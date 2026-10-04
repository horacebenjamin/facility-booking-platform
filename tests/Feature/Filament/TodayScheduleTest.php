<?php

namespace Tests\Feature\Filament;

use App\Enums\BookingStatus;
use App\Filament\Operations\Pages\TodaySchedule;
use App\Models\Booking;
use App\Models\BookingEquipment;
use App\Models\Centre;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TodayScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-04 16:45:00', config('app.timezone')));
    }

    public function test_operations_dashboard_contains_a_single_schedule_navigation_item_and_assigned_centre_page(): void
    {
        $centre = Centre::factory()->create(['name' => 'Riverside Activity Centre']);
        $staff = $this->assistant($centre);

        $this->actingAs($staff)->get('/operations')->assertOk()
            ->assertSee('Today’s Schedule')->assertSee(TodaySchedule::getUrl(panel: 'operations'), false);
        $this->get(TodaySchedule::getUrl(panel: 'operations'))->assertOk()->assertSee('Riverside Activity Centre');
        $this->assertSame(1, count(array_filter(Filament::getPanel('operations')->getPages(), fn (string $page): bool => $page === TodaySchedule::class)));
    }

    public function test_schedule_navigation_and_direct_access_require_booking_permission(): void
    {
        $staff = $this->assistant(Centre::factory()->create());
        $staff->roles()->firstOrFail()->revokePermissionTo('bookings.view');

        $this->actingAs($staff)->get('/operations')->assertOk()->assertDontSee('Today’s Schedule');
        $this->get(TodaySchedule::getUrl(panel: 'operations'))->assertForbidden();
    }

    #[TestWith(['customer'])]
    #[TestWith(['manager'])]
    public function test_non_operations_roles_cannot_open_schedule_even_when_assigned(string $role): void
    {
        $staff = User::factory()->create();
        $staff->assignRole($role);
        $staff->assignedCentres()->attach(Centre::factory()->create());

        $this->actingAs($staff)->get(TodaySchedule::getUrl(panel: 'operations'))->assertForbidden();
    }

    public function test_assistant_can_switch_assigned_centres_without_leaking_customer_data(): void
    {
        $first = $this->booking();
        $second = $this->booking();
        $foreign = $this->booking();
        $staff = $this->assistant($first->centre);
        $staff->assignedCentres()->attach($second->centre);
        $this->actingAs($staff);

        Livewire::test(TodaySchedule::class)
            ->set('centreId', $first->centre_id)
            ->assertSee('Now')->assertSee('Next')
            ->assertSee($first->reference)->assertSee($first->customer->name)
            ->assertDontSee($first->customer->email)->assertDontSee($foreign->reference)
            ->assertDontSee('Booking arrived')->assertDontSee('Mark no-show')
            ->set('centreId', $second->centre_id)
            ->assertSee($second->reference)->assertDontSee($first->reference);
    }

    public function test_query_string_centre_forgery_is_denied(): void
    {
        $staff = $this->assistant(Centre::factory()->create());
        $foreign = Centre::factory()->create();

        $this->actingAs($staff)->get(TodaySchedule::getUrl(parameters: ['centreId' => $foreign->id], panel: 'operations'))->assertForbidden();
    }

    public function test_livewire_centre_forgery_is_denied(): void
    {
        $staff = $this->assistant(Centre::factory()->create());
        $foreign = Centre::factory()->create();
        $this->actingAs($staff);

        Livewire::test(TodaySchedule::class)->set('centreId', $foreign->id)->assertForbidden();
    }

    public function test_guest_is_redirected_to_operations_login(): void
    {
        $this->get(TodaySchedule::getUrl(panel: 'operations'))->assertRedirect(route('filament.operations.auth.login'));
    }

    public function test_operational_labels_escape_customer_resource_and_equipment_text(): void
    {
        $booking = $this->booking();
        $booking->customer->update(['name' => '<script>customer</script>']);
        $booking->resource->update(['name' => '<script>resource</script>']);
        $request = BookingEquipment::factory()->create(['booking_id' => $booking->id, 'requested_quantity' => 7]);
        $request->equipment->update(['name' => '<script>equipment</script>']);
        $this->actingAs($this->assistant($booking->centre));

        $this->get(TodaySchedule::getUrl(panel: 'operations'))
            ->assertSee('&lt;script&gt;customer&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;resource&lt;/script&gt;', false)
            ->assertSee('&lt;script&gt;equipment&lt;/script&gt;', false)
            ->assertSee('7')
            ->assertDontSee('<script>customer</script>', false)
            ->assertDontSee('<script>resource</script>', false)
            ->assertDontSee('<script>equipment</script>', false);
    }

    public function test_refresh_rechecks_revoked_centre_assignment(): void
    {
        $centre = Centre::factory()->create();
        $staff = $this->assistant($centre);
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class);
        $staff->assignedCentres()->detach($centre);

        $page->call('refreshSchedule')->assertForbidden();
    }

    public function test_refresh_renders_current_booking_data_without_creating_records(): void
    {
        $booking = $this->booking();
        $this->actingAs($this->assistant($booking->centre));
        $page = Livewire::test(TodaySchedule::class)->assertSee($booking->reference);
        $booking->update(['status' => BookingStatus::Rejected]);

        $page->call('refreshSchedule')->assertDontSee($booking->reference)->assertSee('No scheduled bookings');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('allocation_occupancies', 0);
    }

    public function test_no_assignment_and_empty_day_have_clear_states(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('leisure-assistant');
        $this->actingAs($staff);
        Livewire::test(TodaySchedule::class)->assertSee('No assigned centres');
        $staff->assignedCentres()->attach(Centre::factory()->create());

        Livewire::test(TodaySchedule::class)->assertSee('No scheduled bookings');
    }

    public function test_invalid_selected_date_has_validation_feedback(): void
    {
        $this->actingAs($this->assistant(Centre::factory()->create()));

        Livewire::test(TodaySchedule::class)->set('date', '2026-02-30')->assertHasErrors(['date'])
            ->set('date', '')->assertHasErrors(['date'])
            ->assertSee('Date')
            ->set('date', '2026-10-04')->assertHasNoErrors()->assertSee('No scheduled bookings');
    }

    public function test_refresh_rechecks_revoked_operations_role(): void
    {
        $staff = $this->assistant(Centre::factory()->create());
        $this->actingAs($staff);
        $page = Livewire::test(TodaySchedule::class);
        $staff->removeRole('leisure-assistant');

        $page->call('refreshSchedule')->assertForbidden();
    }

    private function booking(): Booking
    {
        return Booking::factory()->create(['starts_at' => '2026-10-04 17:00:00', 'ends_at' => '2026-10-04 18:00:00', 'status' => BookingStatus::Confirmed]);
    }

    private function assistant(Centre $centre): User
    {
        $staff = User::factory()->create();
        $staff->assignRole('leisure-assistant');
        $staff->assignedCentres()->attach($centre);

        return $staff;
    }
}
