<?php

namespace TunaSahincomtr\MetaKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;
use TunaSahincomtr\MetaKit\Http\Requests\StoreMetaKitPageRequest;
use TunaSahincomtr\MetaKit\Http\Requests\UpdateMetaKitPageRequest;
use TunaSahincomtr\MetaKit\Http\Resources\MetaKitPageResource;
use TunaSahincomtr\MetaKit\Models\MetaKitPage;
use TunaSahincomtr\MetaKit\Services\MetaKitManager;
use TunaSahincomtr\MetaKit\Services\UrlKeyResolver;

class MetaKitPageController extends Controller
{
    protected MetaKitManager $manager;
    protected UrlKeyResolver $resolver;

    public function __construct()
    {
        $this->manager = app(MetaKitManager::class);
        $this->resolver = new UrlKeyResolver();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MetaKitPage::query();

        // Search
        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->has('domain') && !empty($request->domain)) {
            $query->where('domain', $request->domain);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('path') && !empty($request->path)) {
            $query->where('path', 'like', $request->path . '%');
        }

        if ($request->has('updated_from') && !empty($request->updated_from)) {
            $query->where('updated_at', '>=', $request->updated_from);
        }

        if ($request->has('updated_to') && !empty($request->updated_to)) {
            $query->where('updated_at', '<=', $request->updated_to);
        }

        // Order
        $query->orderBy('updated_at', 'desc');

        // Pagination
        $perPage = min((int) ($request->get('per_page', 20)), 100);
        $pages = $query->paginate($perPage);

        // Include SEO score if requested (default: false for list to improve performance)
        $request->merge(['include_seo_score' => $request->boolean('include_seo_score', false)]);

        return MetaKitPageResource::collection($pages)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMetaKitPageRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Check for duplicate title/description
        $duplicateWarnings = $this->checkDuplicates($data, null);
        
        // If strict mode is enabled and duplicates found, return error
        if (config('metakit.duplicate_validation_strict', false)) {
            if (!empty($duplicateWarnings['title_duplicates']) || !empty($duplicateWarnings['description_duplicates'])) {
                return response()->json([
                    'message' => 'Duplicate title or description detected',
                    'errors' => $duplicateWarnings,
                ], 422);
            }
        }
        
        // Set user IDs safely
        $userId = auth()->id();
        if ($userId) {
            $data['created_by'] = $userId;
            $data['updated_by'] = $userId;
        }

        $page = MetaKitPage::create($data);

