<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Wilaya;
use App\Services\CartService;
use Illuminate\Http\Request;
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
            'items'    => $this->cart->items(),
            'subtotal' => $this->cart->subtotal(),
            'wilayas'  => $wilayas,
        ]);
    }

    /** AJAX: return the delivery fee for a wilaya + delivery type. */
    public function fee(Request $request)
    {
        $wilaya = Wilaya::find($request->query('wilaya_id'));
        $type = $request->query('type', 'home');
        $fee = $wilaya ? $wilaya->feeFor($type) : config('saidi.default_delivery_fee');

        return response()->json(['fee' => $fee]);
    }

    public function store(Request $request)
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'customer_name' => 'required|string|max:120',
            'phone'         => 'required|string|max:30',
            'phone2'        => 'nullable|string|max:30',
            'wilaya_id'     => 'required|exists:wilayas,id',
            'commune'       => 'nullable|string|max:120',
            'address'       => 'required_if:delivery_type,home|nullable|string|max:500',
            'delivery_type' => 'required|in:home,stopdesk',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $wilaya = Wilaya::findOrFail($data['wilaya_id']);
        $deliveryFee = $wilaya->feeFor($data['delivery_type']);
        $subtotal = $this->cart->subtotal();

        $order = DB::transaction(function () use ($data, $wilaya, $deliveryFee, $subtotal, $request) {
            $order = Order::create([
                'reference'     => Order::generateReference(),
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

            foreach ($this->cart->items() as $line) {
                OrderItem::create([
                    'order_id'           => $order->id,
                    'product_id'         => $line['product_id'],
                    'product_variant_id' => $line['variant_id'],
                    'name'               => $line['name'],
                    'variant_label'      => $line['variant'],
                    'image'              => $line['image'],
                    'unit_price'         => $line['price'],
                    'quantity'           => $line['qty'],
                    'line_total'         => $line['price'] * $line['qty'],
                ]);

                if ($product = Product::find($line['product_id'])) {
                    if ($product->track_stock) {
                        $product->decrement('stock', $line['qty']);
                    }
                }
            }

            return $order;
        });

        $this->cart->clear();

        return redirect()->route('checkout.success', $order->reference);
    }

    public function success(string $reference)
    {
        $order = Order::where('reference', $reference)->with('items', 'wilaya')->firstOrFail();

        return view('storefront.success', compact('order'));
    }
}
