<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        $sales = Order::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')->pluck('c', 'd');

        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();
            $chart[] = ['date' => $day, 'orders' => (int) ($sales[$day] ?? 0)];
        }

        return response()->json([
            'today' => [
                'orders'  => Order::whereDate('created_at', $today)->count(),
                'revenue' => (float) Order::whereDate('created_at', $today)
                    ->whereIn('status', ['confirmed', 'preparing', 'shipped', 'delivered'])->sum('total'),
            ],
            'totals' => [
                'orders_pending' => Order::where('status', 'pending')->count(),
                'orders_total'   => Order::count(),
                'revenue'        => (float) Order::whereIn('status', ['confirmed', 'shipped', 'delivered'])->sum('total'),
                'products'       => Product::count(),
                'low_stock'      => Product::where('track_stock', true)->where('stock', '<=', 3)->count(),
                'unread_notifications' => AdminNotification::unread()->count(),
            ],
            'status_counts' => Order::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
            'chart'         => $chart,
            'recent_orders' => Order::with('wilaya')->latest()->take(8)->get()
                ->map(fn ($o) => OrderController::brief($o)),
        ]);
    }
}
