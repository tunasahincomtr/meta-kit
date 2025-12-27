<?php

use Illuminate\Support\Facades\Route;
use TunaSahincomtr\MetaKit\Http\Controllers\SitemapController;

/*
|--------------------------------------------------------------------------
| MetaKit Web Routes
|--------------------------------------------------------------------------
|
| Web routes for MetaKit package (e.g., sitemap.xml).
|
*/

// Sitemap route
if (config('metakit.sitemap.enabled', true)) {
    $sitemapRoute = config('metakit.sitemap.route', '/sitemap.xml');
    // Remove leading slash for route definition
    $sitemapRoute = ltrim($sitemapRoute, '/');
    
    Route::get($sitemapRoute, [SitemapController::class, 'index'])
        ->name('metakit.sitemap');
}
