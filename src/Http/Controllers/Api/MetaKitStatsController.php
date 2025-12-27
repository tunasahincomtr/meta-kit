<?php

namespace TunaSahincomtr\MetaKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use TunaSahincomtr\MetaKit\Models\MetaKitPage;

class MetaKitStatsController extends Controller
{
    /**
     * Get dashboard statistics.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $domain = $request->get('domain');

        $query = MetaKitPage::query();
        if ($domain) {
            $query->where('domain', $domain);
        }

        // Total count
        $total = (clone $query)->count();

        // Status breakdown
        $statusBreakdown = (clone $query)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $active = $statusBreakdown['active'] ?? 0;
        $draft = $statusBreakdown['draft'] ?? 0;

        // Domain breakdown
        $domainBreakdown = (clone $query)
            ->select('domain', DB::raw('count(*) as count'))
            ->groupBy('domain')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->pluck('count', 'domain')
            ->toArray();

        // Recent updates (last 7 days)
        $recentUpdates = (clone $query)
            ->where('updated_at', '>=', now()->subDays(7))
            ->count();

        // Missing meta statistics
        $missingMeta = $this->getMissingMetaStats($query);

        // Duplicate statistics
        $duplicates = $this->getDuplicateStats($query);

        return response()->json([
            'summary' => [
                'total_pages' => $total,
                'active_pages' => $active,
                'draft_pages' => $draft,
                'active_percentage' => $total > 0 ? round(($active / $total) * 100, 2) : 0,
                'draft_percentage' => $total > 0 ? round(($draft / $total) * 100, 2) : 0,
                'recent_updates' => $recentUpdates,
            ],
            'domain_breakdown' => $domainBreakdown,
            'missing_meta' => $missingMeta,
            'duplicates' => $duplicates,
        ]);
    }

    /**
     * Get missing meta statistics.
     */
    public function missingMeta(Request $request): JsonResponse
    {
        $domain = $request->get('domain');
        $query = MetaKitPage::query();
        
        if ($domain) {
            $query->where('domain', $domain);
        }

        $stats = $this->getMissingMetaStats($query, true);

        return response()->json($stats);
    }

    /**
     * Get duplicate statistics.
     */
    public function duplicates(Request $request): JsonResponse
    {
        $domain = $request->get('domain');
        $type = $request->get('type', 'title'); // 'title' or 'description'
        
        $query = MetaKitPage::query();
        if ($domain) {
            $query->where('domain', $domain);
        }

        $stats = $this->getDuplicateStats($query, $type, true);

        return response()->json($stats);
    }

