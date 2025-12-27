<?php

namespace TunaSahincomtr\MetaKit\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UrlKeyResolver
{
    /**
     * Resolve domain from request.
     * Includes port if present (for local development: 127.0.0.1:8000).
     */
    public function resolveDomain(Request $request): string
    {
        $host = $request->getHost();
        $port = $request->getPort();
        
        // Include port if it's not a standard port (80 for http, 443 for https)
        if ($port && $port != 80 && $port != 443) {
            return $host . ':' . $port;
        }
        
        return $host;
    }

    /**
     * Resolve path from request.
     */
    public function resolvePath(Request $request): string
    {
        $path = '/' . ltrim($request->path(), '/');
        return $path === '//' ? '/' : $path;
    }

    /**
     * Resolve query hash from request.
     */
    public function resolveQueryHash(Request $request): ?string
    {
        $query = $this->normalizeQuery($request);
        
        if (empty($query)) {
            return null;
        }

        return sha1(http_build_query($query, '', '&'));
    }

    /**
     * Normalize query parameters.
     */
    public function normalizeQuery(Request $request): array
    {
        $whitelist = config('metakit.query_whitelist', []);
        $query = $request->query();
        
        // Filter by whitelist
        $filtered = array_filter($query, function ($key) use ($whitelist) {
            return in_array($key, $whitelist);
        }, ARRAY_FILTER_USE_KEY);

        // Remove empty values
        $filtered = array_filter($filtered, function ($value) {
            return $value !== null && $value !== '';
        });

        // Sort by key for consistent hashing
        ksort($filtered);

        return $filtered;
    }

    /**
     * Generate cache key.
     */
    public function generateCacheKey(string $domain, string $path, ?string $queryHash = null): string
    {
        $key = "metakit:{$domain}:{$path}";
        
        if ($queryHash) {
            $key .= ":{$queryHash}";
        } else {
            $key .= ':noq';
        }

        return $key;
    }

    /**
     * Resolve all keys from request.
     */
    public function resolveFromRequest(Request $request): array
    {
        $domain = $this->resolveDomain($request);
        $path = $this->resolvePath($request);
        $queryHash = $this->resolveQueryHash($request);
        $cacheKey = $this->generateCacheKey($domain, $path, $queryHash);

        return [
            'domain' => $domain,
            'path' => $path,
            'query_hash' => $queryHash,
            'cache_key' => $cacheKey,
        ];
    }
}

