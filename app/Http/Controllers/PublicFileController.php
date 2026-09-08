<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicFileController extends Controller
{
    /**
     * Handle specific file routes (products/thumbnails/{file}, products/features/{file}, etc.)
     */
    public function showFile(Request $request, string $file, string $path = null)
    {
        // Reconstruct the full path from the route segments
        $uri = $request->getRequestUri();
        $fullPath = str_replace('/api/files/', '', $uri);
        $fullPath = ltrim($fullPath, '/');
        
        // Remove query string
        if (($pos = strpos($fullPath, '?')) !== false) {
            $fullPath = substr($fullPath, 0, $pos);
        }
        
        return $this->serveFile($fullPath);
    }

    /**
     * Handle catch-all file route
     */
    public function show(Request $request, string $path)
    {
        $path = ltrim($path, '/');
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return $this->serveFile($path);
    }

    /**
     * Serve a file from storage
     */
    private function serveFile(string $path)
    {
        // URL decode
        $path = urldecode($path);
        $path = ltrim($path, '/');
        
        // Prevent path traversal
        if (str_contains($path, '..') || empty($path)) {
            \Log::warning('PublicFileController: Invalid path', ['path' => $path]);
            return response()->noContent(Response::HTTP_NOT_FOUND);
        }

        // Check if file exists
        $exists = Storage::disk('public')->exists($path);
        $fullPath = Storage::disk('public')->path($path);
        
        if (! $exists) {
            \Log::warning('PublicFileController: File not found', [
                'path' => $path,
                'full_path' => $fullPath,
                'directory_exists' => is_dir(dirname($fullPath)),
            ]);
            return response()->noContent(Response::HTTP_NOT_FOUND);
        }

        // Return the file
        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
