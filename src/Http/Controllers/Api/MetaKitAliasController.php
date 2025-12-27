<?php

namespace TunaSahincomtr\MetaKit\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TunaSahincomtr\MetaKit\Http\Requests\StoreMetaKitAliasRequest;
use TunaSahincomtr\MetaKit\Http\Requests\UpdateMetaKitAliasRequest;
use TunaSahincomtr\MetaKit\Http\Resources\MetaKitAliasResource;
use TunaSahincomtr\MetaKit\Models\MetaKitAlias;

class MetaKitAliasController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = MetaKitAlias::query();

        // Search
        if ($request->has('q') && !empty($request->q)) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('domain', 'like', "%{$search}%")
                    ->orWhere('old_path', 'like', "%{$search}%")
                    ->orWhere('new_path', 'like', "%{$search}%");
            });
        }

        // Filters
        if ($request->has('domain') && !empty($request->domain)) {
            $query->where('domain', $request->domain);
        }

        if ($request->has('old_path') && !empty($request->old_path)) {
            $query->where('old_path', 'like', $request->old_path . '%');
        }

        // Order
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = min((int) $request->get('per_page', 20), 100);
        $aliases = $query->paginate($perPage);

        return MetaKitAliasResource::collection($aliases)->response();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMetaKitAliasRequest $request): JsonResponse
    {
        $data = $request->validated();
        
        // Check if alias already exists
        $existing = MetaKitAlias::where('domain', $data['domain'])
            ->where('old_path', $data['old_path'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Alias already exists',
                'data' => new MetaKitAliasResource($existing),
            ], 409);
        }

        $alias = MetaKitAlias::create($data);

        return (new MetaKitAliasResource($alias))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(MetaKitAlias $alias): JsonResponse
    {
        return (new MetaKitAliasResource($alias))->response();
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMetaKitAliasRequest $request, MetaKitAlias $alias): JsonResponse
    {
        $data = $request->validated();

        // Check if another alias with same domain+old_path exists
        if (isset($data['domain']) || isset($data['old_path'])) {
            $domain = $data['domain'] ?? $alias->domain;
            $oldPath = $data['old_path'] ?? $alias->old_path;
            
            $existing = MetaKitAlias::where('domain', $domain)
                ->where('old_path', $oldPath)
                ->where('id', '!=', $alias->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Another alias with the same domain and old_path already exists',
                ], 409);
            }
        }

        $alias->update($data);

        return (new MetaKitAliasResource($alias))->response();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MetaKitAlias $alias): JsonResponse
    {
        $alias->delete();

        return response()->json(['message' => 'Alias deleted successfully'], 200);
    }
}

