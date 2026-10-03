<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StoreRecurringBookingRequest extends RecurringBookingPreviewRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->recurringSelectionRules(),
            'submission_mode' => ['required', Rule::in(['all_occurrences', 'available_occurrences'])],
            'selected_occurrence_indexes' => [
                'required_if:submission_mode,available_occurrences',
                'prohibited_unless:submission_mode,available_occurrences',
                'array',
                'min:1',
            ],
            'selected_occurrence_indexes.*' => [
                'integer',
                'distinct:strict',
                'min:1',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_int($value) && $value > $this->integer('occurrence_count')) {
                        $fail('The selected occurrence is outside this recurring series.');
                    }
                },
            ],
        ];
    }
}
