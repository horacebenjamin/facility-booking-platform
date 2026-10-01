<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class QuotePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'amount_minor' => ['prohibited'],
            'calculated_total_minor' => ['prohibited'],
            'currency' => ['prohibited'],
            'discount' => ['prohibited'],
            'final_total_minor' => ['prohibited'],
            'override' => ['prohibited'],
            'rate_id' => ['prohibited'],
            'subtotal_minor' => ['prohibited'],
            'user_id' => ['prohibited'],
            'equipment.*.amount_minor' => ['prohibited'],
            'equipment.*.currency' => ['prohibited'],
            'equipment.*.rate_id' => ['prohibited'],
        ];
    }
}