    /**
     * Get missing meta statistics.
     */
    protected function getMissingMetaStats($query, bool $detailed = false): array
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'missing_title' => 0,
                'missing_description' => 0,
                'missing_canonical' => 0,
                'missing_og_image' => 0,
                'missing_jsonld' => 0,
                'missing_breadcrumb' => 0,
                'missing_percentage' => [],
                'details' => [],
            ];
        }

        $missingTitle = (clone $query)->where(function($q) {
            $q->whereNull('title')->orWhere('title', '');
        })->count();
        $missingDescription = (clone $query)->where(function($q) {
            $q->whereNull('description')->orWhere('description', '');
        })->count();
        $missingCanonical = (clone $query)->where(function($q) {
            $q->whereNull('canonical_url')->orWhere('canonical_url', '');
        })->count();
        $missingOgImage = (clone $query)->where(function($q) {
            $q->whereNull('og_image')->orWhere('og_image', '');
        })->count();
        $missingJsonLd = (clone $query)->where(function($q) {
            $q->whereNull('jsonld')
              ->orWhere('jsonld', '[]')
              ->orWhere('jsonld', '');
        })->count();
        $missingBreadcrumb = (clone $query)->where(function($q) {
            $q->whereNull('breadcrumb_jsonld')
              ->orWhere('breadcrumb_jsonld', '[]')
              ->orWhere('breadcrumb_jsonld', '');
        })->count();

        $stats = [
            'total' => $total,
            'missing_title' => $missingTitle,
            'missing_description' => $missingDescription,
            'missing_canonical' => $missingCanonical,
            'missing_og_image' => $missingOgImage,
            'missing_jsonld' => $missingJsonLd,
            'missing_breadcrumb' => $missingBreadcrumb,
            'missing_percentage' => [
                'title' => round(($missingTitle / $total) * 100, 2),
                'description' => round(($missingDescription / $total) * 100, 2),
                'canonical' => round(($missingCanonical / $total) * 100, 2),
                'og_image' => round(($missingOgImage / $total) * 100, 2),
                'jsonld' => round(($missingJsonLd / $total) * 100, 2),
                'breadcrumb' => round(($missingBreadcrumb / $total) * 100, 2),
            ],
        ];

        if ($detailed) {
            $stats['details'] = [
                'missing_title_pages' => (clone $query)
                    ->where(function($q) {
                        $q->whereNull('title')->orWhere('title', '');
                    })
                    ->select('id', 'domain', 'path', 'title', 'query_hash')
                    ->limit(50)
                    ->get()
                    ->map(fn($page) => [
                        'id' => $page->id,
                        'domain' => $page->domain,
                        'path' => $page->path,
                        'page_key' => $page->domain . '|' . $page->path . '|' . ($page->query_hash ?? ''),
                    ]),
                'missing_description_pages' => (clone $query)
                    ->where(function($q) {
                        $q->whereNull('description')->orWhere('description', '');
                    })
                    ->select('id', 'domain', 'path', 'description', 'query_hash')
                    ->limit(50)
                    ->get()
                    ->map(fn($page) => [
                        'id' => $page->id,
                        'domain' => $page->domain,
                        'path' => $page->path,
                        'page_key' => $page->domain . '|' . $page->path . '|' . ($page->query_hash ?? ''),
                    ]),
                'missing_og_image_pages' => (clone $query)
                    ->where(function($q) {
                        $q->whereNull('og_image')->orWhere('og_image', '');
                    })
                    ->select('id', 'domain', 'path', 'og_image', 'query_hash')
                    ->limit(50)
                    ->get()
                    ->map(fn($page) => [
                        'id' => $page->id,
                        'domain' => $page->domain,
                        'path' => $page->path,
                        'page_key' => $page->domain . '|' . $page->path . '|' . ($page->query_hash ?? ''),
                    ]),
            ];
        }

        return $stats;
    }

    /**
     * Get duplicate statistics.
     */
    protected function getDuplicateStats($query, string $type = 'title', bool $detailed = false): array
    {
        $total = (clone $query)->count();

        if ($total === 0) {
            return [
                'total' => 0,
                'duplicate_count' => 0,
                'unique_count' => 0,
                'duplicate_percentage' => 0,
                'duplicate_groups' => [],
                'details' => [],
            ];
        }

        $column = $type === 'description' ? 'description' : 'title';

        // Find duplicates using GROUP BY and HAVING
        $duplicateGroups = (clone $query)
            ->select($column, DB::raw('count(*) as count'))
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->groupBy($column)
            ->having('count', '>', 1)
            ->get();

        $duplicateCount = $duplicateGroups->sum('count');
        $uniqueCount = $total - $duplicateCount;

        $stats = [
            'type' => $type,
            'total' => $total,
            'duplicate_count' => $duplicateCount,
            'unique_count' => $uniqueCount,
            'duplicate_percentage' => $total > 0 ? round(($duplicateCount / $total) * 100, 2) : 0,
            'duplicate_groups' => $duplicateGroups->map(fn($group) => [
                'value' => $group->{$column},
                'count' => $group->count,
                'preview' => mb_substr($group->{$column}, 0, 100) . (mb_strlen($group->{$column}) > 100 ? '...' : ''),
            ])->values(),
        ];

        if ($detailed) {
            // Get detailed list of duplicate pages
            $duplicateValues = $duplicateGroups->pluck($column)->toArray();
            
            if (!empty($duplicateValues)) {
                $duplicatePages = (clone $query)
                    ->whereIn($column, $duplicateValues)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->select('id', 'domain', 'path', 'query_hash', $column)
                    ->orderBy($column)
                    ->orderBy('domain')
                    ->get()
                    ->groupBy($column)
                    ->map(function ($pages) {
                        return $pages->map(fn($page) => [
                            'id' => $page->id,
                            'domain' => $page->domain,
                            'path' => $page->path,
                            'query_hash' => $page->query_hash,
                            'page_key' => $page->domain . '|' . $page->path . '|' . ($page->query_hash ?? ''),
                        ]);
                    });

                $stats['details'] = $duplicatePages;
            } else {
                $stats['details'] = [];
            }
        }

        return $stats;
    }
}

