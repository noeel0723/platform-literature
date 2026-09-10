<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLiteratureMetadataOverrideRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'original_title' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1', 'max:'.(now()->year + 5)],
            'tagline' => ['nullable', 'string', 'max:2000'],
            'synopsis' => ['nullable', 'string', 'max:20000'],
            'cover_url' => ['nullable', 'url:http,https', 'max:4096'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:30'],
            'format' => ['nullable', 'string', 'max:100'],
            'source_url' => ['nullable', 'url:http,https', 'max:4096'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'reset' => ['nullable', 'boolean'],
        ];
    }
}
