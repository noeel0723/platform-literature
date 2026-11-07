<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAuthorMetadataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true && $this->user()->canUseInteractiveFeatures();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'biography_override' => ['nullable', 'string', 'max:20000'],
            'image_url_override' => ['nullable', 'url:http,https', 'max:4096'],
            'image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image_upload' => ['nullable', 'boolean'],
        ];
    }
}
