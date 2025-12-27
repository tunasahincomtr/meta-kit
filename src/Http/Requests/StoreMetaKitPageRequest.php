<?php

namespace TunaSahincomtr\MetaKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetaKitPageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255'],
            'path' => ['required', 'string', 'max:255', 'starts_with:/'],
            'query_hash' => ['nullable', 'string', 'max:40'],
            // Unique constraint: domain + path + query_hash combination
            // This is handled at database level, but we can add a custom rule if needed
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string'],
            'robots' => ['nullable', 'string', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'og_title' => ['nullable', 'string', 'max:255'],
            'og_description' => ['nullable', 'string'],
            'og_image' => ['nullable', 'url', 'max:2048'],
            'twitter_card' => ['nullable', 'string', 'max:50'],
            'twitter_title' => ['nullable', 'string', 'max:255'],
            'twitter_description' => ['nullable', 'string'],
            'twitter_image' => ['nullable', 'url', 'max:2048'],
            'twitter_site' => ['nullable', 'string', 'max:100'],
            'twitter_creator' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:10'],
            'og_site_name' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'theme_color' => ['nullable', 'string', 'max:7', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'jsonld' => ['nullable', 'array'],
            'jsonld.*' => ['nullable', 'array'], // Validate each item in jsonld array is an array
            'breadcrumb_jsonld' => ['nullable', 'array'], // Kept for backward compatibility
            'status' => ['required', 'in:draft,active'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Handle jsonld as string (decode if needed)
        if ($this->has('jsonld') && is_string($this->jsonld)) {
            $decoded = json_decode($this->jsonld, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['jsonld' => $decoded]);
            }
        }

        // Handle breadcrumb_jsonld as string (decode if needed)
        if ($this->has('breadcrumb_jsonld') && is_string($this->breadcrumb_jsonld)) {
            $decoded = json_decode($this->breadcrumb_jsonld, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['breadcrumb_jsonld' => $decoded]);
            }
        }
    }
}

