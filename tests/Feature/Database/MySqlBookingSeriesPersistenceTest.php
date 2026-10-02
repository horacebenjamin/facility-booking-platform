<?php

namespace Tests\Feature\Database;

use App\Enums\RecurrenceFrequency;
use App\Models\Booking;
use App\Models\BookingSeries;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlBookingSeriesPersistenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    public function test_booking_series_persists_identity_context_and_weekly_recurrence_intent(): void
    {
        $series = BookingSeries::factory()->create([
            'identifier' => '65d78bc1-d51a-45e9-8bd8-9758a6b22283',
            'recurrence_frequency' => RecurrenceFrequency::Weekly,
            'interval_weeks' => 2,
            'occurrence_count' => 6,
            'timezone' => 'Europe/London',
            'first_starts_at' => '2026-10-05 17:00:00',
            'first_ends_at' => '2026-10-05 18:30:00',
        ])->fresh();

        $this->assertSame('65d78bc1-d51a-45e9-8bd8-9758a6b22283', $series->identifier);
        $this->assertSame(RecurrenceFrequency::Weekly, $series->recurrence_frequency);
        $this->assertSame(2, $series->interval_weeks);
        $this->assertSame(6, $series->occurrence_count);
        $this->assertSame('Europe/London', $series->timezone);
        $this->assertSame($series->customer_id, $series->customer->id);
        $this->assertSame($series->centre_id, $series->centre->id);
        $this->assertSame($series->facility_id, $series->facility->id);
        $this->assertSame($series->resource_id, $series->resource->id);
    }

    public function test_one_off_booking_remains_valid_without_a_series(): void
    {
        $booking = Booking::factory()->create()->fresh();

        $this->assertNull($booking->booking_series_id);
        $this->assertNull($booking->occurrence_index);
        $this->assertNull($booking->series);
    }

    public function test_occurrence_belongs_to_series_and_retains_ordinary_booking_identity(): void
    {
        $series = BookingSeries::factory()->create();
        $booking = Booking::factory()->create([
            'booking_series_id' => $series->id,
            'occurrence_index' => 1,
            'customer_id' => $series->customer_id,
            'centre_id' => $series->centre_id,
            'facility_id' => $series->facility_id,
            'resource_id' => $series->resource_id,
        ]);

        $this->assertTrue($booking->series->is($series));
        $this->assertTrue($series->bookings->contains($booking));
        $this->assertSame(1, $booking->occurrence_index);
        $this->assertMatchesRegularExpression('/^BKG-\d{8}$/', $booking->reference);
    }

    public function test_database_rejects_an_occurrence_with_context_from_another_series(): void
    {
        $series = BookingSeries::factory()->create();
        $unrelatedBooking = Booking::factory()->create();

        $this->expectException(QueryException::class);

        Booking::query()->create([
            'booking_series_id' => $series->id,
            'occurrence_index' => 1,
            'reference' => 'BKG-87654321',
            'customer_id' => $unrelatedBooking->customer_id,
            'centre_id' => $unrelatedBooking->centre_id,
            'facility_id' => $unrelatedBooking->facility_id,
            'resource_id' => $unrelatedBooking->resource_id,
            'starts_at' => $unrelatedBooking->starts_at,
            'ends_at' => $unrelatedBooking->ends_at,
            'status' => $unrelatedBooking->status,
            'financial_status' => $unrelatedBooking->financial_status,
        ]);
    }
}
