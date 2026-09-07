<?php

namespace App\Http\Requests;

use App\Models\ReadingList;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReadingListRequest extends FormRequest
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
            'status' => ['required', Rule::in(array_keys(ReadingList::STATUS_LABELS))],
            'progress_value' => ['nullable', 'integer', 'min:0'],
            'progress_total' => ['nullable', 'integer', 'min:1'],
            'progress_unit' => ['required_with:progress_value,progress_total', Rule::in(['page', 'chapter', 'percent'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'reread' => ['nullable', 'boolean'],
            'return_to' => ['nullable', Rule::in(['readlist'])],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $current = $this->integer('progress_value');
                $total = $this->filled('progress_total') ? $this->integer('progress_total') : null;

                if ($total !== null && $current > $total) {
                    $validator->errors()->add('progress_value', 'Current progress cannot exceed the total length.');
                }

                if ($this->input('progress_unit') === 'percent' && $current > 100) {
                    $validator->errors()->add('progress_value', 'Percentage progress cannot exceed 100.');
                }
            },
        ];
    }
}
