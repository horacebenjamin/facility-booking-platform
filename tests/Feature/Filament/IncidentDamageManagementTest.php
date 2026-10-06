<?php

namespace Tests\Feature\Filament;

use App\Actions\CreateIncident;
use App\Enums\BookingStatus;
use App\Filament\Operations\Pages\TodaySchedule;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentDamageManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-06 09:00:00', config('app.timezone')));
    }

    public function test_management_can_only_see_assigned_centre_issues_and_can_review_them(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('management'));
        $booking = $this->booking();
        $foreignBooking = $this->booking();
        $manager = $this->manager($booking->centre);
        $staff = $this->assistant($booking->centre);
        $own = app(CreateIncident::class)->handle($staff, $this->incidentData($booking));
        $foreignStaff = $this->assistant($foreignBooking->centre);
        $foreign = app(CreateIncident::class)->handle($foreignStaff, $this->incidentData($foreignBooking));
        $this->actingAs($manager);

        Livewire::test(ListIncidents::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
        $this->get(IncidentResource::getUrl('view', ['record' => $own]))->assertOk()->assertSee($own->title);
        $this->get(IncidentResource::getUrl('view', ['record' => $foreign]))->assertNotFound();
        Livewire::test(ViewIncident::class, ['record' => $own->getRouteKey()])
            ->mountAction('review')
            ->fillForm(['status' => 'reviewed', 'follow_up_notes' => 'Manager reviewed the incident.'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertSee('Reviewed')
            ->assertDontSee('Open')
            ->assertSee('Manager reviewed the incident.')
            ->assertSee('Manager');
        $this->assertSame('reviewed', $own->fresh()->status->value);
    }

    public function test_operations_can_report_incident_and_damage_from_session_context(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('operations'));
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $this->actingAs($staff);

        Livewire::test(TodaySchedule::class)
            ->set('centreId', $booking->centre_id)
            ->set('date', '2026-10-06')
            ->mountAction('reportIncident', ['booking' => $booking->id])
            ->fillForm(['issue_type' => 'other', 'title' => 'Session incident', 'description' => 'Recorded from schedule context.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();
        Livewire::test(TodaySchedule::class)
            ->set('centreId', $booking->centre_id)
            ->set('date', '2026-10-06')
            ->mountAction('reportDamage', ['booking' => $booking->id])
            ->fillForm(['description' => 'Damage recorded from schedule context.'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('incidents', ['booking_id' => $booking->id, 'reported_by' => $staff->id]);
        $this->assertDatabaseHas('damage_reports', ['booking_id' => $booking->id, 'reported_by' => $staff->id]);
    }

    public function test_customers_cannot_use_internal_issue_urls(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('management'));
        $booking = $this->booking();
        $staff = $this->assistant($booking->centre);
        $incident = app(CreateIncident::class)->handle($staff, $this->incidentData($booking));
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get(IncidentResource::getUrl('view', ['record' => $incident]))->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function incidentData(Booking $booking): array
    {
        return ['centre_id' => $booking->centre_id, 'booking_id' => $booking->id, 'resource_id' => $booking->resource_id, 'issue_type' => 'other', 'title' => 'Issue', 'description' => 'Issue details.', 'occurred_at' => '2026-10-06 09:00:00'];
    }

    private function booking(): Booking
    {
        return Booking::factory()->create(['status' => BookingStatus::Confirmed, 'starts_at' => '2026-10-06 09:00:00', 'ends_at' => '2026-10-06 10:00:00']);
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
