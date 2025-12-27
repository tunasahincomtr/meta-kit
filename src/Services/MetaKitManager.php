<?php

namespace TunaSahincomtr\MetaKit\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use TunaSahincomtr\MetaKit\Models\MetaKitPage;

class MetaKitManager
{
    protected UrlKeyResolver $resolver;
    protected array $overrides = [];
    protected array $detectedConflicts = [];

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
        
        // Check cache first (only if no overrides)
        if (empty($this->overrides)) {
            $cached = Cache::get($keys['cache_key']);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Check database
        $page = MetaKitPage::active()
            ->forDomain($keys['domain'])
            ->forPath($keys['path'])
            ->forQueryHash($keys['query_hash'])
            ->first();

        if ($page) {
            $data = $this->pageToArray($page, $request, $keys);
        } else {
            // Fallback to defaults (don't cache fallback, always generate fresh)
            $data = $this->getFallback($request, $keys);
        }

        // Cache the result (only if no overrides and it's from database)
        if (empty($this->overrides) && $page) {
            Cache::put(
                $keys['cache_key'],
                $data,
                now()->addMinutes(config('metakit.cache_ttl_minutes', 360))
            );
        }

        return $this->applyOverrides($data);
    }

    /**
     * Get fallback meta data.
     */
    protected function getFallback(Request $request, array $keys): array
    {
        $config = config('metakit.default');
        $canonical = $this->buildCanonicalUrl($request, $keys);

        // Determine robots meta based on indexing strategy
        $shouldIndex = $this->shouldIndex($request);
        $robots = $shouldIndex 
            ? ($config['default_robots'] ?? 'index, follow')
            : 'noindex, follow';

        return [
            'title' => $config['site_name'] . $config['title_suffix'],
            'description' => $config['site_name'] . ' - ' . ($config['title_suffix'] ?? ''),
            'keywords' => null,
            'robots' => $robots,
            'language' => $config['language'] ?? 'tr',
            'canonical_url' => $canonical,
            'og_title' => $config['site_name'],
            'og_description' => $config['site_name'] . ' - ' . ($config['title_suffix'] ?? ''),
            'og_image' => $config['default_image'] ?? '/images/og-default.jpg',
            'og_site_name' => $config['og_site_name'] ?? $config['site_name'],
            'twitter_card' => 'summary_large_image',
            'twitter_title' => $config['site_name'],
            'twitter_description' => $config['site_name'] . ' - ' . ($config['title_suffix'] ?? ''),
            'twitter_image' => $config['default_image'] ?? '/images/og-default.jpg',
            'twitter_site' => $config['twitter_site'] ?? null,
            'twitter_creator' => $config['twitter_creator'] ?? null,
            'author' => $config['author'] ?? null,
            'theme_color' => $config['theme_color'] ?? '#0d6efd',
            'referrer' => $config['referrer'] ?? 'strict-origin-when-cross-origin',
            'generator' => $config['generator'] ?? 'MetaKit for Laravel',
            'jsonld' => $this->getDefaultJsonLd($request, $keys),
            'breadcrumb_jsonld' => null,
        ];
    }

    /**
     * Build canonical URL with indexing strategy.
     */
    protected function buildCanonicalUrl(Request $request, array $keys): string
    {
        $scheme = $request->isSecure() ? 'https' : 'http';
        $baseUrl = $scheme . '://' . $keys['domain'] . $keys['path'];
        
        // Check if this URL should be indexed
        $shouldIndex = $this->shouldIndex($request);
        
        // If not indexable, canonical points to base path
        if (!$shouldIndex) {
            return $baseUrl;
        }
        
        // Get canonical strategy (may modify query params for pagination, etc.)
        $canonicalQuery = $this->getCanonicalQuery($request);
        $queryString = !empty($canonicalQuery) ? '?' . http_build_query($canonicalQuery) : '';
        
        return $baseUrl . $queryString;
    }

