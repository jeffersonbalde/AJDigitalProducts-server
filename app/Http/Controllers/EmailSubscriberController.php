<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListEmailSubscriberRequest;
use App\Http\Requests\StoreEmailSubscriberRequest;
use App\Models\EmailSubscriber;
use Illuminate\Http\JsonResponse;

class EmailSubscriberController extends Controller
{
    public function index(ListEmailSubscriberRequest $request): JsonResponse
    {
        $query = EmailSubscriber::query();

        $search = $request->validated('search');
        if ($search) {
            $query->where('email', 'like', '%'.$search.'%');
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->integer('per_page', 15);
        $subscribers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers->items(),
            'pagination' => [
                'current_page' => $subscribers->currentPage(),
                'last_page' => $subscribers->lastPage(),
                'per_page' => $subscribers->perPage(),
                'total' => $subscribers->total(),
                'from' => $subscribers->firstItem(),
                'to' => $subscribers->lastItem(),
            ],
        ]);
    }

    public function store(StoreEmailSubscriberRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $email = strtolower(trim($validated['email']));

        $existing = EmailSubscriber::where('email', $email)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'already_subscribed' => true,
                'message' => 'Email already subscribed.',
                'subscriber' => $existing,
            ]);
        }

        $subscriber = EmailSubscriber::create([
            'email' => $email,
        ]);

        return response()->json([
            'success' => true,
            'already_subscribed' => false,
            'message' => 'Subscription successful.',
            'subscriber' => $subscriber,
        ], 201);
    }
}
