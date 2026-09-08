<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListContactMessageRequest;
use App\Http\Requests\StoreContactMessageRequest;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;

class ContactMessageController extends Controller
{
    public function index(ListContactMessageRequest $request): JsonResponse
    {
        $query = ContactMessage::query();

        $search = $request->validated('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%')
                    ->orWhere('comment', 'like', '%'.$search.'%');
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $request->integer('per_page', 15);
        $messages = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'messages' => $messages->items(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
                'from' => $messages->firstItem(),
                'to' => $messages->lastItem(),
            ],
        ]);
    }

    public function store(StoreContactMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $message = ContactMessage::create([
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'comment' => $validated['comment'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully.',
            'contact' => $message,
        ], 201);
    }

    public function destroy(ContactMessage $contactMessage): JsonResponse
    {
        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Message deleted.',
        ]);
    }
}
