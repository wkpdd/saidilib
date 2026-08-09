<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Setting;
use App\Models\StockLevel;
use App\Services\Delivery\DeliveryManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class OrderController extends Controller
{
    public function __construct(private DeliveryManager $delivery) {}

    public function index(Request $request)
    {
        $query = Order::with('wilaya')->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $orders = $query->paginate(20)->withQueryString();
        $counts = Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');

        return view('admin.orders.index', compact('orders', 'counts'));
    }

    public function show(Order $order)
    {
        $order->load('items', 'wilaya', 'client', 'adjustments.author');
        $providers = $this->delivery->all();

        return view('admin.orders.show', compact('order', 'providers'));
    }

    /**
     * Edit line-item prices before the deal is confirmed/shipped, recompute the
     * totals, and log every change to order_adjustments.
     */
    public function editPrices(Request $request, Order $order)
    {
        abort_unless($order->is_editable, 403, 'Cette commande ne peut plus être modifiée (déjà expédiée).');

        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.unit_price' => 'nullable|numeric|min:0|max:99999999',
            'reason'             => 'nullable|string|max:190',
        ]);

        $order->load('items');
        $changed = 0;

        DB::transaction(function () use ($order, $data, $request, &$changed) {
            foreach ($order->items as $item) {
                $raw = $data['items'][$item->id]['unit_price'] ?? null;
                if ($raw === null || $raw === '') {
                    continue;
                }
                $new = (float) $raw;
                $old = (float) $item->unit_price;
                if (abs($new - $old) < 0.001) {
                    continue;
                }

                $oldTotal = (float) $item->line_total;
                $newTotal = $new * $item->quantity;
                $item->update(['unit_price' => $new, 'line_total' => $newTotal]);

                OrderAdjustment::create([
                    'order_id'      => $order->id,
                    'order_item_id' => $item->id,
                    'label'         => $item->name,
                    'old_price'     => $old,
                    'new_price'     => $new,
                    'old_total'     => $oldTotal,
                    'new_total'     => $newTotal,
                    'reason'        => $data['reason'] ?? null,
                    'created_by'    => Auth::id(),
                ]);
                $changed++;
            }

            if ($changed) {
                $subtotal = (float) $order->items()->sum('line_total');
                $order->update([
                    'subtotal' => $subtotal,
                    'total'    => $subtotal + (float) $order->delivery_fee - (float) $order->discount,
                ]);
            }
        });

        return back()->with(
            $changed ? 'success' : 'error',
            $changed ? "Prix mis à jour ({$changed} ligne(s)). Total recalculé." : 'Aucun changement de prix.'
        );
    }

    /** Printable Noest delivery slip (bordereau). */
    public function slip(Order $order)
    {
        $order->load('items', 'wilaya');

        return view('admin.orders.slip', compact('order'));
    }

    // ── Fiches de préparation (A4) ────────────────────────────────────

    /**
     * A4 picking sheets the team works from — one order per page.
     * Prints either an explicit selection (`ids`) or everything matching the
     * filters currently applied on the list, capped so nobody accidentally
     * sends 900 pages to the printer.
     */
    public function printSheets(Request $request, ?Order $order = null)
    {
        $orders = $order
            ? new EloquentCollection([$order])
            : $this->selection($request, 200);

        $orders->load('items.product:id,sku,brand', 'wilaya');

        if ($orders->isEmpty()) {
            return redirect()->route('admin.orders.index')->with('error', 'Aucune commande à imprimer.');
        }

        // One query for the whole batch: where each article sits in the shop.
        $productIds = $orders->pluck('items')->flatten()->pluck('product_id')->filter()->unique();
        $locations = StockLevel::with('location:id,name')
            ->whereIn('product_id', $productIds)
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy(fn (StockLevel $l) => $l->product_id . '-' . ((int) $l->product_variant_id));

        if ($request->boolean('mark_preparing')) {
            Order::whereIn('id', $orders->pluck('id'))
                ->whereIn('status', ['pending', 'confirmed'])
                ->update(['status' => 'preparing']);
        }

        return view('admin.orders.print', compact('orders', 'locations'));
    }

    // ── Prête à expédier + Noest ──────────────────────────────────────

    /**
     * Mark an order as prepared. When Noest is configured (and auto-send is
     * on, which it is by default) the parcel is created AND validated at
     * Noest in the same click, so their logistics can collect it.
     */
    public function markReady(Request $request, Order $order)
    {
        $order->update(['ready_at' => now(), 'status' => 'ready']);

        if (! $this->autoSendEnabled() && ! $request->boolean('send')) {
            return back()->with('success', "Commande {$order->reference} marquée prête (envoi Noest désactivé).");
        }

        $result = $this->delivery->sendReady($order);

        return back()->with(
            $result->success ? 'success' : 'error',
            "Commande {$order->reference} prête — " . ($result->message ?? '')
        );
    }

    /** Same as markReady() for a batch selected in the list. */
    public function bulkReady(Request $request)
    {
        $orders = $this->selection($request, 100, requireIds: true);

        if ($orders->isEmpty()) {
            return back()->with('error', 'Sélectionnez au moins une commande.');
        }

        $send = $this->autoSendEnabled();
        $ok = 0;
        $errors = [];

        foreach ($orders as $order) {
            $order->update(['ready_at' => now(), 'status' => 'ready']);

            if (! $send) {
                $ok++;
                continue;
            }

            $result = $this->delivery->sendReady($order);
            if ($result->success) {
                $ok++;
            } else {
                $errors[] = "{$order->reference} : {$result->message}";
            }
        }

        $msg = $send
            ? "{$ok} commande(s) prêtes et envoyées à Noest."
            : "{$ok} commande(s) marquées prêtes.";

        return back()
            ->with($errors ? 'error' : 'success', $errors ? $msg . ' Échecs : ' . implode(' · ', array_slice($errors, 0, 5)) : $msg);
    }

    /**
     * Dry run: show exactly what would be sent to Noest and what they would
     * refuse — without creating anything. This is the tool to reach for when
     * an order comes back "données invalides".
     */
    public function noestCheck(Order $order)
    {
        $driver = $this->delivery->driver('noest');

        if (! $driver instanceof \App\Services\Delivery\NoestDriver || ! $driver->isEnabled()) {
            return back()->with('error', "Noest n'est pas configuré (Paramètres → Livraison Noest).");
        }

        $order->load('items', 'wilaya');
        [$payload, $problem] = $driver->payloadFor($order);

        session()->flash('noest_payload', \Illuminate\Support\Arr::except($payload, ['user_guid']));

        return back()->with(
            $problem ? 'error' : 'success',
            $problem
                ? "❌ Noest refuserait cette commande — {$problem}"
                : '✅ Données valides : Noest devrait accepter cette commande.'
        );
    }

    /** Pull the live Noest timeline for one order. */
    public function refreshTracking(Order $order)
    {
        if ($order->delivery_provider !== 'noest' || ! $order->tracking_number) {
            return back()->with('error', "Cette commande n'a pas de colis Noest à suivre.");
        }

        $res = $this->delivery->refreshTracking([$order]);
        $order->refresh();

        if ($res['failed']) {
            return back()->with('error', 'Noest injoignable — réessayez dans un instant.');
        }
        if (! $res['updated']) {
            return back()->with('error', "Colis {$order->tracking_number} introuvable chez Noest.");
        }

        return back()->with('success', 'Suivi Noest actualisé : ' . ($order->noest_status ?: '—')
            . ($res['advanced'] ? ' · statut de la commande mis à jour.' : ''));
    }

    /** Live board of every parcel currently with Noest. */
    public function tracking(Request $request)
    {
        $orders = Order::noest()
            ->with('wilaya')
            ->when($request->query('q'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                $w->where('reference', 'like', "%{$s}%")
                  ->orWhere('tracking_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%");
            }))
            ->when(! $request->boolean('all'), fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled', 'returned']))
            ->latest('dispatched_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.orders.tracking', compact('orders'));
    }

    /** Refresh the whole board (or the current selection) in one API round. */
    public function refreshTrackingBatch(Request $request)
    {
        $orders = $request->filled('ids')
            ? $this->selection($request, 100, requireIds: true)
            : Order::noest()->whereNotIn('status', ['delivered', 'cancelled', 'returned'])->limit(100)->get();

        if ($orders->isEmpty()) {
            return back()->with('error', 'Aucun colis Noest à actualiser.');
        }

        $res = $this->delivery->refreshTracking($orders);

        if ($res['failed'] && ! $res['updated']) {
            return back()->with('error', 'Noest injoignable — réessayez dans un instant.');
        }

        return back()->with('success', "{$res['updated']} colis actualisé(s)"
            . ($res['advanced'] ? ", {$res['advanced']} statut(s) mis à jour" : '') . '.');
    }

    /** Stream the official carrier label PDF (Noest get/order/label). */
    public function noestLabel(Order $order)
    {
        $pdf = $this->delivery->labelPdf($order);

        abort_if($pdf === null, 404, "Étiquette indisponible (commande non expédiée chez un transporteur, ou API indisponible).");

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="noest-' . $order->reference . '.pdf"',
        ]);
    }

    /**
     * Bulk labels. Noest only exposes one PDF per tracking, so we fetch each
     * one and hand back a ZIP rather than pretending we can merge PDFs.
     */
    public function noestLabelsZip(Request $request)
    {
        $orders = $this->selection($request, 50, requireIds: true)->filter(
            fn (Order $o) => $o->delivery_provider === 'noest' && $o->tracking_number
        );

        if ($orders->isEmpty()) {
            return back()->with('error', 'Aucune commande expédiée chez Noest dans la sélection.');
        }

        $path = tempnam(sys_get_temp_dir(), 'labels') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $missing = [];
        foreach ($orders as $order) {
            $pdf = $this->delivery->labelPdf($order);
            if ($pdf === null) {
                $missing[] = $order->reference;
            } else {
                $zip->addFromString("{$order->reference}-{$order->tracking_number}.pdf", $pdf);
            }
        }

        $count = $zip->numFiles;
        $zip->close();

        if (! $count) {
            @unlink($path);

            return back()->with('error', 'Aucune étiquette récupérée (API Noest indisponible ?).');
        }

        if ($missing) {
            session()->flash('error', 'Étiquettes manquantes : ' . implode(', ', $missing));
        }

        return response()->download($path, 'etiquettes-noest-' . now()->format('Y-m-d_H-i') . '.zip')
            ->deleteFileAfterSend();
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Auto-push to the carrier when an order is marked ready (default: on). */
    private function autoSendEnabled(): bool
    {
        return Setting::get('noest_auto_ready', '1') === '1'
            && (bool) optional($this->delivery->driver('noest'))->isEnabled();
    }

    /**
     * The orders a bulk action applies to: an explicit `ids` selection, or
     * everything matching the filters the admin currently has on the list.
     *
     * `$requireIds` guards the destructive bulk actions: with nothing ticked
     * they must do nothing, never fall back to "the 100 newest orders".
     *
     * @return Collection<int, Order>
     */
    private function selection(Request $request, int $cap, bool $requireIds = false): Collection
    {
        $ids = collect(explode(',', (string) $request->input('ids')))
            ->merge((array) $request->input('id', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique();

        if ($requireIds && $ids->isEmpty()) {
            return new EloquentCollection();
        }

        $query = Order::query()->with('items');

        if ($ids->isNotEmpty()) {
            $query->whereIn('id', $ids);
        } else {
            $query
                ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($request->query('q'), fn ($q, $s) => $q->where(function ($w) use ($s) {
                    $w->where('reference', 'like', "%{$s}%")
                      ->orWhere('customer_name', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%");
                }));
        }

        return $query->latest()->limit($cap)->get();
    }

    /** Record a full or partial refund on an order. */
    public function refund(Request $request, Order $order)
    {
        $maxRefund = (float) $order->total - (float) ($order->refund_amount ?? 0);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . max(0.01, $maxRefund),
            'method' => 'required|in:' . implode(',', array_keys(Order::REFUND_METHODS)),
            'reason' => 'nullable|string|max:255',
        ]);

        $order->update([
            'refund_amount' => (float) ($order->refund_amount ?? 0) + $data['amount'],
            'refund_method' => $data['method'],
            'refund_reason' => $data['reason'] ?? null,
            'refunded_at'   => now(),
            'status'        => 'returned',
        ]);

        // Store credit → credit the client's ledger (reduces what they owe).
        if ($data['method'] === 'store_credit' && $order->client_id) {
            $order->client->transactions()->create([
                'type'        => 'payment',
                'amount'      => $data['amount'],
                'description' => "Avoir remboursement {$order->reference}",
                'order_id'    => $order->id,
                'created_by'  => Auth::id(),
            ]);
        }

        AdminNotification::raise(
            'order',
            "Remboursement {$order->reference}",
            number_format($data['amount'], 2, ',', ' ') . ' DA · ' . Order::REFUND_METHODS[$data['method']],
            route('admin.orders.show', $order),
            '↩️'
        );

        return back()->with('success', 'Remboursement enregistré.');
    }

    /** Close the "stock insuffisant" flag once it's been settled with the client. */
    public function resolveStockIssue(Order $order)
    {
        abort_unless($order->has_stock_issue, 404);

        $order->update(['stock_issue_resolved_at' => now()]);

        return back()->with('success', 'Problème de stock marqué comme réglé.');
    }

    public function updateStatus(Request $request, Order $order)
    {
        $request->validate(['status' => 'required|in:' . implode(',', Order::STATUSES)]);
        $order->update(['status' => $request->status]);

        return back()->with('success', 'Statut mis à jour.');
    }

    public function dispatch(Request $request, Order $order)
    {
        $data = $request->validate([
            'provider' => 'required|string',
            'tracking' => 'nullable|string|max:120',
        ]);

        $result = $this->delivery->dispatch($order, $data['provider'], $data['tracking'] ?? null);

        return back()->with($result->success ? 'success' : 'error', $result->message
            ?? ($result->success ? 'Commande expédiée.' : 'Échec de l\'expédition.'));
    }

    /** Validate a dispatched order with its carrier (Noest valid/order). */
    public function validateShipment(Order $order)
    {
        $result = $this->delivery->validate($order);

        return back()->with($result->success ? 'success' : 'error', $result->message);
    }

}
