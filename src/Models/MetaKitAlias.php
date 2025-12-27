<?php

namespace TunaSahincomtr\MetaKit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MetaKitAlias extends Model
{
    protected $table = 'metakit_aliases';

    protected $fillable = [
        'domain',
        'old_path',
        'new_path',
    ];

    public $timestamps = true;

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Purge alias cache when alias is saved (created or updated)
        static::saved(function ($alias) {
            $alias->purgeCache();
        });

        // Purge alias cache when alias is deleted
        static::deleted(function ($alias) {
            $alias->purgeCache();
        });
    }

    /**
     * Purge alias cache for this alias.
     * Also purges old cache if domain/old_path changed.
     */
    public function purgeCache(): void
    {
        try {
            // Purge current cache
            $cacheKey = $this->getCacheKey($this->domain, $this->old_path);
            Cache::forget($cacheKey);

            // If domain or old_path changed, also purge old cache
            if ($this->wasChanged(['domain', 'old_path'])) {
                $originalDomain = $this->getOriginal('domain');
                $originalOldPath = $this->getOriginal('old_path');

                // Only purge if old values exist and are different
                if ($originalDomain && $originalOldPath) {
                    $oldCacheKey = $this->getCacheKey($originalDomain, $originalOldPath);
                    Cache::forget($oldCacheKey);
                }
            }
        } catch (\Exception $e) {
            // Silently fail - don't break model operations if cache fails
            if (config('app.debug', false)) {
                Log::warning('MetaKit: Failed to purge alias cache', [
                    'error' => $e->getMessage(),
                    'alias_id' => $this->id ?? null,
                ]);
            }
        }
    }

    /**
     * Generate cache key for alias.
     */
    protected function getCacheKey(string $domain, string $oldPath): string
    {
        return "metakit_alias:{$domain}:{$oldPath}";
    }

    /**
     * Scope a query to filter by domain.
     */
    public function scopeForDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * Scope a query to filter by old path.
     */
    public function scopeForOldPath($query, string $oldPath)
    {
        return $query->where('old_path', $oldPath);
    }

    /**
     * Find alias by domain and old path.
     */
    public static function findAlias(string $domain, string $oldPath): ?self
    {
        return static::forDomain($domain)
            ->forOldPath($oldPath)
            ->first();
    }
}
