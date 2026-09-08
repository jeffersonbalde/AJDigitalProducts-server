<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IncreaseUploadLimits
{
    /**
     * Handle an incoming request.
     *
     * This middleware increases PHP upload limits for product file uploads.
     * It sets these values at runtime since server-level config might not be accessible.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to product upload endpoints
        if ($request->is('api/products') && in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            // Increase upload limits at runtime
            // Note: These might not work if server has hard limits, but worth trying
            @ini_set('upload_max_filesize', '100M');
            @ini_set('post_max_size', '100M');
            @ini_set('max_execution_time', '300');
            @ini_set('max_input_time', '300');
            @ini_set('memory_limit', '256M');
        }

        return $next($request);
    }
}

