<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class HeroSliderImageController extends Controller
{
    /**
     * Upload hero slider image
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,jpg,png,webp,gif|max:5120', // Max 5MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $image = $request->file('image');
            $imageName = time() . '_hero_' . $image->getClientOriginalName();
            $imagePath = $image->storeAs('hero-slider', $imageName, 'public');
            
            // Get the full URL
            $imageUrl = Storage::disk('public')->url($imagePath);

            return response()->json([
                'success' => true,
                'url' => $imageUrl,
                'path' => $imagePath,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }
}

