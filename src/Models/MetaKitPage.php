<?php

namespace TunaSahincomtr\MetaKit\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'jsonld',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'jsonld' => 'array',
    ];

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

