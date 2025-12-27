<?php

namespace TunaSahincomtr\MetaKit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class MetaKitConflictGuard
{
    protected array $conflicts = [];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if conflict guard is enabled
        $configValue = config('metakit.conflict_guard', []);
        
        // Backward compatibility: handle boolean config (legacy)
        if (is_bool($configValue)) {
            if (!$configValue) {
                return $response; // Disabled
            }
            $config = []; // Use defaults
        } else {
            $config = is_array($configValue) ? $configValue : [];
            if (!($config['enabled'] ?? true)) {
                return $response; // Disabled
            }
        }

        // Only process HTML responses
        if (!$this->isHtmlResponse($response)) {
            return $response;
        }

        // Get response content
        $content = $response->getContent();

        // Skip if content is false (streaming/binary responses)
        if ($content === false || $content === null) {
            return $response;
        }

        // Check response size limit
        $maxSizeKb = $config['max_size_kb'] ?? 512;
        if ($maxSizeKb && (strlen($content) / 1024) > $maxSizeKb) {
            // Response too large - skip processing for performance
            if (config('app.debug', false)) {
                Log::debug('MetaKit ConflictGuard: Skipped large response', [
                    'size_kb' => round(strlen($content) / 1024, 2),
                    'max_size_kb' => $maxSizeKb,
                ]);
            }
            return $response;
        }

        // Parse only head section if enabled (performance optimization)
        $parseHeadOnly = $config['parse_head_only'] ?? true;
        if ($parseHeadOnly) {
            $headContent = $this->extractHeadContent($content);
            if ($headContent === null) {
                // No <head> tag found, skip processing
                return $response;
            }

            // Process only head section
            $processedHead = $this->removeDuplicateTags($headContent);
            
            // Replace original head content
            $content = str_replace($headContent, $processedHead, $content);
        } else {
            // Process full HTML (legacy behavior - not recommended for performance)
            $content = $this->removeDuplicateTags($content);
        }

        // Add conflict warnings if debug mode
        if (config('app.debug', false) && !empty($this->conflicts)) {
            $content = $this->addConflictWarnings($content);
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * Check if response is HTML.
     */
    protected function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        
        // Remove charset and other parameters
        $contentType = preg_split('/[;,\s]/', $contentType)[0];
        $contentType = strtolower(trim($contentType));

        $allowedTypes = config('metakit.conflict_guard.content_types', [
            'text/html',
            'text/html; charset=UTF-8',
            'text/html;charset=UTF-8',
        ]);

        // Normalize allowed types (remove charset parts)
        $normalizedAllowed = array_map(function ($type) {
            return strtolower(trim(preg_split('/[;,\s]/', $type)[0]));
        }, $allowedTypes);

        return in_array($contentType, $normalizedAllowed);
    }

    /**
     * Extract head content from HTML (performance optimization).
     */
    protected function extractHeadContent(string $html): ?string
    {
        // Find <head> tag (case-insensitive, handle attributes)
        if (!preg_match('/<head[^>]*>/i', $html, $headStartMatches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $headStartPos = $headStartMatches[0][1];
        $headStartTag = $headStartMatches[0][0];

        // Find </head> tag (case-insensitive)
        $headContentStart = $headStartPos + strlen($headStartTag);
        
        if (!preg_match('/<\/head>/i', $html, $headEndMatches, PREG_OFFSET_CAPTURE, $headContentStart)) {
            return null;
        }

        $headEndPos = $headEndMatches[0][1];
        $headContent = substr($html, $headStartPos, ($headEndPos + 7) - $headStartPos); // 7 = length of </head>

        return $headContent;
    }

    /**
     * Remove duplicate meta tags from content.
     */
    protected function removeDuplicateTags(string $content): string
    {
        $this->conflicts = [];

        // Track seen tags
        $seenTags = [
            'title' => false,
            'description' => false,
            'canonical' => false,
            'og:title' => false,
            'og:description' => false,
            'og:image' => false,
            'og:site_name' => false,
            'twitter:card' => false,
            'twitter:title' => false,
            'twitter:description' => false,
            'twitter:image' => false,
            'twitter:site' => false,
            'twitter:creator' => false,
            'author' => false,
            'generator' => false,
            'referrer' => false,
            'theme-color' => false,
        ];

        // Remove duplicate <title> tags (keep first)
        $content = preg_replace_callback(
            '/<title[^>]*>.*?<\/title>/is',
            function ($matches) use (&$seenTags) {
                if (!$seenTags['title']) {
                    $seenTags['title'] = true;
                    return $matches[0];
                }
                $this->conflicts[] = 'Duplicate <title> tag removed';
                return '';
            },
            $content
        );

        // Remove duplicate meta description tags (keep first)
        $content = preg_replace_callback(
            '/<meta\s+name=["\']description["\'][^>]*>/i',
            function ($matches) use (&$seenTags) {
                if (!$seenTags['description']) {
                    $seenTags['description'] = true;
                    return $matches[0];
                }
                $this->conflicts[] = 'Duplicate meta description tag removed';
                return '';
            },
            $content
        );

        // Remove duplicate canonical links (keep first)
        $content = preg_replace_callback(
            '/<link\s+rel=["\']canonical["\'][^>]*>/i',
            function ($matches) use (&$seenTags) {
                if (!$seenTags['canonical']) {
                    $seenTags['canonical'] = true;
                    return $matches[0];
                }
                $this->conflicts[] = 'Duplicate canonical link removed';
                return '';
            },
            $content
        );

        // Remove duplicate Open Graph tags
        $ogTags = [
            'og:title' => '/<meta\s+property=["\']og:title["\'][^>]*>/i',
            'og:description' => '/<meta\s+property=["\']og:description["\'][^>]*>/i',
            'og:image' => '/<meta\s+property=["\']og:image["\'][^>]*>/i',
            'og:site_name' => '/<meta\s+property=["\']og:site_name["\'][^>]*>/i',
        ];

        foreach ($ogTags as $tag => $pattern) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) use (&$seenTags, $tag) {
                    if (!$seenTags[$tag]) {
                        $seenTags[$tag] = true;
                        return $matches[0];
                    }
                    $this->conflicts[] = "Duplicate {$tag} tag removed";
                    return '';
                },
                $content
            );
        }

