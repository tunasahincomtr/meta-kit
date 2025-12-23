<?php

namespace TunaSahincomtr\MetaKit\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use TunaSahincomtr\MetaKit\Models\MetaKitPage;

class MetaKitManager
{
    protected UrlKeyResolver $resolver;
    protected ?array $current = null;
    protected array $overrides = [];

    public function __construct()
    {
        $this->resolver = new UrlKeyResolver();
    }

    /**
     * Resolve meta data for current request.
     */
    public function resolve(?Request $request = null): array
    {
        $request = $request ?? request();
        $keys = $this->resolver->resolveFromRequest($request);
        
        // If we have overrides, always resolve fresh to apply them
        $shouldResolveFresh = !empty($this->overrides) || $this->current === null;
        
        if (!$shouldResolveFresh) {
            return $this->current;
        }

        // Check cache first (only if no overrides)
        if (empty($this->overrides)) {
            $cached = Cache::get($keys['cache_key']);
            if ($cached !== null) {
                $this->current = $cached;
                return $this->applyOverrides($this->current);
            }
        }

        // Check database
        $page = MetaKitPage::active()
            ->forDomain($keys['domain'])
            ->forPath($keys['path'])
            ->forQueryHash($keys['query_hash'])
            ->first();

        if ($page) {
            $this->current = $this->pageToArray($page);
        } else {
            // Fallback to defaults
            $this->current = $this->getFallback($request, $keys);
        }

        // Cache the result (only if no overrides)
        if (empty($this->overrides)) {
            Cache::put(
                $keys['cache_key'],
                $this->current,
                now()->addMinutes(config('metakit.cache_ttl_minutes', 360))
            );
        }

        return $this->applyOverrides($this->current);
    }

    /**
     * Get fallback meta data.
     */
    protected function getFallback(Request $request, array $keys): array
    {
        $config = config('metakit.default');
        $canonical = $this->buildCanonicalUrl($request, $keys);

        return [
            'title' => $config['site_name'] . $config['title_suffix'],
            'description' => $config['site_name'] . ' - ' . ($config['title_suffix'] ?? ''),
            'keywords' => null,
            'robots' => $config['default_robots'] ?? 'index, follow',
            'canonical_url' => $canonical,
            'og_title' => $config['site_name'],
            'og_description' => $config['site_name'] . ' - ' . ($config['title_suffix'] ?? ''),
            'og_image' => $config['default_image'] ?? '/images/og-default.jpg',
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $config['site_name'],
            'twitter_description' => $config['site_name'] . ' - ' . ($config['title_suffix'] ?? ''),
            'twitter_image' => $config['default_image'] ?? '/images/og-default.jpg',
            'jsonld' => $this->getDefaultJsonLd($request, $keys),
        ];
    }

    /**
     * Build canonical URL.
     */
    protected function buildCanonicalUrl(Request $request, array $keys): string
    {
        $normalizedQuery = $this->resolver->normalizeQuery($request);
        $queryString = !empty($normalizedQuery) ? '?' . http_build_query($normalizedQuery) : '';
        
        $scheme = $request->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $keys['domain'] . $keys['path'] . $queryString;
    }

