<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ShowBookingReviewRequest extends FormRequest
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
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'equipment' => ['sometimes', 'array'],
            'equipment.*.equipment_id' => ['required', 'integer', 'distinct:strict', 'exists:equipment,id'],
            'equipment.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }
}
