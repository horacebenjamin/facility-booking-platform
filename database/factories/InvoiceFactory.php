<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['reference' => 'INV-'.fake()->uuid(), 'customer_id' => User::factory(), 'organisation_id' => null, 'issued_by' => User::factory(), 'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'status' => InvoiceStatus::Issued, 'currency' => 'GBP', 'total_minor' => 5000];
    }
}
