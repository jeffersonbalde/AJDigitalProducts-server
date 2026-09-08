<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        // Search functionality
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            } elseif ($request->status === 'verified') {
                $query->where('register_status', 'verified')
                      ->where('is_active', true);
            } elseif ($request->status === 'unverified') {
                $query->where(function($q) {
                    $q->where('register_status', '!=', 'verified')
                      ->orWhereNull('register_status')
                      ->orWhere('is_active', false);
                });
            }
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $customers = $query->paginate($perPage);

        // Remove sensitive data from response
        $customersData = $customers->getCollection()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified_at' => $customer->email_verified_at,
                'is_active' => $customer->is_active,
                'register_status' => $customer->register_status,
                'signup_method' => !empty($customer->google_sub) ? 'google' : 'email',
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'customers' => $customersData->all(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ],
        ]);
    }

    /**
     * Get customer statistics
     */
    public function stats()
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();
        $verifiedCustomers = Customer::where('register_status', 'verified')
                                     ->where('is_active', true)
                                     ->count();
        $unverifiedCustomers = $totalCustomers - $verifiedCustomers;

        return response()->json([
            'success' => true,
            'stats' => [
                'total' => $totalCustomers,
                'active' => $activeCustomers,
                'inactive' => $totalCustomers - $activeCustomers,
                'verified' => $verifiedCustomers,
                'unverified' => $unverifiedCustomers,
            ],
        ]);
    }
}

