<?php

namespace TunaSahincomtr\MetaKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaKitPageRequest extends FormRequest
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
            'domain' => ['sometimes', 'required', 'string', 'max:255'],
            'path' => ['sometimes', 'required', 'string', 'max:255', 'starts_with:/'],
            'query_hash' => ['nullable', 'string', 'max:40'],
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
            'jsonld' => ['nullable', 'array'],
            'status' => ['sometimes', 'required', 'in:draft,active'],
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
    }
}
