<?php

namespace Tests\Feature\Filament;

use App\Actions\CreateAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\AvailabilityBlockResource;
use App\Filament\Resources\AvailabilityBlocks\Pages\CreateAvailabilityBlock as CreateClosurePage;
use App\Filament\Resources\AvailabilityBlocks\Pages\ListAvailabilityBlocks;
use App\Filament\Resources\AvailabilityBlocks\Pages\ViewAvailabilityBlock;
use App\Filament\Resources\AvailabilityBlocks\RelationManagers\ImpactsRelationManager;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Resource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ClosureManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2026-10-05 09:00:00', config('app.timezone')));
        Filament::setCurrentPanel(Filament::getPanel('management'));
    }

    public function test_manager_has_discoverable_centre_scoped_closures_and_protected_direct_urls(): void
    {
        $resource = Resource::factory()->create();
        $manager = $this->manager($resource->facility->centre);
        $own = $this->block($manager, $resource);
        $foreignResource = Resource::factory()->create();
        $foreign = $this->block($this->manager($foreignResource->facility->centre), $foreignResource);
        $this->actingAs($manager)->get('/management')->assertOk()->assertSee('Closures');
        Livewire::test(ListAvailabilityBlocks::class)->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$foreign]);
        $this->get(AvailabilityBlockResource::getUrl('view', ['record' => $own]))->assertOk()->assertSee('Repair floor');
        $this->get(AvailabilityBlockResource::getUrl('view', ['record' => $foreign]))->assertNotFound();
        $this->assertFalse(AvailabilityBlockResource::canEdit($own));
        $this->assertFalse(AvailabilityBlockResource::canDelete($own));
    }

    public function test_create_form_uses_action_and_rejects_forged_hierarchy(): void
    {
        $resource = Resource::factory()->create();
        $manager = $this->manager($resource->facility->centre);
        $this->actingAs($manager);
        Livewire::test(CreateClosurePage::class)->fillForm($this->payload($resource))
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseHas('availability_blocks', ['resource_id' => $resource->id, 'created_by' => $manager->id]);
        $foreign = Resource::factory()->create();
        Livewire::test(CreateClosurePage::class)->fillForm([...$this->payload($resource), 'facility_id' => $foreign->facility_id, 'resource_id' => $foreign->id])
            ->call('create')->assertHasFormErrors();
        $this->assertDatabaseCount('availability_blocks', 1);
    }

    #[TestWith(['customer'])]
    #[TestWith(['leisure-assistant'])]
    public function test_non_managers_cannot_use_management_closure_routes(string $role): void
    {
        $resource = Resource::factory()->create();
        $block = $this->block($this->manager($resource->facility->centre), $resource);
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->assignedCentres()->attach($resource->facility->centre_id);
        $this->actingAs($user)->get(AvailabilityBlockResource::getUrl('index'))->assertForbidden();
        $this->get(AvailabilityBlockResource::getUrl('view', ['record' => $block]))->assertForbidden();
    }

    public function test_guest_is_redirected_to_management_login_and_permission_revocation_denies_page(): void
    {
        $this->get(AvailabilityBlockResource::getUrl('index'))->assertRedirect(route('filament.management.auth.login'));
        $centre = Centre::factory()->create();
        $manager = $this->manager($centre);
        $manager->roles()->firstOrFail()->revokePermissionTo('closures.manage');
        $this->actingAs($manager)->get(AvailabilityBlockResource::getUrl('index'))->assertForbidden();
    }

    public function test_impacts_show_contact_followup_and_explicit_operational_resolution_with_separate_finance(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $manager = $this->manager($booking->centre);
        $block = $this->block($manager, $booking->resource);
        $impact = $block->impacts()->sole();
        $bookingBefore = $booking->fresh()->getRawOriginal();
        $paymentIdsBefore = $booking->payments()->pluck('id')->all();
        $invoiceLineIdsBefore = $booking->invoiceLines()->pluck('id')->all();
        $this->actingAs($manager);
        $component = Livewire::test(ImpactsRelationManager::class, ['ownerRecord' => $block, 'pageClass' => ViewAvailabilityBlock::class])
            ->assertCanSeeTableRecords([$impact])->assertSee($booking->reference)->assertSee($booking->customer->name)->assertSee($booking->customer->email)
            ->assertTableColumnExists('booking.reference', fn (TextColumn $column): bool => $column->getUrl() === BookingResource::getUrl('view', ['record' => $booking]), $impact)
            ->assertDontSee('provider_payment_intent_id')->assertTableActionHidden('resolve', $impact)
            ->callTableAction('review', $impact, data: ['operational_requirement' => 'no_action_required', 'financial_requirement' => 'refund_required', 'communication_required' => true, 'review_notes' => 'Venue reopens before the session. Financial follow-up remains separate.'])
            ->assertHasNoTableActionErrors()
            ->callTableAction('contactCustomer', $impact, data: ['communication_notes' => 'Customer informed manually.'])->assertHasNoTableActionErrors();
        $component->mountTableAction('resolve', $impact)
            ->assertMountedActionModalSee('Resolve')
            ->fillForm(['resolution_notes' => 'Operational disruption is resolved.'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors()->assertDispatched('closure-impact-resolved')->assertSee('Resolved');
        $this->assertSame('resolved', $impact->fresh()->status->value);
        $this->assertSame('refund_required', $impact->fresh()->financial_requirement->value);
        $this->assertSame($bookingBefore, $booking->fresh()->getRawOriginal());
        $this->assertSame($paymentIdsBefore, $booking->payments()->pluck('id')->all());
        $this->assertSame($invoiceLineIdsBefore, $booking->invoiceLines()->pluck('id')->all());
        $this->get(BookingResource::getUrl('view', ['record' => $booking]))->assertOk()->assertSee($booking->reference);
    }

    public function test_resolving_an_impact_refreshes_the_closure_unresolved_count(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $manager = $this->manager($booking->centre);
        $block = $this->block($manager, $booking->resource);
        $impact = $block->impacts()->sole();
        $this->actingAs($manager);
        $page = Livewire::test(ViewAvailabilityBlock::class, ['record' => $block->getRouteKey()])
            ->assertSee('Unresolved bookings')
            ->assertSee('1');

        $impact->forceFill(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $manager->id, 'resolution_notes' => 'Resolved for refresh test.'])->save();

        $page->dispatch('closure-impact-resolved')
            ->assertSet('record.unresolved_impacts_count', 0)
            ->assertSee('0');
    }

    public function test_end_requires_confirmation_and_preserves_historical_record(): void
    {
        $resource = Resource::factory()->create();
        $manager = $this->manager($resource->facility->centre);
        $block = $this->block($manager, $resource);
        $this->actingAs($manager);
        Livewire::test(ViewAvailabilityBlock::class, ['record' => $block->getRouteKey()])
            ->mountAction('endClosure')->assertMountedActionModalSee('End')
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertNotNull($block->fresh()->ended_at);
        $this->assertDatabaseCount('availability_blocks', 1);
    }

    public function test_foreign_closure_cannot_be_mounted_as_an_impact_owner(): void
    {
        $resource = Resource::factory()->create();
        $block = $this->block($this->manager($resource->facility->centre), $resource);
        $manager = $this->manager(Centre::factory()->create());
        $this->actingAs($manager);

        Livewire::test(ImpactsRelationManager::class, ['ownerRecord' => $block, 'pageClass' => ViewAvailabilityBlock::class])->assertForbidden();
    }

    public function test_foreign_impact_id_cannot_be_reviewed_from_an_authorised_owner(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $manager = $this->manager($booking->centre);
        $block = $this->block($manager, $booking->resource);
        $foreign = Booking::factory()->create(['status' => 'confirmed', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 11:00:00']);
        $foreignBlock = $this->block($this->manager($foreign->centre), $foreign->resource);
        $impact = $foreignBlock->impacts()->sole();
        $this->actingAs($manager);

        try {
            Livewire::test(ImpactsRelationManager::class, ['ownerRecord' => $block, 'pageClass' => ViewAvailabilityBlock::class])
                ->callTableAction('review', $impact->id, data: ['operational_requirement' => 'no_action_required', 'financial_requirement' => 'not_required', 'communication_required' => false, 'review_notes' => 'Forged review']);
            $this->fail('A foreign impact was available to the closure action.');
        } catch (ActionNotResolvableException $exception) {
            $this->assertStringContainsString('no longer exists', $exception->getMessage());
        }

        $this->assertNull($impact->fresh()->review_notes);
        $this->assertSame('unresolved', $impact->fresh()->status->value);
    }

    public function test_revoked_assignment_denies_an_already_mounted_closure_action(): void
    {
        $resource = Resource::factory()->create();
        $manager = $this->manager($resource->facility->centre);
        $block = $this->block($manager, $resource);
        $this->actingAs($manager);
        $component = Livewire::test(ViewAvailabilityBlock::class, ['record' => $block->getRouteKey()])->mountAction('endClosure');
        $manager->assignedCentres()->detach();

        $component->callMountedAction()->assertForbidden();

        $this->assertNull($block->fresh()->ended_at);
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }

    /** @return array<string, mixed> */
    private function payload(Resource $resource): array
    {
        return ['centre_id' => $resource->facility->centre_id, 'scope' => 'resource', 'facility_id' => $resource->facility_id, 'resource_id' => $resource->id, 'type' => 'maintenance', 'starts_at' => '2026-10-06 10:00:00', 'ends_at' => '2026-10-06 12:00:00', 'reason' => 'Repair floor'];
    }

    private function block(User $manager, Resource $resource): AvailabilityBlock
    {
        return app(CreateAvailabilityBlock::class)->handle($manager, $this->payload($resource));
    }
}