        // Purge cache (silently fail if cache is unavailable)
        try {
            $this->manager->purgeCache(
                $page->domain,
                $page->path,
                $page->query_hash
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to purge cache after page creation', [
                'error' => $e->getMessage(),
                'page_id' => $page->id,
            ]);
        }

        $response = (new MetaKitPageResource($page))
            ->response()
            ->setStatusCode(201);
        
        // Add warnings to response if duplicates found (non-strict mode)
        if (!empty($duplicateWarnings['title_duplicates']) || !empty($duplicateWarnings['description_duplicates'])) {
            $response->header('X-Metakit-Warnings', json_encode($duplicateWarnings));
            $responseData = $response->getData(true);
            $responseData['warnings'] = $duplicateWarnings;
            return response()->json($responseData, 201);
        }

        return $response;
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, MetaKitPage $page): JsonResponse
    {
        // Include SEO score if requested
        $request->merge(['include_seo_score' => $request->boolean('include_seo_score', true)]);
        
        return (new MetaKitPageResource($page))->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMetaKitPageRequest $request, MetaKitPage $page): JsonResponse
    {
        $data = $request->validated();
        
        // Merge with existing data for duplicate check
        $mergedData = array_merge([
            'domain' => $page->domain,
            'title' => $page->title,
            'description' => $page->description,
        ], $data);
        
        // Check for duplicate title/description (excluding current page)
        $duplicateWarnings = $this->checkDuplicates($mergedData, $page->id);
        
        // If strict mode is enabled and duplicates found, return error
        if (config('metakit.duplicate_validation_strict', false)) {
            if (!empty($duplicateWarnings['title_duplicates']) || !empty($duplicateWarnings['description_duplicates'])) {
                return response()->json([
                    'message' => 'Duplicate title or description detected',
                    'errors' => $duplicateWarnings,
                ], 422);
            }
        }
        
        // Set user ID safely
        $userId = auth()->id();
        if ($userId) {
            $data['updated_by'] = $userId;
        }

        $oldDomain = $page->domain;
        $oldPath = $page->path;
        $oldQueryHash = $page->query_hash;

        $page->update($data);

        // Purge old and new cache (silently fail if cache is unavailable)
        try {
            $this->manager->purgeCache($oldDomain, $oldPath, $oldQueryHash);
            $this->manager->purgeCache(
                $page->domain,
                $page->path,
                $page->query_hash
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to purge cache after page update', [
                'error' => $e->getMessage(),
                'page_id' => $page->id,
            ]);
        }

        $response = (new MetaKitPageResource($page))->response();
        
        // Add warnings to response if duplicates found (non-strict mode)
        if (!empty($duplicateWarnings['title_duplicates']) || !empty($duplicateWarnings['description_duplicates'])) {
            $response->header('X-Metakit-Warnings', json_encode($duplicateWarnings));
            $responseData = $response->getData(true);
            $responseData['warnings'] = $duplicateWarnings;
            return response()->json($responseData, 200);
        }

        return $response;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MetaKitPage $page): JsonResponse
    {
        $domain = $page->domain;
        $path = $page->path;
        $queryHash = $page->query_hash;

        $page->delete();

        // Purge cache (silently fail if cache is unavailable)
        try {
            $this->manager->purgeCache($domain, $path, $queryHash);
        } catch (\Exception $e) {
            \Log::warning('Failed to purge cache after page deletion', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'path' => $path,
            ]);
        }

        return response()->json(['message' => 'Page deleted successfully'], 200);
    }

    /**
     * Quick create from URL.
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $request->validate([
            'url' => ['nullable', 'url'],
            'status' => ['nullable', 'in:draft,active'],
        ]);

        $url = $request->input('url') ?? $request->header('referer');
        
        if (!$url) {
            return response()->json(['error' => 'URL or referer header required'], 400);
        }

        $parsed = parse_url($url);
        
        if ($parsed === false) {
            return response()->json(['error' => 'Invalid URL format'], 400);
        }

        $domain = $parsed['host'] ?? '';
        if (empty($domain)) {
            return response()->json(['error' => 'Could not extract domain from URL'], 400);
        }

        $path = '/' . ltrim($parsed['path'] ?? '/', '/');
        
        // Build query hash from query string
        $queryHash = null;
        if (!empty($parsed['query'])) {
            parse_str($parsed['query'], $queryParams);
            $mockRequest = Request::create($url, 'GET', $queryParams);
            $mockRequest->headers->set('Host', $domain);
            $queryHash = $this->resolver->resolveQueryHash($mockRequest);
        }

        // Check if exists
        $page = MetaKitPage::where('domain', $domain)
            ->where('path', $path)
            ->where('query_hash', $queryHash)
            ->first();

        if ($page) {
            return (new MetaKitPageResource($page))->response();
        }

        // Create new
        $status = $request->input('status', 'draft');
        $createData = [
            'domain' => $domain,
            'path' => $path,
            'query_hash' => $queryHash,
            'status' => $status,
        ];
        
        // Set user IDs safely
        $userId = auth()->id();
        if ($userId) {
            $createData['created_by'] = $userId;
            $createData['updated_by'] = $userId;
        }
        
        $page = MetaKitPage::create($createData);

        // Purge cache for the new page
        $this->manager->purgeCache($domain, $path, $queryHash);

        return (new MetaKitPageResource($page))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Export pages as CSV.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = MetaKitPage::query();

        // Apply filters if provided
        if ($request->has('domain') && !empty($request->domain)) {
            $query->where('domain', $request->domain);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $filename = 'metakit-pages-' . date('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV headers
            fputcsv($handle, [
                'ID',
                'Domain',
                'Path',
                'Query Hash',
                'Title',
                'Description',
                'Keywords',
                'Robots',
                'Language',
                'Canonical URL',
                'OG Title',
                'OG Description',
                'OG Image',
                'OG Site Name',
                'Twitter Card',
                'Twitter Title',
                'Twitter Description',
                'Twitter Image',
                'Twitter Site',
                'Twitter Creator',
                'Author',
                'Theme Color',
                'JSON-LD',
                'Breadcrumb JSON-LD',
                'Status',
                'Created At',
                'Updated At',
            ]);

            // Export data in chunks
            $query->chunk(500, function ($pages) use ($handle) {
                foreach ($pages as $page) {
                    fputcsv($handle, [
                        $page->id,
                        $page->domain,
                        $page->path,
                        $page->query_hash,
                        $page->title,
                        $page->description,
                        $page->keywords,
                        $page->robots,
                        $page->language,
                        $page->canonical_url,
                        $page->og_title,
                        $page->og_description,
                        $page->og_image,
                        $page->og_site_name,
                        $page->twitter_card,
                        $page->twitter_title,
                        $page->twitter_description,
                        $page->twitter_image,
                        $page->twitter_site,
                        $page->twitter_creator,
                        $page->author,
                        $page->theme_color,
                        $page->jsonld ? json_encode($page->jsonld, JSON_UNESCAPED_UNICODE) : '',
                        $page->breadcrumb_jsonld ? json_encode($page->breadcrumb_jsonld, JSON_UNESCAPED_UNICODE) : '',
                        $page->status,
                        $page->created_at?->toDateTimeString(),
                        $page->updated_at?->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export pages as JSON.
     */
    public function exportJson(Request $request): Response
    {
        $query = MetaKitPage::query();

        // Apply filters if provided
        if ($request->has('domain') && !empty($request->domain)) {
            $query->where('domain', $request->domain);
        }

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $pages = $query->get();
        $data = MetaKitPageResource::collection($pages);

        $filename = 'metakit-pages-' . date('Y-m-d-His') . '.json';

        return response($data->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Import pages from JSON.
     */
    public function importJson(Request $request): JsonResponse
    {
        $request->validate([
            'data' => ['required', 'array'],
            'data.*.domain' => ['required', 'string'],
            'data.*.path' => ['required', 'string', 'starts_with:/'],
            'data.*.status' => ['sometimes', 'in:draft,active'],
            'skip_duplicates' => ['sometimes', 'boolean'],
        ]);

        $data = $request->input('data', []);
        $skipDuplicates = $request->boolean('skip_duplicates', false);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data as $index => $item) {
            try {
                // Check if already exists
                $existing = MetaKitPage::where('domain', $item['domain'])
                    ->where('path', $item['path'])
                    ->where('query_hash', $item['query_hash'] ?? null)
                    ->first();

                if ($existing) {
                    if ($skipDuplicates) {
                        $skipped++;
                        continue;
                    }
                    // Update existing
                    $updateData = $item;
                    $userId = auth()->id();
                    if ($userId) {
                        $updateData['updated_by'] = $userId;
                    }
                    $existing->update($updateData);
                    $this->manager->purgeCache($existing->domain, $existing->path, $existing->query_hash);
                    $imported++;
                } else {
                    // Create new
                    $createData = $item;
                    $userId = auth()->id();
                    if ($userId) {
                        $createData['created_by'] = $userId;
                        $createData['updated_by'] = $userId;
                    }
                    MetaKitPage::create($createData);
                    $this->manager->purgeCache($item['domain'], $item['path'], $item['query_hash'] ?? null);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ], 200);
    }

    /**
     * Import pages from CSV file.
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
            'skip_duplicates' => ['sometimes', 'boolean'],
        ]);

        $file = $request->file('file');
        $skipDuplicates = $request->boolean('skip_duplicates', false);
        $imported = 0;
        $skipped = 0;
        $errors = [];

        $handle = fopen($file->getPathname(), 'r');
        
        if (!$handle) {
            return response()->json(['message' => 'Could not open CSV file'], 422);
        }

        try {
            // Skip BOM if present
            $bom = fread($handle, 3);
            if ($bom !== chr(0xEF).chr(0xBB).chr(0xBF)) {
                rewind($handle);
            }

            // Read header row
            $headers = fgetcsv($handle);
            if (!$headers) {
                return response()->json(['message' => 'Invalid CSV file - no headers found'], 422);
            }

        // Map headers to column indices
        $headerMap = array_flip($headers);

        $rowNumber = 1; // Header is row 1
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            try {
                // Map CSV row to array
                $item = [];
                foreach ($headerMap as $header => $index) {
                    $item[$header] = $row[$index] ?? null;
                }

                // Skip if required fields are missing
                if (empty($item['Domain']) || empty($item['Path'])) {
                    $skipped++;
                    continue;
                }

                // Prepare data for model
                $data = [
                    'domain' => $item['Domain'],
                    'path' => $item['Path'],
                    'query_hash' => $item['Query Hash'] ?? null,
                    'title' => $item['Title'] ?? null,
                    'description' => $item['Description'] ?? null,
                    'keywords' => $item['Keywords'] ?? null,
                    'robots' => $item['Robots'] ?? null,
                    'language' => $item['Language'] ?? null,
                    'canonical_url' => $item['Canonical URL'] ?? null,
                    'og_title' => $item['OG Title'] ?? null,
                    'og_description' => $item['OG Description'] ?? null,
                    'og_image' => $item['OG Image'] ?? null,
                    'og_site_name' => $item['OG Site Name'] ?? null,
                    'twitter_card' => $item['Twitter Card'] ?? null,
                    'twitter_title' => $item['Twitter Title'] ?? null,
                    'twitter_description' => $item['Twitter Description'] ?? null,
                    'twitter_image' => $item['Twitter Image'] ?? null,
                    'twitter_site' => $item['Twitter Site'] ?? null,
                    'twitter_creator' => $item['Twitter Creator'] ?? null,
                    'author' => $item['Author'] ?? null,
                    'theme_color' => $item['Theme Color'] ?? null,
                    'status' => $item['Status'] ?? 'active',
                ];
                
                // Set user IDs safely
                $userId = auth()->id();
                if ($userId) {
                    $data['created_by'] = $userId;
                    $data['updated_by'] = $userId;
                }

                // Parse JSON-LD fields
                if (!empty($item['JSON-LD'])) {
                    $decoded = json_decode($item['JSON-LD'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['jsonld'] = $decoded;
                    }
                }

                if (!empty($item['Breadcrumb JSON-LD'])) {
                    $decoded = json_decode($item['Breadcrumb JSON-LD'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $data['breadcrumb_jsonld'] = $decoded;
                    }
                }

                // Check if already exists
                $existing = MetaKitPage::where('domain', $data['domain'])
                    ->where('path', $data['path'])
                    ->where('query_hash', $data['query_hash'])
                    ->first();

                if ($existing) {
                    if ($skipDuplicates) {
                        $skipped++;
                        continue;
                    }
                    // Update existing
                    $existing->update($data);
                    $this->manager->purgeCache($existing->domain, $existing->path, $existing->query_hash);
                    $imported++;
                } else {
                    // Create new
                    MetaKitPage::create($data);
                    $this->manager->purgeCache($data['domain'], $data['path'], $data['query_hash']);
                    $imported++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                ];
            }
        }

        } finally {
            // Always close the file handle
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return response()->json([
            'message' => 'Import completed',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ], 200);
    }

    /**
     * Check for duplicate titles and descriptions.
     */
    protected function checkDuplicates(array $data, ?int $excludeId = null): array
    {
        $warnings = [
            'title_duplicates' => [],
            'description_duplicates' => [],
        ];

        if (empty($data['domain'])) {
            return $warnings;
        }

        $query = MetaKitPage::where('domain', $data['domain']);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        // Check for duplicate title
        if (!empty($data['title'])) {
            $titleDuplicates = (clone $query)
                ->where('title', $data['title'])
                ->get(['id', 'path', 'query_hash', 'title']);

            if ($titleDuplicates->isNotEmpty()) {
                $warnings['title_duplicates'] = $titleDuplicates->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'path' => $page->path,
                        'query_hash' => $page->query_hash,
                        'title' => $page->title,
                    ];
                })->toArray();
            }
        }

        // Check for duplicate description
        if (!empty($data['description'])) {
            $descriptionDuplicates = (clone $query)
                ->where('description', $data['description'])
                ->get(['id', 'path', 'query_hash', 'description']);

            if ($descriptionDuplicates->isNotEmpty()) {
                $warnings['description_duplicates'] = $descriptionDuplicates->map(function ($page) {
                    return [
                        'id' => $page->id,
                        'path' => $page->path,
                        'query_hash' => $page->query_hash,
                        'description' => substr($page->description, 0, 100) . (strlen($page->description) > 100 ? '...' : ''),
                    ];
                })->toArray();
            }
        }

        return $warnings;
    }
}

