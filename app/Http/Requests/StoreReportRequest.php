<?php

namespace App\Http\Requests;

use App\Models\Report;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
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
            'target_type' => ['required', Rule::in(['review', 'discussion', 'comment', 'user'])],
            'target_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(array_keys(Report::REASON_LABELS))],
            'details' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
