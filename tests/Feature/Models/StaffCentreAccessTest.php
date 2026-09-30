<?php

namespace Tests\Feature\Models;

use App\Models\Centre;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class StaffCentreAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
    }

    public function test_user_can_be_associated_with_one_centre_and_is_assigned_to_centre(): void
    {
        $manager = User::factory()->create();
        $centre = Centre::factory()->create();
        $otherCentre = Centre::factory()->create();

        $manager->assignedCentres()->attach($centre);

        $this->assertTrue($manager->assignedCentres->contains($centre));
        $this->assertTrue($manager->isAssignedToCentre($centre));
        $this->assertFalse($manager->isAssignedToCentre($otherCentre));
        $this->assertDatabaseHas('centre_user', [
            'centre_id' => $centre->id,
            'user_id' => $manager->id,
        ]);
    }

    public function test_manager_can_be_associated_with_multiple_centres_without_changing_permissions(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $firstCentre = Centre::factory()->create();
        $secondCentre = Centre::factory()->create();

        $manager->assignedCentres()->attach([$firstCentre->id, $secondCentre->id]);

        $this->assertSame(
            [$firstCentre->id, $secondCentre->id],
            $manager->assignedCentres()->orderBy('centres.id')->pluck('centres.id')->all(),
        );
        $this->assertTrue(Gate::forUser($manager)->allows('reports.view'));
    }

    public function test_centre_can_have_multiple_assigned_users(): void
    {
        $centre = Centre::factory()->create();
        $manager = User::factory()->create();
        $leisureAssistant = User::factory()->create();

        $centre->assignedUsers()->attach([$manager->id, $leisureAssistant->id]);

        $this->assertSame(
            [$manager->id, $leisureAssistant->id],
            $centre->assignedUsers()->orderBy('users.id')->pluck('users.id')->all(),
        );
    }

    public function test_leisure_assistant_can_be_associated_with_multiple_centres_without_gaining_management_capability(): void
    {
        $leisureAssistant = User::factory()->create();
        $leisureAssistant->assignRole('leisure-assistant');
        $firstCentre = Centre::factory()->create();
        $secondCentre = Centre::factory()->create();

        $leisureAssistant->assignedCentres()->attach([$firstCentre->id, $secondCentre->id]);

        $this->assertTrue($leisureAssistant->isAssignedToCentre($firstCentre));
        $this->assertTrue($leisureAssistant->isAssignedToCentre($secondCentre));
        $this->assertTrue(Gate::forUser($leisureAssistant)->allows('attendance.manage'));
        $this->assertFalse(Gate::forUser($leisureAssistant)->allows('reports.view'));
    }

    public function test_duplicate_centre_assignment_is_rejected_by_the_database(): void
    {
        $user = User::factory()->create();
        $centre = Centre::factory()->create();

        $user->assignedCentres()->attach($centre);

        $this->expectException(QueryException::class);

        DB::table('centre_user')->insert([
            'centre_id' => $centre->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_centre_assignment_can_be_removed(): void
    {
        $user = User::factory()->create();
        $centre = Centre::factory()->create();
        $user->assignedCentres()->attach($centre);

        $user->assignedCentres()->detach($centre);

        $this->assertFalse($user->isAssignedToCentre($centre));
        $this->assertDatabaseMissing('centre_user', [
            'centre_id' => $centre->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_customer_centre_assignment_does_not_grant_staff_permissions_or_panel_access(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $centre = Centre::factory()->create();

        $customer->assignedCentres()->attach($centre);

        $this->assertTrue($customer->isAssignedToCentre($centre));
        $this->assertFalse(Gate::forUser($customer)->allows('reports.view'));
        $this->assertFalse(Gate::forUser($customer)->allows('attendance.manage'));

        $this->actingAs($customer)->get('/management')->assertForbidden();
        $this->actingAs($customer)->get('/operations')->assertForbidden();
    }

    public function test_database_rejects_assignment_with_unknown_user(): void
    {
        $centre = Centre::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('centre_user')->insert([
            'centre_id' => $centre->id,
            'user_id' => 999999,
        ]);
    }

    public function test_database_rejects_assignment_with_unknown_centre(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('centre_user')->insert([
            'centre_id' => 999999,
            'user_id' => $user->id,
        ]);
    }
}
