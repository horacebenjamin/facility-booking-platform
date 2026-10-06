<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AmendBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bookings.amend') ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'in:occurrence'],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ];
    }
}
