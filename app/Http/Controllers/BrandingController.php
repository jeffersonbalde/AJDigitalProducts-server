<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function show(): JsonResponse
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

    public function logo()
    {
        $logoPath = SiteSetting::getValue('branding.logo_path');
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return response()->noContent(404);
        }

        return Storage::disk('public')->response($logoPath, null, [
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}
