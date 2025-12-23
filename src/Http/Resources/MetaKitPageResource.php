<?php

namespace TunaSahincomtr\MetaKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use TunaSahincomtr\MetaKit\Services\UrlKeyResolver;

class MetaKitPageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolver = new UrlKeyResolver();
        $cacheKey = $resolver->generateCacheKey(
            $this->domain,
            $this->path,
            $this->query_hash
        );

        $previewUrl = $this->canonical_url;
        if (!$previewUrl) {
            $scheme = $request->getScheme();
            $previewUrl = $scheme . '://' . $this->domain . $this->path;
        }

        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'path' => $this->path,
            'query_hash' => $this->query_hash,
            'page_key' => $this->domain . '|' . $this->path . '|' . ($this->query_hash ?? ''),
            'cache_key' => $cacheKey,
            'preview_url' => $previewUrl,
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'robots' => $this->robots,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'twitter_card' => $this->twitter_card,
            'twitter_title' => $this->twitter_title,
            'twitter_description' => $this->twitter_description,
            'twitter_image' => $this->twitter_image,
            'jsonld' => $this->jsonld,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

