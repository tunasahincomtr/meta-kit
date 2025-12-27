<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    */
    'ui' => [
        /*
        | Primary color for Bootstrap components
        | This will be used for buttons, links, and other UI elements
        | Must be a valid Bootstrap color (primary, secondary, success, danger, warning, info, light, dark)
        | Or a custom hex color (e.g., #0d6efd)
        */
        'primary_color' => trim(env('METAKIT_UI_PRIMARY_COLOR', 'primary'), '"\' '),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api_prefix' => 'api/metakit',
    
    /*
    | Public API Middleware (GET işlemleri - Okuma)
    | GET /pages ve GET /pages/{id} bu middleware ile çalışır
    */
    'api_public_middleware' => ['api'],
    
    /*
    | Protected API Middleware (POST/PUT/DELETE işlemleri - Yazma)
    | POST, PUT, DELETE işlemleri bu middleware ile korunur (auth gerekli)
    */
    'api_auth_middleware' => ['api', 'auth:sanctum'],
    
    /*
    |--------------------------------------------------------------------------
    | Form Component Authentication
    |--------------------------------------------------------------------------
    | @metakitform directive için auth kontrolü ayarları
    */
    'form' => [
        /*
        | Auth kontrolü aktif mi?
        | true: Sadece giriş yapmış kullanıcılar formu görebilir
        | false: Herkes formu görebilir (auth kontrolü yapılmaz)
        */
        'auth_required' => env('METAKIT_FORM_AUTH_REQUIRED', true),
        
        /*
        | Auth guard (Laravel auth guard)
        | Varsayılan: web (session based auth)
        | Diğer seçenekler: sanctum, api, vs.
        */
        'auth_guard' => env('METAKIT_FORM_AUTH_GUARD', 'web'),
        
        /*
        | Auth kontrolü başarısız olduğunda gösterilecek mesaj
        | null ise varsayılan mesaj gösterilir
        */
        'auth_denied_message' => env('METAKIT_FORM_AUTH_DENIED_MESSAGE', null),
        
        /*
        | Auth kontrolü başarısız olduğunda yönlendirilecek route
        | null ise sadece mesaj gösterilir, yönlendirme yapılmaz
        | Örnek: 'login', 'auth.login', '/login'
        */
        'auth_redirect_route' => env('METAKIT_FORM_AUTH_REDIRECT_ROUTE', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Query Whitelist
    |--------------------------------------------------------------------------
    | Query parameters that will be included in the query_hash calculation.
    | Parameters not in this list will be ignored.
    */
    'query_whitelist' => [
        'city',
        'district',
        'uni',
        'gender',
        'price_min',
        'price_max',
        'type',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache_ttl_minutes' => 360,

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */
    'default' => [
        'site_name' => env('APP_NAME', 'Laravel'),
        'title_suffix' => ' - ' . env('APP_NAME', 'Laravel'),
        'default_image' => env('METAKIT_DEFAULT_IMAGE', '/images/og-default.jpg'),
        'default_robots' => 'index, follow',
        
        // Language support (http-equiv="content-language")
        'language' => env('METAKIT_DEFAULT_LANGUAGE', env('APP_LOCALE', 'tr')),
        
        // OG Site Name
        'og_site_name' => env('METAKIT_OG_SITE_NAME', env('APP_NAME', 'Laravel')),
        
        // Twitter handles (@ ile başlamalı veya sadece handle)
        'twitter_site' => env('METAKIT_TWITTER_SITE', null),
        'twitter_creator' => env('METAKIT_TWITTER_CREATOR', null),
        
        // Author (E-E-A-T signal)
        'author' => env('METAKIT_AUTHOR', null),
        
        // Referrer policy
        'referrer' => env('METAKIT_REFERRER', 'strict-origin-when-cross-origin'),
        
        // Theme color (hex color, with #)
        'theme_color' => env('METAKIT_THEME_COLOR', '#0d6efd'),
        
        // Generator (package branding)
        'generator' => 'MetaKit for Laravel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Comments
    |--------------------------------------------------------------------------
    | When enabled, adds HTML comments with debug information.
    | Uses APP_DEBUG from .env file. Set to false to disable even when APP_DEBUG is true.
    */
    'debug_comments' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Alias Redirect
    |--------------------------------------------------------------------------
    | Enable automatic redirects for URL aliases.
    | When enabled, old URLs will be automatically redirected to new URLs (301 permanent redirect).
    */
    'alias_redirect' => env('METAKIT_ALIAS_REDIRECT', true),

    /*
    |--------------------------------------------------------------------------
    | Duplicate Validation
    |--------------------------------------------------------------------------
    | Strict duplicate validation for title and description.
    | When enabled, prevents creating pages with duplicate titles or descriptions (same domain).
    | When disabled, allows duplicates but returns warnings in API response.
    */
    'duplicate_validation_strict' => env('METAKIT_DUPLICATE_VALIDATION_STRICT', false),

    /*
    |--------------------------------------------------------------------------
    | Conflict Guard Performance Settings
    |--------------------------------------------------------------------------
    | Performance optimizations for Conflict Guard middleware.
    | 
    | NOTE: For backward compatibility, if this is set to a boolean, it will be treated as 'enabled'.
    | For full control, use the array format below.
    */
    'conflict_guard' => [
        // Enable/disable conflict guard
        'enabled' => env('METAKIT_CONFLICT_GUARD', true),

        // Maximum response size to process (in KB). Larger responses will be skipped.
        // Set to null to disable size limit.
        'max_size_kb' => env('METAKIT_CONFLICT_GUARD_MAX_SIZE', 512), // 512 KB default

        // Parse only up to </head> tag to improve performance (recommended: true)
        // When true, only processes content between <head>...</head> tags
        'parse_head_only' => env('METAKIT_CONFLICT_GUARD_HEAD_ONLY', true),

        // Content types to process (only HTML responses are processed)
        'content_types' => [
            'text/html',
            'text/html; charset=UTF-8',
            'text/html;charset=UTF-8',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Indexing Policy (SEO Strategy)
    |--------------------------------------------------------------------------
    | Controls which query parameter combinations should be indexed by search engines.
    | Non-indexable pages will have canonical pointing to base path + robots "noindex, follow".
    */
    'indexing_policy' => [
        // Strategy: 'allowlist' or 'denylist'
        // 'allowlist': Only listed combinations are indexable (default: strict)
        // 'denylist': Listed combinations are NOT indexable (default: permissive)
        'strategy' => env('METAKIT_INDEXING_STRATEGY', 'denylist'),

        // Parameter combinations that should/shouldn't be indexed
        // Format: ['param1=value1', 'param2=value2'] or ['param1'] for any value
        'allowlist' => [
            // Example: Only allow specific filter combinations to be indexed
            // ['city=istanbul'], // Only istanbul city pages are indexable
            // ['city', 'type'], // Pages with city AND type params are indexable
        ],

        'denylist' => [
            // Example: Deny indexing for specific combinations
            // ['page'], // Pagination pages (page=2, page=3, etc.) - always non-indexable
            // ['sort'], // Sort-only pages (no filters) - non-indexable
            // ['city', 'district'], // Too specific filter combinations - non-indexable
        ],

        // Pagination handling
        'pagination' => [
            // Pagination parameter name (usually 'page')
            'param' => env('METAKIT_PAGINATION_PARAM', 'page'),

            // Should pagination pages be indexable? (default: false)
            'indexable' => env('METAKIT_PAGINATION_INDEXABLE', false),

            // Canonical strategy for pagination:
            // 'base' - Always point to page 1 (or base path if no page param)
            // 'self' - Point to current page (if indexable)
            'canonical_strategy' => env('METAKIT_PAGINATION_CANONICAL', 'base'),
        ],

        // Maximum number of query parameters allowed for indexing
        // Pages with more than this many params will be non-indexable
        'max_params' => env('METAKIT_MAX_INDEXABLE_PARAMS', null),

        // Parameters that, when present alone, make page non-indexable
        // (e.g., ['sort', 'order'] - pages with only sort/order should not be indexed)
        'alone_non_indexable' => ['sort', 'order'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap Settings
    |--------------------------------------------------------------------------
    | Sitemap.xml generation settings.
    */
    'sitemap' => [
        // Enable sitemap generation
        'enabled' => env('METAKIT_SITEMAP_ENABLED', true),

        // Sitemap route path (e.g., '/sitemap.xml')
        'route' => env('METAKIT_SITEMAP_ROUTE', '/sitemap.xml'),

        // Include image sitemap (uses og_image from pages)
        'include_images' => env('METAKIT_SITEMAP_IMAGES', true),

        // Maximum URLs per sitemap file (for sitemap index)
        'max_urls_per_file' => env('METAKIT_SITEMAP_MAX_URLS', 50000),

        // Default change frequency
        'changefreq' => env('METAKIT_SITEMAP_CHANGEFREQ', 'weekly'),

        // Default priority
        'priority' => env('METAKIT_SITEMAP_PRIORITY', '0.8'),

        // Only include pages with status 'active'
        'only_active' => env('METAKIT_SITEMAP_ONLY_ACTIVE', true),
    ],
];
