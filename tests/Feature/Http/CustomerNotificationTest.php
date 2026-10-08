<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomerNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
    }

    public function test_guests_and_unverified_users_cannot_access_notifications_or_mark_them_read(): void
    {
        $id = (string) Str::uuid();
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->patch(route('notifications.read', $id))->assertRedirect(route('login'));
        $user = User::factory()->unverified()->create();
        $user->assignRole('customer');
        $this->actingAs($user)->get(route('notifications.index'))->assertRedirect(route('verification.notice'));
        $this->patch(route('notifications.read', $id))->assertRedirect(route('verification.notice'));
    }

    public function test_customer_sees_only_owned_customer_safe_notification_content(): void
    {
        $customer = $this->customer();
        $owned = $this->notification($customer);
        $this->notification($this->customer(), ['title' => 'Another customer private booking']);
        $this->withoutVite()->actingAs($customer)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->component('notifications/Index')
                ->has('notifications.data', 1)->where('notifications.data.0.id', $owned->id)
                ->where('bookingTimezone', 'Europe/London')
                ->where('notifications.data.0.title', 'Booking requested')
                ->where('notifications.data.0.body', 'Your booking is awaiting review.')
                ->where('notifications.data.0.action_url', '/bookings')
                ->where('notifications.data.0.read_at', null)->where('unread_count', 1)
                ->missing('notifications.data.0.provider_reference')->missing('notifications.data.0.staff_notes')
                ->missing('notifications.data.0.notifiable_id')->missing('notifications.data.0.data'));
        $this->assertNull($owned->fresh()->read_at);
    }

    public function test_customer_cannot_mark_another_customer_notification_read(): void
    {
        $notification = $this->notification($this->customer());
        $this->actingAs($this->customer())->patch(route('notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
        $this->patch(route('notifications.read', (string) Str::uuid()))->assertNotFound();
    }

    public function test_marking_read_is_idempotent_and_ignores_forged_recipient_and_state(): void
    {
        $this->freezeSecond();
        $customer = $this->customer();
        $notification = $this->notification($customer);
        $originalData = $notification->data;
        $this->actingAs($customer)->patch(route('notifications.read', $notification->id), [
            'read_at' => '2000-01-01', 'notifiable_id' => $this->customer()->id,
            'data' => ['title' => 'Paid'],
        ])->assertRedirect(route('notifications.index'));
        $firstRead = $notification->fresh()->read_at;
        $this->assertNotNull($firstRead);
        $this->assertTrue($firstRead->equalTo(now()));
        $this->travel(1)->hour();
        $this->patch(route('notifications.read', $notification->id))->assertRedirect(route('notifications.index'));
        $this->assertTrue($firstRead->equalTo($notification->fresh()->read_at));
        $this->assertSame($customer->id, $notification->fresh()->notifiable_id);
        $this->assertSame($originalData, $notification->fresh()->data);
    }

    public function test_users_without_customer_area_permission_are_forbidden(): void
    {
        $user = User::factory()->create();
        $notification = $this->notification($user);
        $this->actingAs($user)->get(route('notifications.index'))->assertForbidden();
        $this->patch(route('notifications.read', $notification->id))->assertForbidden();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_external_notification_action_urls_are_not_exposed(): void
    {
        $customer = $this->customer();
        $this->notification($customer, ['action_url' => 'https://external.example/financial-data']);
        $this->withoutVite()->actingAs($customer)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.action_url', null));
    }

    public function test_notifications_are_paginated_and_unread_count_includes_other_pages(): void
    {
        $customer = $this->customer();
        for ($index = 0; $index < 51; $index++) {
            $this->notification($customer);
        }
        $this->withoutVite()->actingAs($customer)->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 50)
                ->where('notifications.total', 51)->where('notifications.last_page', 2)->where('unread_count', 51));
        $this->get(route('notifications.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('notifications.current_page', 2));
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    /** @param array<string, mixed> $data */
    private function notification(User $customer, array $data = []): DatabaseNotification
    {
        return $customer->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'booking_request_received',
            'data' => [...[
                'type' => 'booking_request_received', 'title' => 'Booking requested',
                'body' => 'Your booking is awaiting review.', 'action_label' => 'View bookings',
                'action_url' => '/bookings', 'occurred_at' => now()->toISOString(),
                'provider_reference' => 'internal-provider-value', 'staff_notes' => 'Private audit context',
            ], ...$data],
        ]);
    }
}
