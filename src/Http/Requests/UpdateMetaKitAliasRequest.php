<?php

namespace TunaSahincomtr\MetaKit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaKitAliasRequest extends FormRequest
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
            'old_path' => ['sometimes', 'required', 'string', 'max:255', 'starts_with:/'],
            'new_path' => ['sometimes', 'required', 'string', 'max:255', 'starts_with:/'],
        ];
    }
}

