<?php

namespace App\Http\Controllers;

use App\Models\CustomerTimeLog;
use App\Models\Customer;
use Illuminate\Http\Request;

class TimeLogController extends Controller
{
    /**
     * Display a listing of customer time logs
     */
    public function index(Request $request)
    {
        $query = CustomerTimeLog::with('customer');

        // Filter by customer ID
        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by action (login/logout)
        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('logged_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('logged_at', '<=', $request->date_to);
        }

        // Search by customer name or email
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'logged_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        // Validate sort column to prevent SQL injection
        $allowedSortColumns = ['logged_at', 'created_at', 'action', 'ip_address'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'logged_at';
        }
        
        $allowedSortOrders = ['asc', 'desc'];
        if (!in_array(strtolower($sortOrder), $allowedSortOrders)) {
            $sortOrder = 'desc';
        }
        
        $query->orderBy($sortBy, $sortOrder);

        // Pagination - allow large per_page for "all" requests
        $perPage = $request->get('per_page', 15);
        // Cap at 1000 to prevent memory issues
        if ($perPage > 1000) {
            $perPage = 1000;
        }
        
        $timeLogs = $query->paginate($perPage);

        // Format response
        $timeLogsData = $timeLogs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'customer_id' => $log->customer_id,
                'customer_name' => $log->customer ? $log->customer->name : 'N/A',
                'customer_email' => $log->customer ? $log->customer->email : 'N/A',
                'action' => $log->action,
                'ip_address' => $log->ip_address ?? 'N/A',
                'user_agent' => $log->user_agent ?? 'N/A',
                'logged_at' => $log->logged_at ? $log->logged_at->toISOString() : null,
                'created_at' => $log->created_at ? $log->created_at->toISOString() : null,
            ];
        });

        // Calculate statistics from all matching records (before pagination)
        $statsQuery = CustomerTimeLog::with('customer');
        
        // Apply same filters for stats
        if ($request->has('customer_id') && $request->customer_id) {
            $statsQuery->where('customer_id', $request->customer_id);
        }
        if ($request->has('action') && $request->action) {
            $statsQuery->where('action', $request->action);
        }
        if ($request->has('date_from') && $request->date_from) {
            $statsQuery->whereDate('logged_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $statsQuery->whereDate('logged_at', '<=', $request->date_to);
        }
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $statsQuery->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        $totalLogs = $statsQuery->count();
        $loginLogs = (clone $statsQuery)->where('action', 'login')->count();
        $logoutLogs = (clone $statsQuery)->where('action', 'logout')->count();

        return response()->json([
            'success' => true,
            'time_logs' => $timeLogsData->all(),
            'pagination' => [
                'current_page' => $timeLogs->currentPage(),
                'last_page' => $timeLogs->lastPage(),
                'per_page' => $timeLogs->perPage(),
                'total' => $timeLogs->total(),
                'from' => $timeLogs->firstItem(),
                'to' => $timeLogs->lastItem(),
            ],
            'stats' => [
                'total_logs' => $totalLogs,
                'login_logs' => $loginLogs,
                'logout_logs' => $logoutLogs,
            ],
        ]);
    }

    /**
     * Get time logs for a specific customer
     */
    public function getCustomerLogs($customerId, Request $request)
    {
        $customer = Customer::findOrFail($customerId);

        $query = $customer->timeLogs();

        // Filter by action
        if ($request->has('action') && $request->action) {
            $query->where('action', $request->action);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('logged_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('logged_at', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'logged_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $timeLogs = $query->paginate($perPage);

        $timeLogsData = $timeLogs->getCollection()->map(function ($log) {
            return [
                'id' => $log->id,
                'action' => $log->action,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'logged_at' => $log->logged_at,
                'created_at' => $log->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ],
            'time_logs' => $timeLogsData->all(),
            'pagination' => [
                'current_page' => $timeLogs->currentPage(),
                'last_page' => $timeLogs->lastPage(),
                'per_page' => $timeLogs->perPage(),
                'total' => $timeLogs->total(),
                'from' => $timeLogs->firstItem(),
                'to' => $timeLogs->lastItem(),
            ],
        ]);
    }
}
