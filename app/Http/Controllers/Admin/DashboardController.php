<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'orders_total'   => Order::count(),
            'orders_pending' => Order::where('status', 'pending')->count(),
            'revenue'        => Order::whereIn('status', ['confirmed', 'shipped', 'delivered'])->sum('total'),
            'products'       => Product::count(),
        ] + self::visitorStats();

        $recentOrders = Order::with('wilaya')->latest()->take(8)->get();

        // Sales last 14 days for the mini chart
        $sales = Order::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'), DB::raw('SUM(total) as t'))
            ->groupBy('d')->pluck('c', 'd')->toArray();

        $chart = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = Carbon::today()->subDays($i)->toDateString();
            $chart[$day] = $sales[$day] ?? 0;
        }

        $topProducts = Product::withCount('images')
            ->orderByDesc('views')->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentOrders', 'chart', 'topProducts'));
    }

    /** Visitor counts from site_visits (0s until the table is migrated). */
    public static function visitorStats(): array
    {
        try {
            return [
                'visitors_today' => DB::table('site_visits')->where('day', Carbon::today()->toDateString())->count(),
                'visitors_week'  => DB::table('site_visits')->where('day', '>=', Carbon::today()->subDays(6)->toDateString())->count(),
                'views_today'    => (int) DB::table('site_visits')->where('day', Carbon::today()->toDateString())->sum('views'),
            ];
        } catch (\Throwable $e) {
            return ['visitors_today' => 0, 'visitors_week' => 0, 'views_today' => 0];
        }
    }
}