    /**
     * Get default JSON-LD structure.
     */
    protected function getDefaultJsonLd(Request $request, array $keys): array
    {
        $config = config('metakit.default');
        $canonical = $this->buildCanonicalUrl($request, $keys);
        $scheme = $request->isSecure() ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $keys['domain'];

        return [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $config['site_name'],
                'url' => $baseUrl,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $config['site_name'],
                'url' => $baseUrl,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => 'Home',
                        'item' => $baseUrl,
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert page model to array.
     */
    protected function pageToArray(MetaKitPage $page): array
    {
        return [
            'title' => $page->title,
            'description' => $page->description,
            'keywords' => $page->keywords,
            'robots' => $page->robots,
            'canonical_url' => $page->canonical_url,
            'og_title' => $page->og_title,
            'og_description' => $page->og_description,
            'og_image' => $page->og_image,
            'twitter_card' => $page->twitter_card,
            'twitter_title' => $page->twitter_title,
            'twitter_description' => $page->twitter_description,
            'twitter_image' => $page->twitter_image,
            'jsonld' => $page->jsonld,
        ];
    }

    /**
     * Apply overrides to current meta data.
     */
    protected function applyOverrides(array $data): array
    {
        return array_merge($data, $this->overrides);
    }

    /**
     * Get current meta data.
     */
    public function current(): array
    {
        return $this->resolve();
    }

    /**
     * Override methods.
     */
    public function setTitle(string $title): self
    {
        $this->overrides['title'] = $title;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->overrides['description'] = $description;
        return $this;
    }

    public function setCanonical(string $url): self
    {
        $this->overrides['canonical_url'] = $url;
        return $this;
    }

    public function setRobots(string $robots): self
    {
        $this->overrides['robots'] = $robots;
        return $this;
    }

    public function setOgImage(string $url): self
    {
        $this->overrides['og_image'] = $url;
        return $this;
    }

    public function addJsonLd(array $jsonLd): self
    {
        $current = $this->resolve();
        $existing = $current['jsonld'] ?? [];
        $this->overrides['jsonld'] = array_merge($existing, [$jsonLd]);
        return $this;
    }

    public function set(array $data): self
    {
        $this->overrides = array_merge($this->overrides, $data);
        return $this;
    }

    /**
     * Get specific meta value.
     */
    public function getTitle(): string
    {
        return $this->resolve()['title'] ?? '';
    }

    public function getMeta(string $key): ?string
    {
        return $this->resolve()[$key] ?? null;
    }

    /**
     * Render all meta tags.
     */
    public function render(): string
    {
        $meta = $this->resolve();
        $html = [];

        // Title
        if (!empty($meta['title'])) {
            $html[] = '<title>' . e($meta['title']) . '</title>';
        }

        // Basic meta tags
        if (!empty($meta['description'])) {
            $html[] = '<meta name="description" content="' . e($meta['description']) . '">';
        }
        if (!empty($meta['keywords'])) {
            $html[] = '<meta name="keywords" content="' . e($meta['keywords']) . '">';
        }
        if (!empty($meta['robots'])) {
            $html[] = '<meta name="robots" content="' . e($meta['robots']) . '">';
        }

        // Canonical
        if (!empty($meta['canonical_url'])) {
            $html[] = '<link rel="canonical" href="' . e($meta['canonical_url']) . '">';
        }

        // Open Graph
        if (!empty($meta['og_title'])) {
            $html[] = '<meta property="og:title" content="' . e($meta['og_title']) . '">';
        }
        if (!empty($meta['og_description'])) {
            $html[] = '<meta property="og:description" content="' . e($meta['og_description']) . '">';
        }
        if (!empty($meta['og_image'])) {
            $html[] = '<meta property="og:image" content="' . e($meta['og_image']) . '">';
        }
        $html[] = '<meta property="og:type" content="website">';
        if (!empty($meta['canonical_url'])) {
            $html[] = '<meta property="og:url" content="' . e($meta['canonical_url']) . '">';
        }

        // Twitter Card
        if (!empty($meta['twitter_card'])) {
            $html[] = '<meta name="twitter:card" content="' . e($meta['twitter_card']) . '">';
        }
        if (!empty($meta['twitter_title'])) {
            $html[] = '<meta name="twitter:title" content="' . e($meta['twitter_title']) . '">';
        }
        if (!empty($meta['twitter_description'])) {
            $html[] = '<meta name="twitter:description" content="' . e($meta['twitter_description']) . '">';
        }
        if (!empty($meta['twitter_image'])) {
            $html[] = '<meta name="twitter:image" content="' . e($meta['twitter_image']) . '">';
        }

        return implode("\n    ", $html);
    }

    /**
     * Render JSON-LD scripts.
     */
    public function renderJsonLd(): string
    {
        $meta = $this->resolve();
        $jsonLd = $meta['jsonld'] ?? [];

        if (empty($jsonLd)) {
            return '';
        }

        $html = [];
        foreach ($jsonLd as $item) {
            $html[] = '<script type="application/ld+json">' . json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
        }

        return implode("\n    ", $html);
    }

    /**
     * Render debug comments.
     */
    public function renderDebug(): string
    {
        $keys = $this->resolver->resolveFromRequest(request());
        $meta = $this->resolve();

        $debug = [
            'Domain: ' . $keys['domain'],
            'Path: ' . $keys['path'],
            'Query Hash: ' . ($keys['query_hash'] ?? 'null'),
            'Cache Key: ' . $keys['cache_key'],
            'Title: ' . ($meta['title'] ?? 'null'),
        ];

        return "\n    <!-- MetaKit Debug:\n    " . implode("\n    ", $debug) . "\n    -->\n";
    }

    /**
     * Purge cache for a specific page.
     */
    public function purgeCache(string $domain, string $path, ?string $queryHash = null): void
    {
        $cacheKey = $this->resolver->generateCacheKey($domain, $path, $queryHash);
        Cache::forget($cacheKey);
    }
}

