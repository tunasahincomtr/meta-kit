<?php

namespace TunaSahincomtr\MetaKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use TunaSahincomtr\MetaKit\Services\SeoScoreCalculator;
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

        $data = [
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
            'language' => $this->language,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'og_site_name' => $this->og_site_name,
            'twitter_card' => $this->twitter_card,
            'twitter_title' => $this->twitter_title,
            'twitter_description' => $this->twitter_description,
            'twitter_image' => $this->twitter_image,
            'twitter_site' => $this->twitter_site,
            'twitter_creator' => $this->twitter_creator,
            'author' => $this->author,
            'theme_color' => $this->theme_color,
            'jsonld' => $this->jsonld,
            'breadcrumb_jsonld' => $this->breadcrumb_jsonld,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        // Add SEO score if requested (default: true for show, false for index)
        if ($request->boolean('include_seo_score', true)) {
            try {
                $calculator = new SeoScoreCalculator();
                // Pass the model instance (SeoScoreCalculator expects MetaKitPage model)
                $data['seo_score'] = $calculator->calculate($this->resource);
            } catch (\Exception $e) {
                // Silently fail if SEO score calculation fails
                \Log::warning('Failed to calculate SEO score', [
                    'error' => $e->getMessage(),
                    'page_id' => $this->id,
                ]);
            }
        }

        return $data;
    }
}

