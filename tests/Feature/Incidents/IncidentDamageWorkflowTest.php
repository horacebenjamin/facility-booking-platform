<?php

namespace Tests\Feature\Incidents;

use App\Actions\CreateDamageReport;
use App\Actions\CreateIncident;
use App\Actions\ReviewDamageReport;
use App\Actions\ReviewIncident;
use App\Enums\BookingStatus;
use App\Enums\DamageFinancialFollowUp;
use App\Enums\DamageResponsibility;
use App\Enums\OperationalIssueStatus;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\DamageReport;
use App\Models\Equipment;
use App\Models\Incident;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class IncidentDamageWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00:00', config('app.timezone')));
    }

    public function test_authorised_staff_can_record_an_incident_with_booking_context_and_audit(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $bookingBefore = $booking->fresh()->getRawOriginal();

        $incident = app(CreateIncident::class)->handle($staff, [
            'centre_id' => $booking->centre_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            'issue_type' => 'health_and_safety',
            'title' => 'Wet floor near entrance',
            'description' => 'The floor was wet immediately after the session started.',
            'immediate_action' => 'Area isolated and warning sign placed.',
            'occurred_at' => '2026-10-06 09:05:00',
        ]);

        $this->assertModelExists($incident);
        $this->assertSame($booking->centre_id, $incident->centre_id);
        $this->assertSame($booking->id, $incident->booking_id);
        $this->assertSame($booking->resource_id, $incident->resource_id);
        $this->assertSame($staff->id, $incident->reported_by);
        $this->assertSame(OperationalIssueStatus::Open, $incident->status);
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertTrue(Activity::query()->where('subject_type', Incident::class)->where('subject_id', $incident->id)->where('event', 'incident.created')->exists());
    }

    public function test_authorised_staff_can_record_damage_with_equipment_context_without_assigning_liability(): void
    {
        $booking = $this->booking();
        $equipment = Equipment::factory()->for($booking->centre)->create();
        $staff = $this->assistant($booking->centre);

        $report = app(CreateDamageReport::class)->handle($staff, [
            'centre_id' => $booking->centre_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            'equipment_id' => $equipment->id,
            'description' => 'One goal net has a torn section.',
            'observed_at' => '2026-10-06 09:10:00',
        ]);

        $this->assertModelExists($report);
        $this->assertSame($booking->centre_id, $report->centre_id);
        $this->assertSame($booking->id, $report->booking_id);
        $this->assertSame($equipment->id, $report->equipment_id);
        $this->assertSame($staff->id, $report->reported_by);
        $this->assertSame(DamageResponsibility::Undetermined, $report->responsibility);
        $this->assertSame(DamageFinancialFollowUp::NotRequired, $report->financial_follow_up);
    }

    public function test_staff_cannot_create_cross_centre_or_invalid_context_records(): void
    {
        $booking = $this->booking();
        $foreignBooking = $this->booking();
        $staff = $this->assistant($booking->centre);

        $this->expectException(ValidationException::class);
        app(CreateIncident::class)->handle($staff, [
            'centre_id' => $booking->centre_id,
            'booking_id' => $foreignBooking->id,
            'resource_id' => $foreignBooking->resource_id,
            'issue_type' => 'other',
            'title' => 'Invalid context',
            'description' => 'This must not be saved.',
            'occurred_at' => '2026-10-06 09:00:00',
        ]);
    }

    public function test_staff_cannot_create_records_for_an_unassigned_centre(): void
    {
        $booking = $this->booking();
        $staff = User::factory()->create();
        $staff->assignRole('leisure-assistant');

        $this->expectException(AuthorizationException::class);
        app(CreateDamageReport::class)->handle($staff, [
            'centre_id' => $booking->centre_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            'description' => 'Unauthorised report.',
            'observed_at' => '2026-10-06 09:00:00',
        ]);
    }

    public function test_manager_can_review_damage_and_incident_through_forward_only_states(): void
    {
        $booking = $this->booking();
        $manager = $this->manager($booking->centre);
        $staff = $this->assistant($booking->centre);
        $incident = app(CreateIncident::class)->handle($staff, $this->incidentData($booking));
        $report = app(CreateDamageReport::class)->handle($staff, $this->damageData($booking));

        app(ReviewIncident::class)->handle($manager, $incident, ['status' => 'reviewed', 'follow_up_notes' => 'Manager inspected the area.']);
        app(ReviewIncident::class)->handle($manager, $incident->fresh(), ['status' => 'resolved', 'follow_up_notes' => 'Cleaning procedure updated.']);
        app(ReviewDamageReport::class)->handle($manager, $report, [
            'status' => 'reviewed',
            'responsibility' => DamageResponsibility::Undetermined->value,
            'financial_follow_up' => DamageFinancialFollowUp::ReviewRequired->value,
            'follow_up_notes' => 'Awaiting maintenance assessment; no charge has been made.',
        ]);

        $incident = $incident->fresh();
        $report = $report->fresh();
        $this->assertSame(OperationalIssueStatus::Resolved, $incident->status);
        $this->assertSame($manager->id, $incident->resolved_by);
        $this->assertSame(OperationalIssueStatus::Reviewed, $report->status);
        $this->assertSame(DamageFinancialFollowUp::ReviewRequired, $report->financial_follow_up);
        $this->assertSame(DamageResponsibility::Undetermined, $report->responsibility);
        $this->assertTrue(Activity::query()->where('subject_type', DamageReport::class)->where('subject_id', $report->id)->where('event', 'damage.status_changed')->exists());
    }

    public function test_customers_cannot_view_internal_issue_records(): void
    {
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $incident = app(CreateIncident::class)->handle($staff, $this->incidentData($booking));
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->assertFalse(Gate::forUser($customer)->allows('view', $incident));
    }

    /** @return array<string, mixed> */
    private function incidentData(Booking $booking): array
    {
        return [
            'centre_id' => $booking->centre_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            'issue_type' => 'facility_issue',
            'title' => 'Lighting issue',
            'description' => 'A light was not working.',
            'occurred_at' => '2026-10-06 09:00:00',
        ];
    }

    /** @return array<string, mixed> */
    private function damageData(Booking $booking): array
    {
        return [
            'centre_id' => $booking->centre_id,
            'booking_id' => $booking->id,
            'resource_id' => $booking->resource_id,
            'description' => 'A piece of equipment requires inspection.',
            'observed_at' => '2026-10-06 09:00:00',
        ];
    }

    private function booking(): Booking
    {
        return Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'starts_at' => '2026-10-06 09:00:00',
            'ends_at' => '2026-10-06 10:00:00',
        ]);
    }

    private function assistant(Centre $centre): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('leisure-assistant');
        $staff->assignedCentres()->attach($centre);

        return $staff;
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }
}
