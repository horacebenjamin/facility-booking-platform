<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->withTwoFactor()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        $activity = Activity::query()->sole();

        $this->assertSame('profile', $activity->log_name);
        $this->assertSame('profile.updated', $activity->event);
        $this->assertSame('Profile updated', $activity->description);
        $this->assertSame($user->getMorphClass(), $activity->causer_type);
        $this->assertSame($user->id, $activity->causer_id);
        $this->assertSame($user->getMorphClass(), $activity->subject_type);
        $this->assertSame($user->id, $activity->subject_id);
        $this->assertSame(['changed_fields' => ['name', 'email']], $activity->properties?->all());
        $this->assertSame([], $activity->attribute_changes?->all());
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_update_does_not_create_an_activity_when_nothing_changes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertDatabaseEmpty('activity_log');
    }

    public function test_unauthenticated_profile_update_does_not_create_an_activity(): void
    {
        $this->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseEmpty('activity_log');
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
