<?php

namespace TunaSahincomtr\MetaKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
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

        return MetaKitPageResource::collection($pages)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMetaKitPageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        $page = MetaKitPage::create($data);

        // Purge cache
        $this->manager->purgeCache(
            $page->domain,
            $page->path,
            $page->query_hash
        );

        return (new MetaKitPageResource($page))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MetaKitPage $page): JsonResponse
    {
        return (new MetaKitPageResource($page))->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMetaKitPageRequest $request, MetaKitPage $page): JsonResponse
    {
        $data = $request->validated();
        $data['updated_by'] = auth()->id();

        $oldDomain = $page->domain;
        $oldPath = $page->path;
        $oldQueryHash = $page->query_hash;

        $page->update($data);

        // Purge old and new cache
        $this->manager->purgeCache($oldDomain, $oldPath, $oldQueryHash);
        $this->manager->purgeCache(
            $page->domain,
            $page->path,
            $page->query_hash
        );

        return (new MetaKitPageResource($page))->response();
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

        // Purge cache
        $this->manager->purgeCache($domain, $path, $queryHash);

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
        $domain = $parsed['host'] ?? '';
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
        $page = MetaKitPage::create([
            'domain' => $domain,
            'path' => $path,
            'query_hash' => $queryHash,
            'status' => $status,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return (new MetaKitPageResource($page))
            ->response()
            ->setStatusCode(201);
    }
}