    /**
     * Determine if current URL should be indexed.
     */
    public function shouldIndex(?Request $request = null): bool
    {
        $request = $request ?? request();
        $policy = config('metakit.indexing_policy', []);
        
        if (empty($policy)) {
            return true; // Default: index everything
        }

        // Get normalized query parameters
        $query = $this->resolver->normalizeQuery($request);
        $queryKeys = array_keys($query);
        
        // Check pagination
        $paginationParam = $policy['pagination']['param'] ?? 'page';
        if ($request->has($paginationParam)) {
            $isPaginationIndexable = $policy['pagination']['indexable'] ?? false;
            if (!$isPaginationIndexable) {
                return false;
            }
        }

        // Check max params limit
        if (isset($policy['max_params']) && count($queryKeys) > $policy['max_params']) {
            return false;
        }

        // Check alone_non_indexable params
        if (isset($policy['alone_non_indexable'])) {
            $aloneParams = $policy['alone_non_indexable'];
            $hasOnlyAloneParams = !empty($queryKeys) && 
                                  count($queryKeys) === count(array_intersect($queryKeys, $aloneParams)) &&
                                  count(array_diff($queryKeys, $aloneParams)) === 0;
            if ($hasOnlyAloneParams) {
                return false;
            }
        }

        $strategy = $policy['strategy'] ?? 'denylist';

        if ($strategy === 'allowlist') {
            // Only allowlisted combinations are indexable
            $allowlist = $policy['allowlist'] ?? [];
            if (empty($allowlist)) {
                return false; // Empty allowlist = nothing is indexable
            }

            // Check if current query matches any allowlist pattern
            return $this->matchesPattern($query, $allowlist);
        } else {
            // Denylist: everything is indexable EXCEPT denylisted
            $denylist = $policy['denylist'] ?? [];
            if (empty($denylist)) {
                return true; // Empty denylist = everything is indexable
            }

            // Check if current query matches any denylist pattern
            return !$this->matchesPattern($query, $denylist);
        }
    }

