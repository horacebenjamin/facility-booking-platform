<?php

namespace App\Http\Requests;

use App\Services\RecurrencePattern;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecurringBookingPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bookings.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->recurringSelectionRules(),
            'submission_mode' => ['prohibited'],
            'selected_occurrence_indexes' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function recurringSelectionRules(): array
    {
        return [
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'interval_weeks' => ['required', 'integer', 'between:1,'.RecurrencePattern::MaximumIntervalWeeks],
            'occurrence_count' => ['required', 'integer', 'between:2,'.RecurrencePattern::MaximumOccurrences],
            'timezone' => ['required', 'string', 'max:64', 'timezone:all'],
            'equipment' => ['sometimes', 'array'],
            'equipment.*.equipment_id' => ['required', 'integer', 'distinct:strict', 'exists:equipment,id'],
            'equipment.*.quantity' => ['required', 'integer', 'min:1'],
            'amount_minor' => ['prohibited'],
            'billing_method' => ['prohibited'],
            'invoice_term_days' => ['prohibited'],
            'invoice_id' => ['prohibited'],
            'invoice_eligible' => ['prohibited'],
            'availability' => ['prohibited'],
            'booking_series_id' => ['prohibited'],
            'calculated_total_minor' => ['prohibited'],
            'centre_id' => ['prohibited'],
            'currency' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'discount' => ['prohibited'],
            'ends_at_operational' => ['prohibited'],
            'expires_at' => ['prohibited'],
            'facility_id' => ['prohibited'],
            'final_total_minor' => ['prohibited'],
            'financial_status' => ['prohibited'],
            'occurrence_index' => ['prohibited'],
            'override' => ['prohibited'],
            'price' => ['prohibited'],
            'price_snapshot' => ['prohibited'],
            'rate_id' => ['prohibited'],
            'recurrence_frequency' => ['prohibited'],
            'reference' => ['prohibited'],
            'starts_at_operational' => ['prohibited'],
            'status' => ['prohibited'],
            'subtotal_minor' => ['prohibited'],
            'user_id' => ['prohibited'],
            'equipment.*.amount_minor' => ['prohibited'],
            'equipment.*.currency' => ['prohibited'],
            'equipment.*.rate_id' => ['prohibited'],
        ];
    }
}
