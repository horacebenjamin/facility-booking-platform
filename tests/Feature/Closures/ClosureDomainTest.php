<?php

namespace Tests\Feature\Closures;

use App\Actions\CreateAvailabilityBlock;
use App\Models\Centre;
use App\Models\Facility;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ClosureDomainTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
    }

    #[TestWith(['centre'])]
    #[TestWith(['facility'])]
    #[TestWith(['resource'])]
    public function test_assigned_manager_creates_exactly_one_scope_and_records_responsibility(string $scope): void
    {
        $resource = Resource::factory()->create();
        $centre = $resource->facility->centre;
        $manager = $this->manager($centre);
        $data = $this->payload($centre);
        $data['scope'] = $scope;
        if ($scope !== 'centre') {
            $data['facility_id'] = $resource->facility_id;
        }
        if ($scope === 'resource') {
            $data['resource_id'] = $resource->id;
        }

        $block = app(CreateAvailabilityBlock::class)->handle($manager, $data);

        $this->assertSame($scope === 'centre' ? $centre->id : null, $block->centre_id);
        $this->assertSame($scope === 'facility' ? $resource->facility_id : null, $block->facility_id);
        $this->assertSame($scope === 'resource' ? $resource->id : null, $block->resource_id);
        $this->assertSame('Weather closure', $block->reason);
        $this->assertSame($manager->id, $block->created_by);
        $this->assertNull($block->ended_at);
        $this->assertDatabaseCount('availability_blocks', 1);
        $this->assertDatabaseCount('bookings', 0);
        $audit = Activity::query()->where('event', 'closure.created')->where('subject_id', $block->id)->sole();
        $this->assertSame($manager->id, $audit->causer_id);
        $this->assertSame($centre->id, $audit->properties['centre_id']);
        $this->assertSame(0, $audit->properties['affected_count']);
    }

    #[TestWith(['school_use'])]
    #[TestWith(['exam'])]
    #[TestWith(['maintenance'])]
    #[TestWith(['health_and_safety'])]
    #[TestWith(['weather'])]
    #[TestWith(['internal_use'])]
    #[TestWith(['manager_blockout'])]
    #[TestWith(['other'])]
    public function test_supported_type_is_persisted(string $type): void
    {
        $centre = Centre::factory()->create();
        $block = app(CreateAvailabilityBlock::class)->handle($this->manager($centre), [...$this->payload($centre), 'type' => $type]);

        $this->assertSame($type, $block->type->value);
    }

    public function test_manager_cannot_create_in_an_unassigned_centre(): void
    {
        $manager = $this->manager(Centre::factory()->create());
        $this->expectException(AuthorizationException::class);

        app(CreateAvailabilityBlock::class)->handle($manager, $this->payload(Centre::factory()->create()));
    }

    public function test_manager_without_closure_capability_is_denied(): void
    {
        $centre = Centre::factory()->create();
        $manager = $this->manager($centre);
        $manager->roles()->firstOrFail()->revokePermissionTo('closures.manage');
        $this->expectException(AuthorizationException::class);

        app(CreateAvailabilityBlock::class)->handle($manager, $this->payload($centre));
    }

    #[TestWith(['customer'])]
    #[TestWith(['leisure-assistant'])]
    public function test_non_manager_with_capability_and_assignment_is_still_denied(string $role): void
    {
        $centre = Centre::factory()->create();
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo('closures.manage');
        $user->assignedCentres()->attach($centre);
        $this->expectException(AuthorizationException::class);

        app(CreateAvailabilityBlock::class)->handle($user, $this->payload($centre));
    }

    #[TestWith(['type', 'imaginary'])]
    #[TestWith(['centre_id', null])]
    #[TestWith(['scope', 'allocation_unit'])]
    #[TestWith(['reason', '   '])]
    #[TestWith(['reason', null])]
    #[TestWith(['starts_at', '2026-02-30 18:00:00'])]
    #[TestWith(['starts_at', 'next Monday'])]
    #[TestWith(['ends_at', '2026-10-05 17:00:00'])]
    #[TestWith(['ends_at', '2026-10-05 18:00:00'])]
    public function test_invalid_input_does_not_persist_a_block(string $field, mixed $value): void
    {
        $centre = Centre::factory()->create();
        $manager = $this->manager($centre);

        try {
            app(CreateAvailabilityBlock::class)->handle($manager, [...$this->payload($centre), $field => $value]);
            $this->fail('Invalid closure input was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }

        $this->assertDatabaseCount('availability_blocks', 0);
    }

    public function test_reason_over_maximum_length_is_rejected(): void
    {
        $centre = Centre::factory()->create();
        $this->expectException(ValidationException::class);

        app(CreateAvailabilityBlock::class)->handle($this->manager($centre), [...$this->payload($centre), 'reason' => str_repeat('x', 256)]);
    }

    public function test_foreign_facility_cannot_be_smuggled_into_an_assigned_centre(): void
    {
        $centre = Centre::factory()->create();
        $foreign = Facility::factory()->create();
        $this->expectException(ValidationException::class);

        app(CreateAvailabilityBlock::class)->handle($this->manager($centre), [...$this->payload($centre), 'scope' => 'facility', 'facility_id' => $foreign->id]);
    }

    public function test_resource_must_belong_to_the_selected_facility(): void
    {
        $centre = Centre::factory()->create();
        $selectedFacility = Facility::factory()->for($centre)->create();
        $otherFacility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($otherFacility)->create();
        $this->expectException(ValidationException::class);

        app(CreateAvailabilityBlock::class)->handle($this->manager($centre), [...$this->payload($centre), 'scope' => 'resource', 'facility_id' => $selectedFacility->id, 'resource_id' => $resource->id]);
    }

    public function test_missing_scope_target_is_rejected(): void
    {
        $centre = Centre::factory()->create();
        $this->expectException(ValidationException::class);

        app(CreateAvailabilityBlock::class)->handle($this->manager($centre), [...$this->payload($centre), 'scope' => 'resource']);
    }

    public function test_client_supplied_responsibility_and_effective_end_cannot_override_creation(): void
    {
        $centre = Centre::factory()->create();
        $manager = $this->manager($centre);
        $foreign = User::factory()->create();
        $block = app(CreateAvailabilityBlock::class)->handle($manager, [...$this->payload($centre), 'created_by' => $foreign->id, 'ended_by' => $foreign->id, 'ended_at' => '2026-10-05 17:00:00']);

        $this->assertSame($manager->id, $block->created_by);
        $this->assertNull($block->ended_at);
        $this->assertNull($block->ended_by);
    }

    public function test_centre_scope_rejects_a_second_scope_target(): void
    {
        $resource = Resource::factory()->create();
        $centre = $resource->facility->centre;
        $this->expectException(ValidationException::class);

        app(CreateAvailabilityBlock::class)->handle($this->manager($centre), [...$this->payload($centre), 'resource_id' => $resource->id]);
    }

    /** @return array<string, mixed> */
    private function payload(Centre $centre): array
    {
        return ['centre_id' => $centre->id, 'scope' => 'centre', 'type' => 'weather', 'starts_at' => '2026-10-05 18:00:00', 'ends_at' => '2026-10-05 19:00:00', 'reason' => ' Weather closure '];
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }
}
