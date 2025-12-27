<?php

namespace TunaSahincomtr\MetaKit\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use TunaSahincomtr\MetaKit\Models\MetaKitPage;

class SitemapController extends Controller
{
    /**
     * Generate sitemap.xml.
     */
    public function index(): Response
    {
        $config = config('metakit.sitemap', []);

        // Check if sitemap is enabled
        if (!($config['enabled'] ?? true)) {
            abort(404);
        }

        // Cache sitemap for 1 hour
        $cacheKey = 'metakit_sitemap';
        $cacheTtl = now()->addHour();

        $sitemap = Cache::remember($cacheKey, $cacheTtl, function () use ($config) {
            return $this->generateSitemap($config);
        });

        return response($sitemap, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Generate sitemap XML.
     */
    protected function generateSitemap(array $config): string
    {
        // Get active pages only if configured
        $query = MetaKitPage::query();
        
        if ($config['only_active'] ?? true) {
            $query->where('status', 'active');
        }

        // Order by updated_at
        $pages = $query->orderBy('updated_at', 'desc')->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        
        // Add image namespace if images are included
        if ($config[' include_images'] ?? true) { $xml
    .=' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"' ; } $xml .='>' . "\n" ; foreach ($pages as $page)
    { $xml .=$this->generateUrlEntry($page, $config);
    }

    $xml .= '</urlset>';

return $xml;
}

/**
* Generate URL entry for a page.
*/
protected function generateUrlEntry(MetaKitPage $page, array $config): string
{
$xml = " <url>\n";

    // Build loc (location URL)
    $scheme = config('app.url') ? parse_url(config('app.url'), PHP_URL_SCHEME) : 'https';
    $loc = $scheme . '://' . $page->domain . $page->path;

    // Add query string if query_hash exists (reconstruct from query_hash if possible)
    // Note: We can't reconstruct exact query string from hash, so we skip query params
    // If you need query params in sitemap, consider storing normalized query string in database

    $xml .= ' <loc>' . htmlspecialchars($loc, ENT_XML1, 'UTF-8') . '</loc>' . "\n";

    // Add lastmod (last modified)
    if ($page->updated_at) {
    $lastmod = $page->updated_at->format('c'); // ISO 8601 format
    $xml .= ' <lastmod>' . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . '</lastmod>' . "\n";
    }

    // Add changefreq
    $changefreq = $config['changefreq'] ?? 'weekly';
    $xml .= ' <changefreq>' . htmlspecialchars($changefreq, ENT_XML1, 'UTF-8') . '</changefreq>' . "\n";

    // Add priority
    $priority = $config['priority'] ?? '0.8';
    $xml .= ' <priority>' . htmlspecialchars($priority, ENT_XML1, 'UTF-8') . '</priority>' . "\n";

    // Add image if configured and available
    if (($config['include_images'] ?? true) && !empty($page->og_image)) {
    // Extract scheme from loc URL
    $scheme = parse_url($loc, PHP_URL_SCHEME) ?: 'https';
    $xml .= $this->generateImageEntry($page, $scheme);
    }

    $xml .= " </url>\n";

return $xml;
}

/**
* Generate image entry for sitemap.
*/
protected function generateImageEntry(MetaKitPage $page, string $scheme): string
{
$xml = ' <image:image>' . "\n";

    // Build image URL
    $imageUrl = $page->og_image;

    // If image URL is relative, make it absolute
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    $baseUrl = rtrim(config('app.url') ?: ($scheme . '://' . $page->domain), '/');
    $imageUrl = $baseUrl . '/' . ltrim($imageUrl, '/');
    }

    $xml .= ' <image:loc>' . htmlspecialchars($imageUrl, ENT_XML1, 'UTF-8') . '</image:loc>' . "\n";

    // Add image title if available
    if (!empty($page->og_title)) {
    $xml .= ' <image:title>' . htmlspecialchars($page->og_title, ENT_XML1, 'UTF-8') . '</image:title>' . "\n";
    } elseif (!empty($page->title)) {
    $xml .= ' <image:title>' . htmlspecialchars($page->title, ENT_XML1, 'UTF-8') . '</image:title>' . "\n";
    }

    // Add image caption if available
    if (!empty($page->og_description)) {
    $xml .= ' <image:caption>' . htmlspecialchars($page->og_description, ENT_XML1, 'UTF-8') . '</image:caption>' . "\n";
    } elseif (!empty($page->description)) {
    // Truncate description to reasonable length for caption
    $caption = mb_substr(strip_tags($page->description), 0, 200);
    $xml .= ' <image:caption>' . htmlspecialchars($caption, ENT_XML1, 'UTF-8') . '</image:caption>' . "\n";
    }

    $xml .= ' </image:image>' . "\n";

return $xml;
}

/**
* Purge sitemap cache.
* Call this method when pages are updated/deleted to regenerate sitemap.
*/
public static function purgeCache(): void
{
Cache::forget('metakit_sitemap');
}
}
