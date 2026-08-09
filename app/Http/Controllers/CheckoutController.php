<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wilaya;
use App\Services\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart) {}

    public function index()
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        $wilayas = Wilaya::active()->orderBy('code')->get();

        return view('storefront.checkout', [
            'items'        => $this->cart->lines(),
            'subtotal'     => $this->cart->subtotal(),
            'savings'      => $this->cart->quantitySavings(),
            'wilayas'      => $wilayas,
            'freeShipping' => $this->cart->hasFreeShipping(),
        ]);
    }

    /** AJAX: return the delivery fee for a wilaya + delivery type. */
    public function fee(Request $request)
    {
        if ($this->cart->hasFreeShipping()) {
            return response()->json(['fee' => 0, 'free' => true]);
        }

        $wilaya = Wilaya::find($request->query('wilaya_id'));
        $type = $request->query('type', 'home');
        $fee = $wilaya ? $wilaya->feeFor($type) : config('saidi.default_delivery_fee');

        return response()->json(['fee' => $fee, 'free' => false]);
    }

    /**
     * AJAX: the communes the carrier actually serves in a wilaya, so the
     * customer picks a name Noest recognises instead of typing one it will
     * reject. Cached in the driver; empty list = free text, as before.
     */
    public function communes(Request $request)
    {
        $wilaya = Wilaya::find($request->query('wilaya_id'));
        $list = $wilaya
            ? ((new \App\Services\Delivery\NoestDriver())->communes((int) $wilaya->code) ?? [])
            : [];

        return response()->json(['communes' => $list]);
    }

    public function store(Request $request)
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        // The phone/commune rules mirror what the carrier requires: an order
        // that can't be handed to Noest is worthless to everyone.
        $phoneRule = function ($attribute, $value, $fail) {
            if ($value !== null && $value !== '' && \App\Services\Delivery\NoestDriver::normalizePhone($value) === null) {
                $fail(__('shop.invalid_phone'));
            }
        };

        $noest = new \App\Services\Delivery\NoestDriver();

        // The commune must be one the carrier serves. Only enforced when we
        // actually hold their list: if their API is down, the shop keeps
        // taking orders rather than turning customers away.
        $communeRule = function ($attribute, $value, $fail) use ($request, $noest) {
            $wilaya = Wilaya::find($request->input('wilaya_id'));

            if (! $wilaya || ! $value || $request->input('delivery_type') === 'stopdesk') {
                return;
            }

            $known = $noest->communes((int) $wilaya->code);

            if ($known && $noest->resolveCommune((int) $wilaya->code, $value) === null) {
                $fail(__('shop.unknown_commune'));
            }
        };

        $data = $request->validate([
            'customer_name' => 'required|string|max:120',
            'phone'         => ['required', 'string', 'max:30', $phoneRule],
            'phone2'        => ['nullable', 'string', 'max:30', $phoneRule],
            'wilaya_id'     => 'required|exists:wilayas,id',
            'commune'       => ['required_if:delivery_type,home', 'nullable', 'string', 'max:120', $communeRule],
            'address'       => 'required_if:delivery_type,home|nullable|string|max:500',
            'delivery_type' => 'required|in:home,stopdesk',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $wilaya = Wilaya::findOrFail($data['wilaya_id']);

        // Store the commune exactly as the carrier spells it, so the order can
        // be handed over later without any guessing.
        if (! empty($data['commune'])) {
            $data['commune'] = $noest->resolveCommune((int) $wilaya->code, $data['commune']) ?? $data['commune'];
        }

        // Free-delivery campaign: settled server-side, never from the form.
        $deliveryFee = $this->cart->hasFreeShipping() ? 0.0 : $wilaya->feeFor($data['delivery_type']);

        // Resolve quantity breaks ONCE, then bill exactly what we resolved —
        // recomputing inside the loop could drift from the displayed subtotal.
        $lines = $this->cart->lines();
        $subtotal = array_sum(array_column($lines, 'line_total'));

        $order = DB::transaction(function () use ($data, $wilaya, $deliveryFee, $subtotal, $lines, $request) {
            $order = Order::create([
                'reference'     => Order::generateReference(),
                'client_id'     => Auth::guard('client')->id(),
                'customer_name' => $data['customer_name'],
                'phone'         => $data['phone'],
                'phone2'        => $data['phone2'] ?? null,
                'wilaya_id'     => $wilaya->id,
                'commune'       => $data['commune'] ?? null,
                'address'       => $data['address'] ?? null,
                'delivery_type' => $data['delivery_type'],
                'subtotal'      => $subtotal,
                'delivery_fee'  => $deliveryFee,
                'total'         => $subtotal + $deliveryFee,
                'payment_method'=> 'cod',
                'status'        => 'pending',
                'notes'         => $data['notes'] ?? null,
                'utm_source'    => $request->cookie('utm_source'),
                'fbp'           => $request->cookie('_fbp'),
                'fbc'           => $request->cookie('_fbc'),
                'ip'            => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 500),
            ]);

            $shortages = [];

            foreach ($lines as $line) {
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $line['product_id'],
                    'product_variant_id' => $line['variant_id'],
                    'name'               => $line['name'],
                    'variant_label'      => $line['variant'],
                    'image'              => $line['image'],
                    'unit_price'         => $line['price'],
                    'quantity'           => $line['qty'],
                    'line_total'         => $line['line_total'],
                ]);

                if ($product = Product::find($line['product_id'])) {
                    $variant = $line['variant_id'] ? ProductVariant::find($line['variant_id']) : null;
                    $available = $product->availableFor($variant);

                    // Never abort here: an oversized order is still an order.
                    // We take it, floor the stock at 0 and flag the shortfall.
                    $missing = app(\App\Services\StockService::class)
                        ->sell($product, $variant, (int) $line['qty'], $order->reference);

                    if ($missing > 0) {
                        $label = trim($line['name'] . ' ' . ($line['variant'] ? "({$line['variant']})" : ''));
                        $shortages[] = "{$label} — demandé {$line['qty']}, disponible " . (int) $available
                            . ", manque {$missing}";
                    }
                }
            }

            if ($shortages) {
                $order->update([
                    'has_stock_issue' => true,
                    'stock_issue'     => implode("\n", $shortages),
                ]);
            }

            return $order;
        });

        $this->cart->clear();

        // Notify the admin of the new order (in-app bell).
        AdminNotification::raise(
            'order',
            "Nouvelle commande {$order->reference}",
            "{$order->customer_name} · " . number_format((float) $order->total, 2, ',', ' ') . ' DA',
            route('admin.orders.show', $order),
            '🧾'
        );

        // Second, louder bell when we can't cover the quantities ordered —
        // this one needs a human to call the customer back.
        if ($order->has_stock_issue) {
            AdminNotification::raise(
                'stock',
                "⚠️ Stock insuffisant — commande {$order->reference}",
                $order->stock_issue,
                route('admin.orders.show', $order),
                '⚠️'
            );
        }

        // Fan out Telegram alerts AFTER the response is sent so checkout stays fast.
        dispatch(function () use ($order) {
            app(\App\Services\TelegramNotifier::class)->orderCreated($order);
        })->afterResponse();

        return redirect()->route('checkout.success', $order->reference);
    }

    public function success(string $reference)
    {
        $order = Order::where('reference', $reference)->with('items', 'wilaya')->firstOrFail();

        return view('storefront.success', compact('order'));
    }
}
