<?php

namespace App\Http\Requests;

use App\Models\Discussion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment_body' => trim((string) $this->input('comment_body')),
        ]);
    }

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
        $discussion = $this->route('discussion');

        return [
            'comment_body' => ['required', 'string', 'max:3000'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('comments', 'id')
                    ->where('discussion_id', $discussion instanceof Discussion ? $discussion->id : 0)
                    ->whereNull('parent_id'),
            ],
            'comment_contains_spoiler' => ['nullable', 'boolean'],
        ];
    }
}
