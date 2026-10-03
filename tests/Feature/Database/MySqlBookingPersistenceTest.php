<?php

namespace Tests\Feature\Database;

use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\Equipment;
use App\Models\Resource;
use App\Models\ResourceRate;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlBookingPersistenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('mysql', DB::connection()->getDriverName());
    }

    public function test_booking_belongs_to_its_customer_centre_facility_and_resource(): void
    {
        $booking = Booking::factory()->create();

        $this->assertSame($booking->customer_id, $booking->customer->id);
        $this->assertSame($booking->centre_id, $booking->centre->id);
        $this->assertSame($booking->facility_id, $booking->facility->id);
        $this->assertSame($booking->resource_id, $booking->resource->id);
        $this->assertTrue($booking->customer->bookings->contains($booking));
        $this->assertTrue($booking->centre->bookings->contains($booking));
        $this->assertTrue($booking->facility->bookings->contains($booking));
        $this->assertTrue($booking->resource->bookings->contains($booking));
    }

    public function test_booking_factory_creates_a_valid_coherent_record(): void
    {
        $booking = Booking::factory()->create();

        $this->assertModelExists($booking);
        $this->assertSame($booking->facility_id, $booking->resource->facility_id);
        $this->assertSame($booking->centre_id, $booking->facility->centre_id);
        $this->assertTrue($booking->starts_at->lt($booking->ends_at));
    }

    public function test_booking_persists_an_increasing_customer_visible_time_range(): void
    {
        $booking = Booking::factory()->create([
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 11:00:00',
        ]);

        $this->assertSame('2026-10-12 09:00:00', $booking->fresh()->starts_at->toDateTimeString());
        $this->assertSame('2026-10-12 11:00:00', $booking->fresh()->ends_at->toDateTimeString());
    }

    public function test_database_rejects_booking_time_ranges_with_equal_bounds(): void
    {
        $this->expectException(QueryException::class);

        Booking::factory()->create([
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 09:00:00',
        ]);
    }

    public function test_database_rejects_booking_time_ranges_with_reversed_bounds(): void
    {
        $this->expectException(QueryException::class);

        Booking::factory()->create([
            'starts_at' => '2026-10-12 11:00:00',
            'ends_at' => '2026-10-12 09:00:00',
        ]);
    }

    public function test_requested_booking_and_not_due_financial_status_persist_independently(): void
    {
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Requested,
            'financial_status' => FinancialStatus::NotDue,
        ])->fresh();

        $this->assertSame(BookingStatus::Requested, $booking->status);
        $this->assertSame(FinancialStatus::NotDue, $booking->financial_status);
        $this->assertNotSame($booking->status->value, $booking->financial_status->value);
    }

    public function test_eloquent_enum_cast_rejects_an_invalid_booking_status(): void
    {
        $this->expectException(\ValueError::class);

        Booking::factory()->create(['status' => 'invalid']);
    }

    public function test_mysql_rejects_an_invalid_raw_booking_status(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('bookings')->insert([
            'reference' => 'BKG-11111111',
            'customer_id' => $booking->customer_id,
            'centre_id' => $booking->centre_id,
            'facility_id' => $booking->facility_id,
            'resource_id' => $booking->resource_id,
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 11:00:00',
            'status' => 'invalid',
            'financial_status' => FinancialStatus::NotDue->value,
        ]);
    }

    public function test_eloquent_enum_cast_rejects_an_invalid_financial_status(): void
    {
        $this->expectException(\ValueError::class);

        Booking::factory()->create(['financial_status' => 'invalid']);
    }

    public function test_mysql_rejects_an_invalid_raw_financial_status(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('bookings')->insert([
            'reference' => 'BKG-22222222',
            'customer_id' => $booking->customer_id,
            'centre_id' => $booking->centre_id,
            'facility_id' => $booking->facility_id,
            'resource_id' => $booking->resource_id,
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 11:00:00',
            'status' => BookingStatus::Requested->value,
            'financial_status' => 'invalid',
        ]);
    }

    public function test_database_enforces_a_unique_booking_reference(): void
    {
        Booking::factory()->create(['reference' => 'BKG-00000001']);

        $this->expectException(QueryException::class);

        Booking::factory()->create(['reference' => 'BKG-00000001']);
    }

    public function test_pricing_snapshot_preserves_exact_amounts_currency_and_breakdown(): void
    {
        $booking = Booking::factory()->create();
        $equipmentRequest = BookingEquipment::factory()->for($booking)->create(['requested_quantity' => 2]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create([
            'currency' => 'GBP',
            'resource_amount_minor' => 5000,
            'equipment_amount_minor' => 2000,
            'subtotal_minor' => 7000,
            'discount_requested_amount_minor' => 750,
            'discount_amount_minor' => 500,
            'discount_description' => 'Regular discount',
            'calculated_total_minor' => 6500,
            'final_total_minor' => 6500,
        ]);
        $resourceLine = BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();
        $equipmentLine = BookingPriceLine::factory()->for($snapshot, 'snapshot')->equipment($equipmentRequest)->create([
            'booking_id' => $booking->id,
        ]);

        $snapshot = $snapshot->fresh();

        $this->assertTrue($booking->priceSnapshot->is($snapshot));
        $this->assertSame('GBP', $snapshot->currency);
        $this->assertSame(5000, $snapshot->resource_amount_minor);
        $this->assertSame(2000, $snapshot->equipment_amount_minor);
        $this->assertSame(7000, $snapshot->subtotal_minor);
        $this->assertSame(500, $snapshot->discount_amount_minor);
        $this->assertSame(6500, $snapshot->calculated_total_minor);
        $this->assertSame(6500, $snapshot->final_total_minor);
        $this->assertSame(5000, $resourceLine->fresh()->amount_minor);
        $this->assertSame(2000, $equipmentLine->fresh()->amount_minor);
        $this->assertTrue($equipmentLine->equipmentRequest->is($equipmentRequest));
    }

    public function test_included_equipment_retains_a_zero_valued_price_line(): void
    {
        $booking = Booking::factory()->create();
        $equipmentRequest = BookingEquipment::factory()->for($booking)->create(['requested_quantity' => 3]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
        $line = BookingPriceLine::factory()->for($snapshot, 'snapshot')->includedEquipment($equipmentRequest)->create([
            'booking_id' => $booking->id,
        ]);

        $this->assertSame(0, $line->fresh()->hourly_rate_minor);
        $this->assertSame(0, $line->fresh()->amount_minor);
        $this->assertSame(3, $line->fresh()->quantity);
    }

    public function test_pricing_snapshot_is_unaffected_by_later_live_rate_changes(): void
    {
        $booking = Booking::factory()->create();
        $rate = ResourceRate::factory()->for($booking->resource)->create(['amount_minor' => 2500]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
        $line = BookingPriceLine::factory()->for($snapshot, 'snapshot')->create([
            'booking_id' => $booking->id,
            'hourly_rate_minor' => 2500,
            'amount_minor' => 5000,
        ]);

        $rate->update(['amount_minor' => 9900]);

        $this->assertSame(9900, $rate->fresh()->amount_minor);
        $this->assertSame(2500, $line->fresh()->hourly_rate_minor);
        $this->assertSame(5000, $line->fresh()->amount_minor);
    }

    public function test_database_rejects_pricing_snapshot_totals_that_do_not_reconcile(): void
    {
        $this->expectException(QueryException::class);

        BookingPriceSnapshot::factory()->create([
            'resource_amount_minor' => 5000,
            'equipment_amount_minor' => 1000,
            'subtotal_minor' => 5000,
            'calculated_total_minor' => 5000,
            'final_total_minor' => 5000,
        ]);
    }

    public function test_pricing_snapshot_persists_authorised_override_metadata_and_final_total(): void
    {
        $actor = User::factory()->create(['name' => 'Pricing Manager']);
        $snapshot = BookingPriceSnapshot::factory()->create([
            'override_original_total_minor' => 5000,
            'override_adjusted_total_minor' => 4250,
            'override_reason' => 'Community event agreement',
            'override_responsible_user_id' => $actor->id,
            'override_responsible_user_name' => $actor->name,
            'override_adjusted_at' => '2026-10-02 10:30:00',
            'final_total_minor' => 4250,
        ])->fresh();

        $this->assertSame(5000, $snapshot->override_original_total_minor);
        $this->assertSame(4250, $snapshot->override_adjusted_total_minor);
        $this->assertSame('Community event agreement', $snapshot->override_reason);
        $this->assertTrue($snapshot->overrideResponsibleUser->is($actor));
        $this->assertSame('2026-10-02 10:30:00', $snapshot->override_adjusted_at->toDateTimeString());
        $this->assertSame(4250, $snapshot->final_total_minor);
    }

    public function test_booking_equipment_request_persists_its_quantity_and_relationships(): void
    {
        $booking = Booking::factory()->create();
        $request = BookingEquipment::factory()->for($booking)->create(['requested_quantity' => 4]);

        $this->assertSame(4, $request->fresh()->requested_quantity);
        $this->assertTrue($request->booking->is($booking));
        $this->assertSame($request->equipment_id, $request->equipment->id);
        $this->assertTrue($booking->equipmentRequests->contains($request));
        $this->assertTrue($request->equipment->bookingRequests->contains($request));
    }

    public function test_database_rejects_zero_or_negative_booking_equipment_quantities(): void
    {
        $booking = Booking::factory()->create();

        try {
            BookingEquipment::factory()->for($booking)->create(['requested_quantity' => 0]);
            $this->fail('A zero requested equipment quantity must be rejected.');
        } catch (QueryException) {
            $this->assertDatabaseMissing('booking_equipment', [
                'booking_id' => $booking->id,
                'requested_quantity' => 0,
            ]);
        }

        $this->expectException(QueryException::class);

        BookingEquipment::factory()->for($booking)->create(['requested_quantity' => -1]);
    }

    public function test_database_rejects_booking_equipment_with_an_invalid_equipment_reference(): void
    {
        $booking = Booking::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('booking_equipment')->insert([
            'booking_id' => $booking->id,
            'centre_id' => $booking->centre_id,
            'equipment_id' => 999999,
            'requested_quantity' => 1,
        ]);
    }

    public function test_database_rejects_cross_facility_resource_references(): void
    {
        $booking = Booking::factory()->create();
        $otherResource = Resource::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('bookings')->insert([
            'reference' => 'BKG-99999999',
            'customer_id' => $booking->customer_id,
            'centre_id' => $booking->centre_id,
            'facility_id' => $booking->facility_id,
            'resource_id' => $otherResource->id,
            'starts_at' => '2026-10-12 09:00:00',
            'ends_at' => '2026-10-12 11:00:00',
            'status' => BookingStatus::Requested->value,
            'financial_status' => FinancialStatus::NotDue->value,
        ]);
    }

    public function test_database_rejects_booking_equipment_from_another_centre(): void
    {
        $booking = Booking::factory()->create();
        $equipment = Equipment::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('booking_equipment')->insert([
            'booking_id' => $booking->id,
            'centre_id' => $booking->centre_id,
            'equipment_id' => $equipment->id,
            'requested_quantity' => 1,
        ]);
    }

    public function test_database_restricts_deleting_resource_and_equipment_referenced_by_bookings(): void
    {
        $booking = Booking::factory()->create();
        $equipmentRequest = BookingEquipment::factory()->for($booking)->create();

        try {
            $booking->resource->delete();
            $this->fail('A resource referenced by a booking must not be deleted.');
        } catch (QueryException) {
            $this->assertModelExists($booking->resource);
        }

        try {
            $equipmentRequest->equipment->delete();
            $this->fail('Equipment referenced by a booking request must not be deleted.');
        } catch (QueryException) {
            $this->assertModelExists($equipmentRequest->equipment);
        }
    }
}
