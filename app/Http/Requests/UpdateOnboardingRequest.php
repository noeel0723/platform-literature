<?php

namespace App\Http\Requests;

use App\Models\Literature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOnboardingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() && $this->user()->isActive();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['next', 'back', 'complete', 'skip'])],
            'formats' => ['sometimes', 'array', 'list', 'max:4'],
            'formats.*' => ['required', 'string', 'distinct', Rule::in(Literature::supportedTypes())],
        ];
    }
}
