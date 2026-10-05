<?php

namespace Tests\Feature\Closures;

use App\Actions\CreateAvailabilityBlock;
use App\Actions\DetectClosureAffectedBookings;
use App\Actions\EndAvailabilityBlock;
use App\Actions\RecordClosureCustomerCommunication;
use App\Actions\RecordClosureImpactReview;
use App\Actions\ResolveClosureImpact;
use App\Enums\BookingStatus;
use App\Enums\ClosureImpactStatus;
use App\Models\AvailabilityBlock;
use App\Models\AvailabilityBlockBookingImpact;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\AvailabilityBlockEvaluator;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClosureManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
    }

    public function test_review_and_manual_communication_enable_explicit_resolution_without_financial_effects(): void
    {
        [$manager, $block, $booking, $impact] = $this->scenario();
        $before = $booking->fresh()->getAttributes();
        $review = app(RecordClosureImpactReview::class);
        $data = ['operational_requirement' => 'no_action_required', 'financial_requirement' => 'refund_required', 'communication_required' => true, 'review_notes' => 'Venue reopened; separately review the requested goodwill refund.'];
        $review->handle($manager, $impact, $data);
        $count = Activity::query()->count();
        $review->handle($manager, $impact, $data);
        $this->assertSame($count, Activity::query()->count());
        $this->assertSame(ClosureImpactStatus::Unresolved, $impact->fresh()->status);
        $this->assertInvalid(fn () => app(ResolveClosureImpact::class)->handle($manager, $impact, 'Reviewed'));
        app(RecordClosureCustomerCommunication::class)->handle($manager, $impact, 'Customer informed by telephone about reopening.');
        $result = app(ResolveClosureImpact::class)->handle($manager, $impact, 'Operational disruption no longer requires booking changes.');

        $this->assertSame(ClosureImpactStatus::Resolved, $result->status);
        $this->assertSame('refund_required', $result->financial_requirement->value);
        $this->assertSame($manager->id, $result->resolved_by);
        $this->assertSame($manager->id, $result->communicated_by);
        $this->assertTrue($result->resolved_at->equalTo(now()));
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('invoices', 0);
        foreach (['closure.review_recorded', 'closure.customer_communication_recorded', 'closure.impact_resolved'] as $event) {
            $this->assertSame($manager->id, Activity::query()->where('event', $event)->sole()->causer_id);
        }
        $count = Activity::query()->count();
        $this->assertInvalid(fn () => app(ResolveClosureImpact::class)->handle($manager, $impact, 'Retry'));
        $this->assertInvalid(fn () => app(RecordClosureCustomerCommunication::class)->handle($manager, $impact, 'Retry'));
        $this->assertInvalid(fn () => $review->handle($manager, $impact, $data));
        $this->assertSame($count, Activity::query()->count());
    }

    #[TestWith(['reschedule_required', 'not_required'])]
    #[TestWith(['alternative_resource_required', 'not_required'])]
    #[TestWith(['alternative_centre_required', 'not_required'])]
    #[TestWith(['cancellation_required', 'not_required'])]
    #[TestWith(['no_action_required', 'review_required'])]
    public function test_unperformed_operational_work_or_unassessed_finance_cannot_be_called_resolved(string $operational, string $financial): void
    {
        [$manager, , , $impact] = $this->scenario();
        app(RecordClosureImpactReview::class)->handle($manager, $impact, ['operational_requirement' => $operational, 'financial_requirement' => $financial, 'communication_required' => false, 'review_notes' => 'Further management work required.']);

        $this->assertInvalid(fn () => app(ResolveClosureImpact::class)->handle($manager, $impact, 'Attempt to close review'));

        $this->assertSame(ClosureImpactStatus::Unresolved, $impact->fresh()->status);
        $this->assertSame(0, Activity::query()->where('event', 'closure.impact_resolved')->count());
    }

    public function test_duplicate_contact_and_invalid_review_input_leave_history_unchanged(): void
    {
        [$manager, , , $impact] = $this->scenario();
        app(RecordClosureCustomerCommunication::class)->handle($manager, $impact, 'Customer was contacted.');
        $this->assertInvalid(fn () => app(RecordClosureCustomerCommunication::class)->handle($manager, $impact, 'Duplicate'));
        $this->assertInvalid(fn () => app(RecordClosureImpactReview::class)->handle($manager, $impact, ['operational_requirement' => 'cancelled', 'financial_requirement' => 'refunded', 'communication_required' => false, 'review_notes' => ' ']));
        $this->assertSame(1, Activity::query()->where('event', 'closure.customer_communication_recorded')->count());
        $this->assertSame(0, Activity::query()->where('event', 'closure.review_recorded')->count());
    }

    public function test_closure_review_end_and_resolution_preserve_paid_card_and_outstanding_invoice_records(): void
    {
        [$manager, $block, $paid, $impact] = $this->scenario();
        $payment = Payment::factory()->create(['booking_id' => $paid->id, 'status' => 'succeeded']);
        $invoiced = Booking::factory()->create(['resource_id' => $paid->resource_id, 'status' => BookingStatus::Confirmed, 'financial_status' => 'invoiced', 'billing_method' => 'invoice', 'invoice_term_days' => 30, 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $invoice = Invoice::factory()->create(['customer_id' => $invoiced->customer_id]);
        $line = InvoiceLine::factory()->create(['booking_id' => $invoiced->id, 'invoice_id' => $invoice->id]);
        $before = [$payment->fresh()->getAttributes(), $invoice->fresh()->getAttributes(), $line->fresh()->getAttributes(), $invoiced->fresh()->getAttributes()];
        app(DetectClosureAffectedBookings::class)->handle($manager, $block);
        app(EndAvailabilityBlock::class)->handle($manager, $block);
        foreach ($block->impacts()->get() as $affected) {
            app(RecordClosureImpactReview::class)->handle($manager, $affected, $this->reviewData());
            app(ResolveClosureImpact::class)->handle($manager, $affected, 'Block ended; no booking change is necessary.');
        }
        $this->assertSame($before, [$payment->fresh()->getAttributes(), $invoice->fresh()->getAttributes(), $line->fresh()->getAttributes(), $invoiced->fresh()->getAttributes()]);
        $this->assertSame('paid', $paid->fresh()->financial_status->value);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_ending_overlapping_closures_preserves_intervals_impacts_and_audit(): void
    {
        [$manager, $first, $booking, $impact] = $this->scenario();
        $second = app(CreateAvailabilityBlock::class)->handle($manager, ['centre_id' => $booking->centre_id, 'scope' => 'centre', 'type' => 'weather', 'reason' => 'Storm', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 12:00:00']);
        $evaluator = app(AvailabilityBlockEvaluator::class);
        $starts = CarbonImmutable::parse('2026-10-06 10:00:00');
        $ends = $starts->addHour();
        $original = $first->ends_at->toDateTimeString();
        app(EndAvailabilityBlock::class)->handle($manager, $first);
        $this->assertTrue($evaluator->isBlocked($booking->resource, $starts, $ends));
        app(EndAvailabilityBlock::class)->handle($manager, $second);
        $this->assertFalse($evaluator->isBlocked($booking->resource, $starts, $ends));
        $this->assertSame($original, $first->fresh()->ends_at->toDateTimeString());
        $this->assertSame(ClosureImpactStatus::Unresolved, $impact->fresh()->status);
        $this->assertDatabaseCount('availability_blocks', 2);
        $this->assertDatabaseCount('availability_block_booking_impacts', 2);
        $this->assertSame(2, Activity::query()->where('event', 'closure.ended')->count());
        $this->assertInvalid(fn () => app(EndAvailabilityBlock::class)->handle($manager, $first));
        $this->assertSame(2, Activity::query()->where('event', 'closure.ended')->count());
    }

    #[TestWith(['manager', false])]
    #[TestWith(['leisure-assistant', true])]
    #[TestWith(['customer', true])]
    public function test_unauthorised_staff_cannot_review_resolve_contact_or_end(string $role, bool $assigned): void
    {
        [, $block, $booking, $impact] = $this->scenario();
        $actor = User::factory()->create();
        $actor->assignRole($role);
        if ($assigned) {
            $actor->assignedCentres()->attach($booking->centre_id);
        }
        foreach ([fn () => app(EndAvailabilityBlock::class)->handle($actor, $block), fn () => app(ResolveClosureImpact::class)->handle($actor, $impact, 'Forged'), fn () => app(RecordClosureCustomerCommunication::class)->handle($actor, $impact, 'Forged'), fn () => app(RecordClosureImpactReview::class)->handle($actor, $impact, $this->reviewData())] as $action) {
            try {
                $action();
                $this->fail('Unauthorised mutation succeeded.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
        $this->assertNull($block->fresh()->ended_at);
        $this->assertSame(ClosureImpactStatus::Unresolved, $impact->fresh()->status);
    }

    public function test_reassigned_or_permission_revoked_manager_is_denied_using_stale_models(): void
    {
        [$manager, $block, $booking, $impact] = $this->scenario();
        $manager->load('assignedCentres');
        $manager->assignedCentres()->detach($booking->centre_id);
        $this->expectException(AuthorizationException::class);
        app(EndAvailabilityBlock::class)->handle($manager, $block);
    }

    public function test_impact_review_requires_booking_visibility_in_addition_to_closure_capability(): void
    {
        [$manager, , , $impact] = $this->scenario();
        $manager->roles()->firstOrFail()->revokePermissionTo('bookings.view');
        $this->expectException(AuthorizationException::class);
        app(RecordClosureImpactReview::class)->handle($manager, $impact, $this->reviewData());
    }

    /** @return array{User, AvailabilityBlock, Booking, AvailabilityBlockBookingImpact} */
    private function scenario(): array
    {
        $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'financial_status' => 'paid', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($booking->centre_id);
        $block = app(CreateAvailabilityBlock::class)->handle($manager, ['centre_id' => $booking->centre_id, 'scope' => 'centre', 'type' => 'maintenance', 'reason' => 'Repair', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 12:00:00']);

        return [$manager, $block, $booking, $block->impacts()->sole()];
    }

    /** @return array<string, mixed> */
    private function reviewData(): array
    {
        return ['operational_requirement' => 'no_action_required', 'financial_requirement' => 'not_required', 'communication_required' => false, 'review_notes' => 'Closure ended without disruption.'];
    }

    private function assertInvalid(callable $action): void
    {
        try {
            $action();
            $this->fail('Invalid transition succeeded.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