    /**
     * Check if query matches any pattern in list.
     */
    protected function matchesPattern(array $query, array $patterns): bool
    {
        $queryKeys = array_keys($query);

        foreach ($patterns as $pattern) {
            if (!is_array($pattern)) {
                continue;
            }

            // Convert pattern to array of param names (e.g., ['city=istanbul'] -> ['city'])
            $patternKeys = array_map(function ($item) {
                return explode('=', $item)[0];
            }, $pattern);

            // Check if all pattern keys exist in query
            if (count(array_intersect($patternKeys, $queryKeys)) === count($patternKeys)) {
                // If pattern has values (e.g., 'city=istanbul'), check values too
                $valueMatch = true;
                foreach ($pattern as $item) {
                    if (strpos($item, '=') !== false) {
                        [$param, $value] = explode('=', $item, 2);
                        if (!isset($query[$param]) || $query[$param] !== $value) {
                            $valueMatch = false;
                            break;
                        }
                    }
                }

                if ($valueMatch) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get canonical query parameters (may be modified by strategy).
     */
    protected function getCanonicalQuery(Request $request): array
    {
        $policy = config('metakit.indexing_policy', []);
        $pagination = $policy['pagination'] ?? [];
        
        $query = $this->resolver->normalizeQuery($request);
        
        // Handle pagination canonical strategy
        $paginationParam = $pagination['param'] ?? 'page';
        $canonicalStrategy = $pagination['canonical_strategy'] ?? 'base';
        
        if ($request->has($paginationParam)) {
            if ($canonicalStrategy === 'base') {
                // Remove pagination param from canonical (point to page 1 / base)
                unset($query[$paginationParam]);
            }
            // else: 'self' - keep pagination param in canonical
        }

        return $query;
    }

    /**
     * Get default JSON-LD structure.
     * Returns array of JSON-LD objects (Organization, WebSite, BreadcrumbList).
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
     * Normalize JSON-LD data to array format.
     * Combines jsonld and breadcrumb_jsonld into a single array.
     * Handles backward compatibility (single object -> array).
     *
     * @param mixed $jsonLd
     * @param mixed $breadcrumbJsonLd
     * @return array
     */
    protected function normalizeJsonLd($jsonLd, $breadcrumbJsonLd = null): array
    {
        $normalized = [];

        // Normalize jsonld to array
        if (!empty($jsonLd)) {
            if (is_array($jsonLd)) {
                // If it's already an array, check if it's array of objects or single object
                if (isset($jsonLd[0]) && is_array($jsonLd[0])) {
                    // Array of objects - perfect
                    $normalized = $jsonLd;
                } elseif (isset($jsonLd['@type']) || isset($jsonLd['@context'])) {
                    // Single object - wrap it in array
                    $normalized = [$jsonLd];
                } else {
                    // Empty or invalid array
                    $normalized = [];
                }
            } else {
                // Not an array - skip (shouldn't happen due to cast, but just in case)
                $normalized = [];
            }
        }

        // Add breadcrumb_jsonld if exists (backward compatibility)
        if (!empty($breadcrumbJsonLd)) {
            if (is_array($breadcrumbJsonLd)) {
                if (isset($breadcrumbJsonLd['@type'])) {
                    // Single breadcrumb object
                    $normalized[] = $breadcrumbJsonLd;
                } elseif (isset($breadcrumbJsonLd[0]) && is_array($breadcrumbJsonLd[0])) {
                    // Array of breadcrumb objects
                    $normalized = array_merge($normalized, $breadcrumbJsonLd);
                }
            }
        }

        // Filter out empty items
        return array_values(array_filter($normalized, function($item) {
            return !empty($item) && is_array($item);
        }));
    }

    /**
     * Convert page model to array.
     */
    protected function pageToArray(MetaKitPage $page, Request $request, array $keys): array
    {
        $config = config('metakit.default');
        
        // Apply canonical strategy if canonical_url is not explicitly set in DB
        $canonical = $page->canonical_url;
        if (!$canonical) {
            $canonical = $this->buildCanonicalUrl($request, $keys);
        }
        
        // Apply robots strategy if robots is not explicitly set in DB
        $robots = $page->robots;
        if (!$robots) {
            $shouldIndex = $this->shouldIndex($request);
            $robots = $shouldIndex 
                ? ($config['default_robots'] ?? 'index, follow')
                : 'noindex, follow';
        }
        
        return [
            'title' => $page->title,
            'description' => $page->description,
            'keywords' => $page->keywords,
            'robots' => $robots,
            'language' => $page->language ?? $config['language'] ?? 'tr',
            'canonical_url' => $canonical,
            'og_title' => $page->og_title,
            'og_description' => $page->og_description,
            'og_image' => $page->og_image,
            'og_site_name' => $page->og_site_name ?? $config['og_site_name'] ?? $config['site_name'],
            'twitter_card' => $page->twitter_card,
            'twitter_title' => $page->twitter_title,
            'twitter_description' => $page->twitter_description,
            'twitter_image' => $page->twitter_image,
            'twitter_site' => $page->twitter_site ?? $config['twitter_site'] ?? null,
            'twitter_creator' => $page->twitter_creator ?? $config['twitter_creator'] ?? null,
            'author' => $page->author ?? $config['author'] ?? null,
            'theme_color' => $page->theme_color ?? $config['theme_color'] ?? '#0d6efd',
            'referrer' => $config['referrer'] ?? 'strict-origin-when-cross-origin',
            'generator' => $config['generator'] ?? 'MetaKit for Laravel',
            'jsonld' => $this->normalizeJsonLd($page->jsonld, $page->breadcrumb_jsonld),
            'breadcrumb_jsonld' => $page->breadcrumb_jsonld, // Keep for backward compatibility
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

    /**
     * Set canonical using indexing strategy (smart canonical).
     * If URL should not be indexed, canonical will point to base path.
     */
    public function setCanonicalStrategy(?Request $request = null): self
    {
        $request = $request ?? request();
        $keys = $this->resolver->resolveFromRequest($request);
        $canonical = $this->buildCanonicalUrl($request, $keys);
        $this->overrides['canonical_url'] = $canonical;
        
        // Also set robots if not already set
        if (!isset($this->overrides['robots'])) {
            $shouldIndex = $this->shouldIndex($request);
            $this->overrides['robots'] = $shouldIndex 
                ? (config('metakit.default.default_robots') ?? 'index, follow')
                : 'noindex, follow';
        }
        
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

    /**
     * Add a JSON-LD object to the existing JSON-LD array.
     * 
     * @param array $jsonLd Single JSON-LD object or array of objects
     * @return self
     */
    public function addJsonLd(array $jsonLd): self
    {
        // Get current resolved data (will include any existing overrides)
        $current = $this->resolve();
        $existing = $current['jsonld'] ?? [];
        
        // Normalize existing to array
        if (!is_array($existing)) {
            $existing = !empty($existing) ? [$existing] : [];
        }
        
        // Merge with new JSON-LD
        if (!isset($this->overrides['jsonld'])) {
            $this->overrides['jsonld'] = $existing;
        }
        
        // Ensure jsonld is array in overrides
        if (!is_array($this->overrides['jsonld'])) {
            $this->overrides['jsonld'] = !empty($this->overrides['jsonld']) ? [$this->overrides['jsonld']] : [];
        }
        
        // Add new JSON-LD (handle both single object and array)
        if (isset($jsonLd[0]) && is_array($jsonLd[0])) {
            // Array of objects
            $this->overrides['jsonld'] = array_merge($this->overrides['jsonld'], $jsonLd);
        } else {
            // Single object
            $this->overrides['jsonld'][] = $jsonLd;
        }
        
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
     * Render all meta tags with conflict detection.
     */
    public function render(): string
    {
        $meta = $this->resolve();
        $html = [];
        $this->detectedConflicts = [];

        // Detect existing meta tags in output buffer
        $this->detectConflicts();

        // Title (skip if conflict detected)
        if (!empty($meta['title']) && !$this->hasConflict('title')) {
            $html[] = '<title>' . e($meta['title']) . '</title>';
        }

        // Content Language (http-equiv)
        if (!empty($meta['language'])) {
            $html[] = '<meta http-equiv="content-language" content="' . e($meta['language']) . '">';
        }

        // Basic meta tags
        if (!empty($meta['description']) && !$this->hasConflict('meta', 'description')) {
            $html[] = '<meta name="description" content="' . e($meta['description']) . '">';
        }
        if (!empty($meta['keywords']) && !$this->hasConflict('meta', 'keywords')) {
            $html[] = '<meta name="keywords" content="' . e($meta['keywords']) . '">';
        }
        if (!empty($meta['robots']) && !$this->hasConflict('meta', 'robots')) {
            $html[] = '<meta name="robots" content="' . e($meta['robots']) . '">';
        }
        
        // Author (E-E-A-T signal)
        if (!empty($meta['author']) && !$this->hasConflict('meta', 'author')) {
            $html[] = '<meta name="author" content="' . e($meta['author']) . '">';
        }
        
        // Generator (package branding)
        if (!empty($meta['generator'])) {
            $html[] = '<meta name="generator" content="' . e($meta['generator']) . '">';
        }
        
        // Referrer policy
        if (!empty($meta['referrer'])) {
            $html[] = '<meta name="referrer" content="' . e($meta['referrer']) . '">';
        }
        
        // Theme color (PWA support)
        if (!empty($meta['theme_color'])) {
            $html[] = '<meta name="theme-color" content="' . e($meta['theme_color']) . '">';
        }

        // Canonical (skip if conflict detected)
        if (!empty($meta['canonical_url']) && !$this->hasConflict('canonical')) {
            $html[] = '<link rel="canonical" href="' . e($meta['canonical_url']) . '">';
        }

        // Open Graph
        if (!empty($meta['og_title']) && !$this->hasConflict('og', 'title')) {
            $html[] = '<meta property="og:title" content="' . e($meta['og_title']) . '">';
        }
        if (!empty($meta['og_description']) && !$this->hasConflict('og', 'description')) {
            $html[] = '<meta property="og:description" content="' . e($meta['og_description']) . '">';
        }
        if (!empty($meta['og_image']) && !$this->hasConflict('og', 'image')) {
            $html[] = '<meta property="og:image" content="' . e($meta['og_image']) . '">';
        }
        if (!empty($meta['og_site_name']) && !$this->hasConflict('og', 'site_name')) {
            $html[] = '<meta property="og:site_name" content="' . e($meta['og_site_name']) . '">';
        }
        
        // og:type ve og:url her zaman eklenir (conflict kontrolü yapılmaz)
        $html[] = '<meta property="og:type" content="website">';
        if (!empty($meta['canonical_url']) && !$this->hasConflict('og', 'url')) {
            $html[] = '<meta property="og:url" content="' . e($meta['canonical_url']) . '">';
        }

        // Twitter Card
        if (!empty($meta['twitter_card']) && !$this->hasConflict('twitter', 'card')) {
            $html[] = '<meta name="twitter:card" content="' . e($meta['twitter_card']) . '">';
        }
        if (!empty($meta['twitter_site'])) {
            $siteHandle = str_starts_with($meta['twitter_site'], '@') ? $meta['twitter_site'] : '@' . $meta['twitter_site'];
            $html[] = '<meta name="twitter:site" content="' . e($siteHandle) . '">';
        }
        if (!empty($meta['twitter_creator'])) {
            $creatorHandle = str_starts_with($meta['twitter_creator'], '@') ? $meta['twitter_creator'] : '@' . $meta['twitter_creator'];
            $html[] = '<meta name="twitter:creator" content="' . e($creatorHandle) . '">';
        }
        if (!empty($meta['twitter_title']) && !$this->hasConflict('twitter', 'title')) {
            $html[] = '<meta name="twitter:title" content="' . e($meta['twitter_title']) . '">';
        }
        if (!empty($meta['twitter_description']) && !$this->hasConflict('twitter', 'description')) {
            $html[] = '<meta name="twitter:description" content="' . e($meta['twitter_description']) . '">';
        }
        if (!empty($meta['twitter_image']) && !$this->hasConflict('twitter', 'image')) {
            $html[] = '<meta name="twitter:image" content="' . e($meta['twitter_image']) . '">';
        }

        $output = implode("\n    ", $html);

        // Add conflict warnings in debug mode
        if (config('app.debug', false) && !empty($this->detectedConflicts)) {
            $output .= $this->renderConflictWarnings();
        }

        return $output;
    }

    /**
     * Detect conflicts in existing output buffer.
     * This method checks for duplicate meta tags that might already exist in the page.
     */
    protected function detectConflicts(): void
    {
        // Always check for conflicts, but only warn in debug mode
        // We need to check output buffer to see if tags already exist
        
        // Reset conflicts array
        $this->detectedConflicts = [];
        
        // Start output buffering if not already started
        if (!ob_get_level()) {
            ob_start();
        }

        // Get current output buffer content
        $output = ob_get_contents();
        if (empty($output)) {
            return;
        }

        // Check for existing title tags (before MetaKit renders)
        if (preg_match_all('/<title[^>]*>.*?<\/title>/is', $output, $matches)) {
            $count = count($matches[0]);
            if ($count > 0) {
                $this->detectedConflicts[] = [
                    'type' => 'title',
                    'name' => null,
                    'count' => $count,
                    'message' => "Bu sayfada {$count} adet <title> tagı tespit edildi. MetaKit'in title'ı atlanacak.",
                ];
            }
        }

        // Check for duplicate meta description
        if (preg_match_all('/<meta\s+name=["\']description["\'][^>]*>/is', $output, $matches)) {
            $count = count($matches[0]);
            if ($count > 0) {
                $this->detectedConflicts[] = [
                    'type' => 'meta',
                    'name' => 'description',
                    'count' => $count,
                    'message' => "Bu sayfada {$count} adet meta description tagı tespit edildi. MetaKit'in description'ı atlanacak.",
                ];
            }
        }

        // Check for duplicate canonical
        if (preg_match_all('/<link\s+rel=["\']canonical["\'][^>]*>/is', $output, $matches)) {
            $count = count($matches[0]);
            if ($count > 0) {
                $this->detectedConflicts[] = [
                    'type' => 'canonical',
                    'name' => null,
                    'count' => $count,
                    'message' => "Bu sayfada {$count} adet canonical link tespit edildi. MetaKit'in canonical'ı atlanacak.",
                ];
            }
        }

        // Check for duplicate OG tags
        $ogTags = ['og:title', 'og:description', 'og:image', 'og:url', 'og:site_name'];
        foreach ($ogTags as $ogTag) {
            $property = str_replace('og:', '', $ogTag);
            if (preg_match_all('/<meta\s+property=["\']' . preg_quote($ogTag, '/') . '["\'][^>]*>/is', $output, $matches)) {
                $count = count($matches[0]);
                if ($count > 0) {
                    $this->detectedConflicts[] = [
                        'type' => 'og',
                        'name' => $property,
                        'count' => $count,
                        'message' => "Bu sayfada {$count} adet {$ogTag} tagı tespit edildi. MetaKit'in {$ogTag} tagı atlanacak.",
                    ];
                }
            }
        }

        // Check for duplicate Twitter tags
        $twitterTags = ['twitter:card', 'twitter:title', 'twitter:description', 'twitter:image'];
        foreach ($twitterTags as $twitterTag) {
            $name = str_replace('twitter:', '', $twitterTag);
            if (preg_match_all('/<meta\s+name=["\']' . preg_quote($twitterTag, '/') . '["\'][^>]*>/is', $output, $matches)) {
                $count = count($matches[0]);
                if ($count > 0) {
                    $this->detectedConflicts[] = [
                        'type' => 'twitter',
                        'name' => $name,
                        'count' => $count,
                        'message' => "Bu sayfada {$count} adet {$twitterTag} tagı tespit edildi. MetaKit'in {$twitterTag} tagı atlanacak.",
                    ];
                }
            }
        }
    }

    /**
     * Check if a specific meta tag has conflict.
     */
    protected function hasConflict(string $type, ?string $name = null): bool
    {
        foreach ($this->detectedConflicts as $conflict) {
            if ($conflict['type'] === $type) {
                if ($name === null || ($conflict['name'] ?? null) === $name) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Render conflict warnings as JavaScript console.warn.
     */
    protected function renderConflictWarnings(): string
    {
        if (empty($this->detectedConflicts)) {
            return '';
        }

        $warnings = array_map(function ($conflict) {
            return $conflict['message'];
        }, $this->detectedConflicts);

        $jsonWarnings = json_encode($warnings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return "\n    <script>\n        console.warn('%cMetaKit Conflict Warnings', 'color: #FF9800; font-weight: bold; font-size: 14px;');\n        console.warn(" . $jsonWarnings . ");\n    </script>\n";
    }

    /**
     * Render JSON-LD scripts.
     * Supports multiple JSON-LD objects from jsonld array.
     * Also includes breadcrumb_jsonld for backward compatibility.
     */
    public function renderJsonLd(): string
    {
        $meta = $this->resolve();
        $jsonLd = $meta['jsonld'] ?? [];
        $breadcrumbJsonLd = $meta['breadcrumb_jsonld'] ?? null;

        // Normalize jsonld to array (backward compatibility: handle single object)
        if (!is_array($jsonLd)) {
            $jsonLd = !empty($jsonLd) ? [$jsonLd] : [];
        }

        // Ensure all items are arrays (remove null/empty items)
        $allJsonLd = array_filter($jsonLd, function($item) {
            return !empty($item) && is_array($item);
        });

        // Add breadcrumb JSON-LD if exists (backward compatibility)
        if (!empty($breadcrumbJsonLd)) {
            if (is_array($breadcrumbJsonLd)) {
                // If it's a single object, add it
                if (isset($breadcrumbJsonLd['@type'])) {
                    $allJsonLd[] = $breadcrumbJsonLd;
                } else {
                    // If it's already an array of items, merge it
                    $allJsonLd = array_merge($allJsonLd, array_filter($breadcrumbJsonLd, function($item) {
                        return !empty($item) && is_array($item);
                    }));
                }
            }
        }

        if (empty($allJsonLd)) {
            return '';
        }

        $html = [];
        foreach ($allJsonLd as $item) {
            if (!empty($item) && is_array($item)) {
                $html[] = '<script type="application/ld+json">' . json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
            }
        }

        return implode("\n    ", $html);
    }

    /**
     * Render debug as JavaScript console.log.
     */
    public function renderDebug(): string
    {
        if (!config('app.debug', false)) {
            return '';
        }

        $keys = $this->resolver->resolveFromRequest(request());
        $meta = $this->resolve();

        $debugData = [
            'domain' => $keys['domain'],
            'path' => $keys['path'],
            'query_hash' => $keys['query_hash'] ?? null,
            'cache_key' => $keys['cache_key'],
            'title' => $meta['title'] ?? null,
        ];

        // Add conflict information if any
        if (!empty($this->detectedConflicts)) {
            $debugData['conflicts'] = array_map(function ($conflict) {
                return $conflict['message'];
            }, $this->detectedConflicts);
        }

        $jsonData = json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        return "\n    <script>\n        console.log('%cMetaKit Debug', 'color: #4CAF50; font-weight: bold; font-size: 14px;');\n        console.log(" . $jsonData . ");\n    </script>\n";
    }

    /**
     * Purge cache for a specific page.
     */
    public function purgeCache(string $domain, string $path, ?string $queryHash = null): void
    {
        $cacheKey = $this->resolver->generateCacheKey($domain, $path, $queryHash);
        Cache::forget($cacheKey);
    }

    /**
     * Purge all cache entries for a domain and path pattern.
     * Useful when path changes or multiple query hashes exist.
     */
    public function purgeCacheByPattern(string $domain, string $pathPattern = null): void
    {
        // Note: This requires cache tags or manual tracking
        // For now, we'll just purge the specific key
        // In production, consider using cache tags if your cache driver supports it
        if ($pathPattern) {
            // If using Redis/Memcached with tags, implement tag-based purging
            // For now, this is a placeholder for future enhancement
        }
    }

    /**
     * Clear all MetaKit cache entries.
     * Use with caution in production!
     */
    public function purgeAllCache(): void
    {
        // This would require cache tags or a cache key prefix scan
        // For now, this is a placeholder
        // In production with Redis, you could use: Cache::tags(['metakit'])->flush();
    }
}
 