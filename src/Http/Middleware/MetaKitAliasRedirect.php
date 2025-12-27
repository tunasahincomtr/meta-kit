<?php

namespace TunaSahincomtr\MetaKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use TunaSahincomtr\MetaKit\Models\MetaKitAlias;

class MetaKitAliasRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for API routes
        if ($request->is('api/*')) {
            return $next($request);
        }

        $domain = $request->getHost();
        $path = '/' . ltrim($request->path(), '/');

        // Check cache first
        $cacheKey = "metakit_alias:{$domain}:{$path}";
        $newPath = Cache::get($cacheKey);

        if ($newPath === null) {
            // Check database
            $alias = MetaKitAlias::findAlias($domain, $path);
            
            if ($alias) {
                $newPath = $alias->new_path;
                // Cache for 24 hours
                Cache::put($cacheKey, $newPath, now()->addHours(24));
            } else {
                // Cache negative result for 1 hour to avoid repeated DB queries
                Cache::put($cacheKey, false, now()->addHour());
            }
        }

        // If alias found, redirect (301 Permanent Redirect)
        if ($newPath && $newPath !== false && $newPath !== $path) {
            // Preserve query string
            $queryString = $request->getQueryString();
            $redirectUrl = $newPath . ($queryString ? '?' . $queryString : '');
            
            // Build full URL
            $scheme = $request->isSecure() ? 'https' : 'http';
            $fullUrl = $scheme . '://' . $domain . $redirectUrl;

            return redirect($fullUrl, 301);
        }

        return $next($request);
    }
}

