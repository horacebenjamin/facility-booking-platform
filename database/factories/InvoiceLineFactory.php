<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLine> */
class InvoiceLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['invoice_id' => Invoice::factory(), 'booking_id' => Booking::factory(), 'charge_kind' => 'booking_total', 'description' => 'Facility booking charge', 'amount_minor' => 5000];
    }
}
