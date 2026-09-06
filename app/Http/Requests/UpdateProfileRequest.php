<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'lowercase',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($this->user()),
            ],
            'location' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:500'],
            'favorite_literature_ids' => ['array', 'max:4'],
            'favorite_literature_ids.*' => ['integer', 'distinct', Rule::exists('literatures', 'id')],
            'favorite_author_ids' => ['array', 'max:4'],
            'favorite_author_ids.*' => ['integer', 'distinct', Rule::exists('authors', 'id')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => Str::lower(trim((string) $this->input('username'))),
            'favorite_literature_ids' => $this->filledIntegerValues('favorite_literature_ids'),
            'favorite_author_ids' => $this->filledIntegerValues('favorite_author_ids'),
        ]);
    }

    /** @return array<int, int> */
    private function filledIntegerValues(string $key): array
    {
        return collect(Arr::wrap($this->input($key)))
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->values()
            ->all();
    }
}