        // Remove duplicate Twitter Card tags
        $twitterTags = [
            'twitter:card' => '/<meta\s+name=["\']twitter:card["\'][^>]*>/i',
            'twitter:title' => '/<meta\s+name=["\']twitter:title["\'][^>]*>/i',
            'twitter:description' => '/<meta\s+name=["\']twitter:description["\'][^>]*>/i',
            'twitter:image' => '/<meta\s+name=["\']twitter:image["\'][^>]*>/i',
            'twitter:site' => '/<meta\s+name=["\']twitter:site["\'][^>]*>/i',
            'twitter:creator' => '/<meta\s+name=["\']twitter:creator["\'][^>]*>/i',
        ];

        foreach ($twitterTags as $tag => $pattern) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) use (&$seenTags, $tag) {
                    if (!$seenTags[$tag]) {
                        $seenTags[$tag] = true;
                        return $matches[0];
                    }
                    $this->conflicts[] = "Duplicate {$tag} tag removed";
                    return '';
                },
                $content
            );
        }

        // Remove duplicate other meta tags
        $otherTags = [
            'author' => '/<meta\s+name=["\']author["\'][^>]*>/i',
            'generator' => '/<meta\s+name=["\']generator["\'][^>]*>/i',
            'referrer' => '/<meta\s+name=["\']referrer["\'][^>]*>/i',
            'theme-color' => '/<meta\s+name=["\']theme-color["\'][^>]*>/i',
        ];

        foreach ($otherTags as $tag => $pattern) {
            $content = preg_replace_callback(
                $pattern,
                function ($matches) use (&$seenTags, $tag) {
                    if (!$seenTags[$tag]) {
                        $seenTags[$tag] = true;
                        return $matches[0];
                    }
                    $this->conflicts[] = "Duplicate {$tag} tag removed";
                    return '';
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Add conflict warnings to HTML (debug mode only).
     */
    protected function addConflictWarnings(string $content): string
    {
        if (empty($this->conflicts)) {
            return $content;
        }

        $warnings = array_unique($this->conflicts);
        $warningsJson = json_encode($warnings, JSON_UNESCAPED_UNICODE);

        // Insert console.warn before </head>
        $script = "<script>if(console&&console.warn){console.warn('MetaKit ConflictGuard: Duplicate meta tags detected', {$warningsJson});}</script>";

        // Try to insert before </head>
        if (stripos($content, '</head>') !== false) {
            $content = preg_replace('/<\/head>/i', $script . '</head>', $content, 1);
        } else {
            // Fallback: insert at the beginning of body or at the end
            if (stripos($content, '<body') !== false) {
                $content = preg_replace('/(<body[^>]*>)/i', '$1' . $script, $content, 1);
            } else {
                $content = $script . $content;
            }
        }

        return $content;
    }
}
