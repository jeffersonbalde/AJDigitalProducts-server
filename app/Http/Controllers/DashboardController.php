<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Only admins or personnel should access admin dashboard stats
            if (! $user || (! ($user instanceof \App\Models\Admin) && ! ($user instanceof \App\Models\Personnel))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Admin access required.',
                ], 403);
            }

            $totalCustomers = Customer::query()->count();

            $ordersQuery = Order::query();

            $totalOrders = (clone $ordersQuery)->count();
            $paidOrders = (clone $ordersQuery)->where('payment_status', 'paid')->count();
            $pendingOrders = (clone $ordersQuery)->where('payment_status', 'pending')->count();
            $failedOrders = (clone $ordersQuery)->where('payment_status', 'failed')->count();

            $totalRevenue = (clone $ordersQuery)
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            $today = now();

            $todayRevenue = (clone $ordersQuery)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', $today->toDateString())
                ->sum('total_amount');

            $monthRevenue = (clone $ordersQuery)
                ->where('payment_status', 'paid')
                ->whereBetween('created_at', [
                    $today->copy()->startOfMonth(),
                    $today->copy()->endOfMonth(),
                ])
                ->sum('total_amount');

            $activeProducts = Product::query()
                ->where('is_active', true)
                ->count();

            $recentOrders = Order::query()
                ->with('customer')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'stats' => [
                    'totalCustomers' => $totalCustomers,
                    'totalOrders' => $totalOrders,
                    'paidOrders' => $paidOrders,
                    'pendingOrders' => $pendingOrders,
                    'failedOrders' => $failedOrders,
                    'totalRevenue' => $totalRevenue,
                    'todayRevenue' => $todayRevenue,
                    'monthRevenue' => $monthRevenue,
                    'activeProducts' => $activeProducts,
                ],
                'recent_orders' => $recentOrders,
            ]);
        } catch (\Throwable $e) {
            Log::error('Dashboard stats failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}


