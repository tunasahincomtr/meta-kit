<?php

namespace TunaSahincomtr\MetaKit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use TunaSahincomtr\MetaKit\Services\MetaKitManager;

class MetaKitPage extends Model
{
    use HasFactory;

    protected $table = 'metakit_pages';

    protected $fillable = [
        'domain',
        'path',
        'query_hash',
        'title',
        'description',
        'keywords',
        'robots',
        'language',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'og_site_name',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'twitter_site',
        'twitter_creator',
        'author',
        'theme_color',
        'jsonld',
        'breadcrumb_jsonld',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'jsonld' => 'array',
        'breadcrumb_jsonld' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Purge cache when page is saved (created or updated)
        static::saved(function ($page) {
            $page->purgeCache();
            // Also purge sitemap cache
            \TunaSahincomtr\MetaKit\Http\Controllers\SitemapController::purgeCache();
        });

        // Purge cache when page is deleted
        static::deleted(function ($page) {
            $page->purgeCache();
            // Also purge sitemap cache
            \TunaSahincomtr\MetaKit\Http\Controllers\SitemapController::purgeCache();
        });
    }

    /**
     * Purge cache for this page.
     * Also purges old cache if domain/path/query_hash changed.
     */
    public function purgeCache(): void
    {
        try {
            $manager = App::make(MetaKitManager::class);
            
            // Purge current cache
            $manager->purgeCache(
                $this->domain,
                $this->path,
                $this->query_hash
            );

            // If path or domain changed, also purge old cache
            if ($this->wasChanged(['domain', 'path', 'query_hash'])) {
                $originalDomain = $this->getOriginal('domain');
                $originalPath = $this->getOriginal('path');
                $originalQueryHash = $this->getOriginal('query_hash');

                // Only purge if old values exist and are different
                if ($originalDomain && $originalPath) {
                    $manager->purgeCache(
                        $originalDomain,
                        $originalPath,
                        $originalQueryHash
                    );
                }
            }
        } catch (\Exception $e) {
            // Silently fail - don't break model operations if cache fails
            if (config('app.debug', false)) {
                Log::warning('MetaKit: Failed to purge cache', [
                    'error' => $e->getMessage(),
                    'page_id' => $this->id ?? null,
                ]);
            }
        }
    }

    /**
     * Get the user that created the page.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'created_by');
    }

    /**
     * Get the user that updated the page.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'updated_by');
    }

    /**
     * Scope a query to only include active pages.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to filter by domain.
     */
    public function scopeForDomain($query, string $domain)
    {
        return $query->where('domain', $domain);
    }

    /**
     * Scope a query to filter by path.
     */
    public function scopeForPath($query, string $path)
    {
        return $query->where('path', $path);
    }

    /**
     * Scope a query to filter by query hash.
     */
    public function scopeForQueryHash($query, ?string $queryHash)
    {
        return $query->where('query_hash', $queryHash);
    }
}
