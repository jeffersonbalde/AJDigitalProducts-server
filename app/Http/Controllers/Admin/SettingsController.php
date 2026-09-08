<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\Admin;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! $user || ! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'password' => $validated['new_password'],
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    public function getBranding(): JsonResponse
    {
        $logoSetting = SiteSetting::query()->where('key', 'branding.logo_path')->first();
        $logoPath = $logoSetting?->value;
        $logoText = SiteSetting::getValue('branding.logo_text', 'AJ Creative Studio');

        $logoUrl = null;
        if ($logoPath) {
            $logoUrl = Storage::disk('public')->exists($logoPath)
                ? '/api/branding/logo?v='.($logoSetting?->updated_at?->timestamp ?? time())
                : null;
        }

        return response()->json([
            'success' => true,
            'branding' => [
                'logo_text' => $logoText,
                'logo_path' => $logoPath,
                'logo_url' => $logoUrl,
            ],
        ]);
    }

    public function updateBranding(UpdateBrandingRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! ($user instanceof Admin)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        $validated = $request->validated();

        if (array_key_exists('logo_text', $validated)) {
            $nextText = trim((string) ($validated['logo_text'] ?? ''));
            SiteSetting::setValue('branding.logo_text', $nextText !== '' ? $nextText : 'AJ Creative Studio');
        }

        if ($request->hasFile('logo')) {
            $previousPath = SiteSetting::getValue('branding.logo_path');

            $file = $request->file('logo');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $path = $file->storeAs('branding', 'logo-'.now()->format('YmdHis').'.'.$ext, 'public');
            SiteSetting::setValue('branding.logo_path', $path);

            if ($previousPath && $previousPath !== $path && Storage::disk('public')->exists($previousPath)) {
                Storage::disk('public')->delete($previousPath);
            }
        }

        return $this->getBranding();
    }
}
