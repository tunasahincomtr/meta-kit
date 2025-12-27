<?php

namespace TunaSahincomtr\MetaKit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetaKitAliasResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'old_path' => $this->old_path,
            'new_path' => $this->new_path,
            'redirect_url' => $this->buildRedirectUrl(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Build full redirect URL.
     */
    protected function buildRedirectUrl(): string
    {
        $scheme = request()->isSecure() ? 'https' : 'http';
        return $scheme . '://' . $this->domain . $this->new_path;
    }
}

