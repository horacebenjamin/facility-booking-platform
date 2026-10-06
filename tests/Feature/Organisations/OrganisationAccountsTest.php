<?php

namespace Tests\Feature\Organisations;

use App\Actions\ApproveBooking;
use App\Actions\CreateBookingRequest;
use App\Actions\CreateOrganisation;
use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrganisationRole;
use App\Exceptions\InvoiceUnavailable;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\AllocationOccupancy;
use App\Models\AllocationUnit;
use App\Models\Booking;
use App\Models\BookingPriceLine;
use App\Models\BookingPriceSnapshot;
use App\Models\BookingSeries;
use App\Models\Centre;
use App\Models\CustomerInvoiceTerms;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\Payment;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Services\LifecycleNotificationPayload;
use App\Services\OperationalOccupancyCalculator;
use App\Services\PaymentService;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class OrganisationAccountsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
        $this->travelTo('2026-10-01 12:00:00');
        config([
            'booking.cancellation.minimum_notice_minutes' => 0,
            'booking.recurrence_timezone' => 'Europe/London',
            'payments.stripe_live_mode' => false,
        ]);
    }

    public function test_customer_creates_an_organisation_and_becomes_its_sole_owner(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($customer)->post(route('organisations.store'), ['name' => '  Riverside Rowing Club  ']);

        $organisation = Organisation::query()->sole();
        $response->assertRedirect(route('organisations.show', $organisation));
        $this->assertSame('Riverside Rowing Club', $organisation->name);
        $membership = OrganisationMembership::query()->sole();
        $this->assertSame($customer->id, $membership->user_id);
        $this->assertSame(OrganisationRole::Owner, $membership->role);
        $audit = Activity::query()->where('event', 'organisation.created')->sole();
        $this->assertSame($customer->id, $audit->causer_id);
        $this->assertSame($membership->id, $audit->getProperty('membership_id'));
    }

    public function test_organisation_creation_is_atomic_with_its_initial_owner(): void
    {
        $customer = $this->customer();
        Event::listen('eloquent.creating: '.OrganisationMembership::class, function (): void {
            throw new RuntimeException('Simulated membership failure.');
        });

        try {
            app(CreateOrganisation::class)->handle($customer, 'Half-created Club');
            $this->fail('Organisation creation should have failed.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseEmpty('organisations');
        $this->assertDatabaseEmpty('organisation_memberships');
    }

    public function test_organisation_creation_requires_a_customer_and_a_name(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($manager)->post(route('organisations.store'), ['name' => 'Staff Club'])->assertForbidden();
        $this->actingAs($this->customer())->post(route('organisations.store'), ['name' => '   '])->assertSessionHasErrors('name');
        $this->assertDatabaseEmpty('organisations');
    }

    public function test_owner_adds_an_existing_customer_by_email_and_duplicates_are_rejected(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $colleague = $this->customer();

        $this->actingAs($owner)->post(route('organisations.memberships.store', $organisation), [
            'email' => '  '.strtoupper($colleague->email).' ',
            'role' => OrganisationRole::Finance->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(OrganisationRole::Finance, $colleague->organisationMembership($organisation)?->role);
        $audit = Activity::query()->where('event', 'organisation.member_added')->sole();
        $this->assertSame($owner->id, $audit->causer_id);
        $this->assertSame($colleague->id, $audit->getProperty('member_user_id'));

        $this->actingAs($owner)->post(route('organisations.memberships.store', $organisation), [
            'email' => $colleague->email,
            'role' => OrganisationRole::Admin->value,
        ])->assertSessionHasErrors('email');
        $this->assertSame(2, $organisation->memberships()->count());
        $this->assertSame(OrganisationRole::Finance, $colleague->organisationMembership($organisation)?->role);
    }

    public function test_member_addition_requires_an_existing_customer_account(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $manager = User::factory()->create();
        $manager->assignRole('manager');

        $this->actingAs($owner)->post(route('organisations.memberships.store', $organisation), [
            'email' => 'nobody@example.test',
            'role' => OrganisationRole::Member->value,
        ])->assertSessionHasErrors('email');
        $this->actingAs($owner)->post(route('organisations.memberships.store', $organisation), [
            'email' => $manager->email,
            'role' => OrganisationRole::Member->value,
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, $organisation->memberships()->count());
    }

    public function test_only_owners_and_admins_manage_members_and_admins_cannot_manage_owners(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $admin = $this->member($organisation, OrganisationRole::Admin);
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $finance = $this->member($organisation, OrganisationRole::Finance);
        $candidate = $this->customer();
        $ownerMembership = $owner->organisationMembership($organisation);

        foreach ([$bookingManager, $finance] as $actor) {
            $this->actingAs($actor)->post(route('organisations.memberships.store', $organisation), [
                'email' => $candidate->email,
                'role' => OrganisationRole::Member->value,
            ])->assertForbidden();
        }
        $this->actingAs($admin)->post(route('organisations.memberships.store', $organisation), [
            'email' => $candidate->email,
            'role' => OrganisationRole::Owner->value,
        ])->assertForbidden();
        $this->actingAs($admin)->patch(route('organisations.memberships.update', [$organisation, $ownerMembership]), [
            'role' => OrganisationRole::Member->value,
        ])->assertForbidden();
        $this->actingAs($admin)->delete(route('organisations.memberships.destroy', [$organisation, $ownerMembership]))->assertForbidden();

        $this->actingAs($admin)->patch(route('organisations.memberships.update', [$organisation, $finance->organisationMembership($organisation)]), [
            'role' => OrganisationRole::BookingManager->value,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($candidate->organisationMembership($organisation));
        $this->assertSame(OrganisationRole::Owner, $ownerMembership?->fresh()?->role);
        $this->assertSame(OrganisationRole::BookingManager, $finance->organisationMembership($organisation)?->role);
        $change = Activity::query()->where('event', 'organisation.membership_role_changed')->sole();
        $this->assertSame('finance', $change->getProperty('before'));
        $this->assertSame('booking_manager', $change->getProperty('after'));
        $this->assertSame($admin->id, $change->causer_id);
    }

    public function test_an_organisation_always_retains_at_least_one_owner(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $ownerMembership = $owner->organisationMembership($organisation);

        $this->actingAs($owner)->patch(route('organisations.memberships.update', [$organisation, $ownerMembership]), [
            'role' => OrganisationRole::Admin->value,
        ])->assertSessionHasErrors('role');
        $this->actingAs($owner)->delete(route('organisations.memberships.destroy', [$organisation, $ownerMembership]))
            ->assertSessionHasErrors('membership');
        $this->assertSame(OrganisationRole::Owner, $ownerMembership?->fresh()?->role);

        $secondOwner = $this->member($organisation, OrganisationRole::Owner);
        $this->actingAs($owner)->patch(route('organisations.memberships.update', [$organisation, $ownerMembership]), [
            'role' => OrganisationRole::Admin->value,
        ])->assertSessionHasNoErrors();
        $this->actingAs($secondOwner)->delete(route('organisations.memberships.destroy', [$organisation, $ownerMembership]))
            ->assertSessionHasNoErrors();

        $this->assertNull($owner->organisationMembership($organisation));
        $this->assertSame(1, $organisation->memberships()->where('role', OrganisationRole::Owner->value)->count());
    }

    public function test_organisation_pages_and_membership_ids_are_isolated_between_organisations(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        [$otherOrganisation, $otherOwner] = $this->organisationWithOwner();
        $member = $this->member($organisation, OrganisationRole::Member);
        $foreignMembership = $otherOwner->organisationMembership($otherOrganisation);

        $this->withoutVite()->actingAs($owner)->get(route('organisations.index'))
            ->assertInertia(fn (Assert $page) => $page->component('organisations/Index')
                ->has('organisations', 1)
                ->where('organisations.0.id', $organisation->id));
        $this->actingAs($owner)->get(route('organisations.show', $otherOrganisation))->assertNotFound();
        $this->actingAs($owner)->patch(route('organisations.memberships.update', [$organisation, $foreignMembership]), [
            'role' => OrganisationRole::Member->value,
        ])->assertNotFound();
        $this->actingAs($owner)->delete(route('organisations.memberships.destroy', [$organisation, $foreignMembership]))->assertNotFound();
        $this->actingAs($owner)->post(route('organisations.memberships.store', $otherOrganisation), [
            'email' => $member->email,
            'role' => OrganisationRole::Member->value,
        ])->assertNotFound();

        $this->withoutVite()->actingAs($member)->get(route('organisations.show', $organisation))
            ->assertInertia(fn (Assert $page) => $page->component('organisations/Show')
                ->where('organisation.can_manage_members', false)
                ->has('members', 0));
        $this->assertSame(OrganisationRole::Owner, $foreignMembership?->fresh()?->role);
    }

    public function test_organisation_booking_records_responsible_organisation_and_initiating_actor(): void
    {
        [$organisation] = $this->organisationWithOwner();
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $fixture = $this->bookableFixture();

        $this->actingAs($bookingManager)->postJson(route('bookings.store'), [
            ...$this->bookingPayload($fixture['resource']),
            'organisation_id' => $organisation->id,
        ])->assertCreated()
            ->assertJsonPath('data.organisation_name', $organisation->name)
            ->assertJsonPath('data.booked_by_name', $bookingManager->name);

        $booking = Booking::query()->sole();
        $this->assertSame($organisation->id, $booking->organisation_id);
        $this->assertSame($bookingManager->id, $booking->customer_id);
        $audit = Activity::query()->where('subject_id', $booking->id)->where('event', 'booking.requested')->sole();
        $this->assertSame($bookingManager->id, $audit->causer_id);
        $this->assertSame($organisation->id, $audit->getProperty('organisation_id'));
        $this->assertSame('booking_manager', $audit->getProperty('actor_organisation_role'));
        $this->assertSame('organisation_self_service', $audit->getProperty('booking_channel'));
    }

    public function test_bookings_without_an_organisation_remain_individual(): void
    {
        [$organisation] = $this->organisationWithOwner();
        $owner = $organisation->memberships()->sole()->user;
        $fixture = $this->bookableFixture();

        $this->actingAs($owner)->postJson(route('bookings.store'), $this->bookingPayload($fixture['resource']))
            ->assertCreated()
            ->assertJsonPath('data.organisation_name', null);

        $booking = Booking::query()->sole();
        $this->assertNull($booking->organisation_id);
        $this->assertSame($owner->id, $booking->customer_id);
        $this->assertSame('customer_self_service', Activity::query()->where('event', 'booking.requested')->sole()->getProperty('booking_channel'));
    }

    public function test_organisation_id_cannot_be_forged_or_used_without_booking_rights(): void
    {
        [$organisation] = $this->organisationWithOwner();
        $outsider = $this->customer();
        $member = $this->member($organisation, OrganisationRole::Member);
        $finance = $this->member($organisation, OrganisationRole::Finance);
        $fixture = $this->bookableFixture();
        $payload = [...$this->bookingPayload($fixture['resource']), 'organisation_id' => $organisation->id];

        foreach ([$outsider, $member, $finance] as $actor) {
            $this->actingAs($actor)->postJson(route('bookings.store'), $payload)->assertNotFound();
            $this->actingAs($actor)->postJson(route('bookings.recurring.store'), [
                ...$this->recurringPayload($fixture['resource']),
                'organisation_id' => $organisation->id,
                'submission_mode' => 'all_occurrences',
            ])->assertNotFound();
        }
        $this->actingAs($outsider)->postJson(route('bookings.store'), [...$payload, 'organisation_id' => 999999])->assertNotFound();

        $this->assertDatabaseEmpty('bookings');
        $this->assertDatabaseEmpty('booking_series');
    }

    public function test_booking_action_rechecks_live_membership_inside_its_transaction(): void
    {
        [$organisation] = $this->organisationWithOwner();
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $fixture = $this->bookableFixture();
        $retrievals = 0;
        Event::listen('eloquent.retrieved: '.Resource::class, function () use (&$retrievals, $bookingManager, $organisation): void {
            if (++$retrievals === 2) {
                OrganisationMembership::query()->whereBelongsTo($organisation)->whereBelongsTo($bookingManager)->delete();
            }
        });

        try {
            app(CreateBookingRequest::class)->handle(
                $bookingManager,
                $fixture['resource']->id,
                CarbonImmutable::parse('2026-10-05 18:00:00'),
                CarbonImmutable::parse('2026-10-05 19:00:00'),
                organisation: $organisation,
            );
            $this->fail('A removed member must not create an organisation booking.');
        } catch (AuthorizationException) {
        }

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_recurring_organisation_booking_assigns_the_series_and_every_occurrence(): void
    {
        [$organisation] = $this->organisationWithOwner();
        $admin = $this->member($organisation, OrganisationRole::Admin);
        $fixture = $this->bookableFixture();

        $this->actingAs($admin)->postJson(route('bookings.recurring.preview'), [
            ...$this->recurringPayload($fixture['resource']),
            'organisation_id' => $organisation->id,
        ])->assertOk();
        $this->actingAs($admin)->postJson(route('bookings.recurring.store'), [
            ...$this->recurringPayload($fixture['resource']),
            'organisation_id' => $organisation->id,
            'submission_mode' => 'all_occurrences',
        ])->assertCreated()
            ->assertJsonPath('data.organisation_name', $organisation->name)
            ->assertJsonPath('data.booked_by_name', $admin->name);

        $series = BookingSeries::query()->sole();
        $this->assertSame($organisation->id, $series->organisation_id);
        $this->assertSame($admin->id, $series->customer_id);
        $this->assertSame(3, $series->bookings()->where('organisation_id', $organisation->id)->where('customer_id', $admin->id)->count());
    }

    public function test_personal_recurring_confirmation_carries_no_organisation(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $fixture = $this->bookableFixture();

        $this->actingAs($owner)->postJson(route('bookings.recurring.store'), [
            ...$this->recurringPayload($fixture['resource']),
            'submission_mode' => 'all_occurrences',
        ])->assertCreated()
            ->assertJsonPath('data.organisation_name', null)
            ->assertJsonPath('data.booked_by_name', $owner->name);

        $this->assertNull(BookingSeries::query()->sole()->organisation_id);
    }

    public function test_review_page_offers_only_organisations_the_customer_may_book_for(): void
    {
        [$bookable] = $this->organisationWithOwner();
        [$readOnly] = $this->organisationWithOwner();
        $customer = $this->member($bookable, OrganisationRole::BookingManager);
        OrganisationMembership::factory()->for($readOnly)->for($customer)->create(['role' => OrganisationRole::Finance]);
        $fixture = $this->bookableFixture();

        $this->withoutVite()->actingAs($customer)->get(route('bookings.review', $this->bookingPayload($fixture['resource'])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('bookings/Review')
                ->has('bookingContexts', 1)
                ->where('bookingContexts.0.organisation_id', $bookable->id)
                ->where('bookingContexts.0.role_label', 'Booking Manager'));
    }

    public function test_every_member_sees_organisation_bookings_and_nothing_from_other_organisations(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        [$otherOrganisation, $otherOwner] = $this->organisationWithOwner();
        $member = $this->member($organisation, OrganisationRole::Member);
        $finance = $this->member($organisation, OrganisationRole::Finance);
        $outsider = $this->customer();
        $fixture = $this->bookableFixture();
        $organisationBooking = $this->booking($owner, $fixture, ['organisation_id' => $organisation->id]);
        $this->booking($owner, $fixture, ['starts_at' => '2026-10-05 19:00:00', 'ends_at' => '2026-10-05 20:00:00']);
        $foreignBooking = $this->booking($otherOwner, $fixture, ['organisation_id' => $otherOrganisation->id]);

        foreach ([$member, $finance] as $viewer) {
            $this->withoutVite()->actingAs($viewer)->get(route('bookings.index'))
                ->assertInertia(fn (Assert $page) => $page->has('bookings', 1)
                    ->where('bookings.0.id', $organisationBooking->id)
                    ->where('bookings.0.owner_type', 'organisation')
                    ->where('bookings.0.owner_name', $organisation->name)
                    ->where('bookings.0.booked_by_name', $owner->name)
                    ->where('bookings.0.can_cancel', false)
                    ->where('bookings.0.can_amend', false));
            $this->withoutVite()->actingAs($viewer)->get(route('bookings.show', $organisationBooking))->assertOk();
            $this->actingAs($viewer)->get(route('bookings.show', $foreignBooking))->assertNotFound();
        }

        $this->actingAs($outsider)->get(route('bookings.show', $organisationBooking))->assertNotFound();
        $this->withoutVite()->actingAs($outsider)->get(route('bookings.index'))
            ->assertInertia(fn (Assert $page) => $page->has('bookings', 0));
    }

    public function test_only_booking_capable_roles_cancel_organisation_bookings(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $member = $this->member($organisation, OrganisationRole::Member);
        $finance = $this->member($organisation, OrganisationRole::Finance);
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $booking = $this->booking($owner, $this->bookableFixture(), ['organisation_id' => $organisation->id]);

        foreach ([$member, $finance] as $actor) {
            $this->actingAs($actor)->post(route('bookings.cancel', $booking), ['reason' => 'Not allowed'])->assertForbidden();
        }
        $this->assertSame(BookingStatus::Requested, $booking->fresh()->status);

        $this->actingAs($bookingManager)->post(route('bookings.cancel', $booking), ['reason' => 'Session moved'])
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame($owner->id, $booking->fresh()->customer_id);
        $audit = Activity::query()->where('subject_id', $booking->id)->where('event', 'booking.cancelled')->sole();
        $this->assertSame($bookingManager->id, $audit->causer_id);
        $this->assertSame($organisation->id, $audit->getProperty('organisation_id'));
        $this->assertSame('booking_manager', $audit->getProperty('actor_organisation_role'));
    }

    public function test_only_booking_capable_roles_amend_organisation_bookings(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $member = $this->member($organisation, OrganisationRole::Member);
        $admin = $this->member($organisation, OrganisationRole::Admin);
        $fixture = $this->bookableFixture();
        $booking = app(CreateBookingRequest::class)->handle(
            $owner,
            $fixture['resource']->id,
            CarbonImmutable::parse('2026-10-05 18:00:00'),
            CarbonImmutable::parse('2026-10-05 19:00:00'),
            organisation: $organisation,
        );
        $amendment = ['scope' => 'occurrence', 'starts_at' => '2026-10-05 19:00:00', 'ends_at' => '2026-10-05 20:00:00'];

        $this->actingAs($member)->patch(route('bookings.amend', $booking), $amendment)->assertForbidden();
        $this->assertSame('2026-10-05 18:00:00', $booking->fresh()->starts_at->toDateTimeString());

        $this->actingAs($admin)->patch(route('bookings.amend', $booking), $amendment)->assertRedirect(route('bookings.show', $booking));

        $this->assertSame('2026-10-05 19:00:00', $booking->fresh()->starts_at->toDateTimeString());
        $this->assertSame($organisation->id, $booking->fresh()->organisation_id);
        $audit = Activity::query()->where('subject_id', $booking->id)->where('event', 'booking.amended')->sole();
        $this->assertSame($admin->id, $audit->causer_id);
        $this->assertSame('admin', $audit->getProperty('actor_organisation_role'));
    }

    public function test_finance_can_pay_an_organisation_booking_and_booking_managers_cannot(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $finance = $this->member($organisation, OrganisationRole::Finance);
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $booking = $this->payableBooking($owner, $organisation);
        $this->mock(PaymentService::class)->shouldReceive('createCheckout')->once()
            ->andReturn(['id' => 'cs_test', 'url' => 'https://checkout.stripe.com/c/pay_test', 'payment_intent' => 'pi_test', 'livemode' => false]);

        $this->actingAs($bookingManager)->get(route('bookings.payment.show', $booking))->assertNotFound();
        $this->actingAs($bookingManager)->postJson(route('bookings.payment.store', $booking))->assertNotFound();
        $this->withoutVite()->actingAs($bookingManager)->get(route('bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('booking.can_pay', false));

        $this->withoutVite()->actingAs($finance)->get(route('bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('booking.can_pay', true));
        $this->actingAs($finance)->postJson(route('bookings.payment.store', $booking))->assertSuccessful();

        $payment = Payment::query()->sole();
        $this->assertSame($owner->id, $payment->customer_id);
        $audit = Activity::query()->where('event', 'payment.initiated')->sole();
        $this->assertSame($finance->id, $audit->causer_id);
        $this->assertSame('finance', $audit->getProperty('actor_organisation_role'));
    }

    public function test_organisation_invoices_are_visible_only_to_finance_capable_members(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $finance = $this->member($organisation, OrganisationRole::Finance);
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $member = $this->member($organisation, OrganisationRole::Member);
        $outsider = $this->customer();
        $booking = $this->invoiceBooking($bookingManager, ['organisation_id' => $organisation->id]);
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $personal = $this->invoiceBooking($owner);
        $personalInvoice = app(IssueInvoice::class)->handle($this->manager($personal), [$personal->id]);

        $this->assertSame($organisation->id, $invoice->organisation_id);
        $this->assertNull($personalInvoice->organisation_id);
        foreach ([$owner, $finance] as $viewer) {
            $this->withoutVite()->actingAs($viewer)->get(route('invoices.show', $invoice))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('invoice.owner_type', 'organisation')
                    ->where('invoice.owner_name', $organisation->name));
        }
        $this->withoutVite()->actingAs($finance)->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page->has('invoices', 1)->where('invoices.0.id', $invoice->id));
        $this->withoutVite()->actingAs($owner)->get(route('invoices.index'))
            ->assertInertia(fn (Assert $page) => $page->has('invoices', 2));

        foreach ([$bookingManager, $member, $outsider] as $viewer) {
            $this->actingAs($viewer)->get(route('invoices.show', $invoice))->assertNotFound();
            $this->withoutVite()->actingAs($viewer)->get(route('invoices.index'))
                ->assertInertia(fn (Assert $page) => $page->has('invoices', 0));
        }
        $this->actingAs($finance)->get(route('invoices.show', $personalInvoice))->assertNotFound();
        $this->withoutVite()->actingAs($bookingManager)->get(route('bookings.show', $booking))
            ->assertInertia(fn (Assert $page) => $page->where('booking.invoice', null));
    }

    public function test_organisation_invoice_terms_are_held_by_the_organisation_and_separate_from_individual_terms(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $organisationBooking = $this->approvalBooking($owner, ['organisation_id' => $organisation->id]);
        $personalBooking = $this->approvalBooking($owner, [
            'resource_id' => $organisationBooking->resource_id,
            'starts_at' => now()->addDays(5)->setTime(18, 0),
            'ends_at' => now()->addDays(5)->setTime(20, 0),
        ]);
        $manager = $this->manager($organisationBooking);

        $terms = app(SetCustomerInvoiceTerms::class)->handle($manager, $organisationBooking, true, 21);

        $this->assertNull($terms->customer_id);
        $this->assertSame($organisation->id, $terms->organisation_id);
        $this->assertSame($terms->id, CustomerInvoiceTerms::query()->responsibleFor($organisationBooking)->sole()->id);
        $this->assertNull(CustomerInvoiceTerms::query()->responsibleFor($personalBooking)->first());

        $approved = app(ApproveBooking::class)->handle($manager, $organisationBooking);
        $this->assertSame(BillingMethod::Invoice, $approved->billing_method);
        $this->assertSame(21, $approved->invoice_term_days);
        $personalApproved = app(ApproveBooking::class)->handle($manager, $personalBooking);
        $this->assertSame(BillingMethod::Card, $personalApproved->billing_method);
    }

    public function test_staff_review_shows_the_responsible_organisation_and_its_invoice_terms(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $booking = $this->approvalBooking($owner, ['organisation_id' => $organisation->id]);
        $manager = $this->manager($booking);
        CustomerInvoiceTerms::query()->create([
            'customer_id' => $owner->id,
            'centre_id' => $booking->centre_id,
            'enabled' => true,
            'term_days' => 14,
            'authorised_by' => $manager->id,
            'authorised_at' => now(),
        ]);

        $this->actingAs($manager);
        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee($organisation->name)
            ->assertSee('Card payment required')
            ->assertDontSee('14 day terms');

        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 45);

        Livewire::test(ViewBooking::class, ['record' => $booking->getRouteKey()])
            ->assertSee('Authorised — 45 day terms');
    }

    public function test_one_organisation_invoice_can_cover_bookings_made_by_different_members_but_never_mixes_owners(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        [$otherOrganisation, $otherOwner] = $this->organisationWithOwner();
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $first = $this->invoiceBooking($owner, ['organisation_id' => $organisation->id]);
        $second = $this->invoiceBooking($bookingManager, ['organisation_id' => $organisation->id, 'resource_id' => $first->resource_id]);
        $personal = $this->invoiceBooking($owner, ['resource_id' => $first->resource_id]);
        $foreign = $this->invoiceBooking($otherOwner, ['organisation_id' => $otherOrganisation->id, 'resource_id' => $first->resource_id]);
        $manager = $this->manager($first);

        foreach ([[$first->id, $personal->id], [$first->id, $foreign->id]] as $bookingIds) {
            try {
                app(IssueInvoice::class)->handle($manager, $bookingIds);
                $this->fail('Bookings with different responsible owners must not share an invoice.');
            } catch (InvoiceUnavailable) {
            }
        }
        $this->assertDatabaseEmpty('invoices');

        $invoice = app(IssueInvoice::class)->handle($manager, [$first->id, $second->id]);
        $this->assertSame($organisation->id, $invoice->organisation_id);
        $this->assertCount(2, $invoice->lines);

        app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BACS-001', 'Organisation transfer');
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $second->fresh()->financial_status);
    }

    public function test_removed_member_loses_organisation_access_immediately_while_history_is_preserved(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $admin = $this->member($organisation, OrganisationRole::Admin);
        $booking = $this->invoiceBooking($admin, ['organisation_id' => $organisation->id]);
        $invoice = app(IssueInvoice::class)->handle($this->manager($booking), [$booking->id]);
        $payable = $this->payableBooking($admin, $organisation);
        $this->actingAs($admin)->get(route('invoices.show', $invoice))->assertOk();

        $this->actingAs($owner)->delete(route('organisations.memberships.destroy', [$organisation, $admin->organisationMembership($organisation)]))
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('organisations.show', $organisation))->assertNotFound();
        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertNotFound();
        $this->actingAs($admin)->post(route('bookings.cancel', $booking), ['reason' => 'Gone'])->assertNotFound();
        $this->actingAs($admin)->get(route('invoices.show', $invoice))->assertNotFound();
        $this->actingAs($admin)->get(route('bookings.payment.show', $payable))->assertNotFound();
        $this->actingAs($admin)->postJson(route('bookings.payment.store', $payable))->assertNotFound();
        $this->withoutVite()->actingAs($admin)->get(route('bookings.index'))->assertInertia(fn (Assert $page) => $page->has('bookings', 0));
        $this->withoutVite()->actingAs($admin)->get(route('invoices.index'))->assertInertia(fn (Assert $page) => $page->has('invoices', 0));

        $this->assertSame($admin->id, $booking->fresh()->customer_id);
        $this->assertSame($organisation->id, $booking->fresh()->organisation_id);
        $removal = Activity::query()->where('event', 'organisation.member_removed')->sole();
        $this->assertSame($owner->id, $removal->causer_id);
        $this->assertSame('admin', $removal->getProperty('role'));
        $this->withoutVite()->actingAs($owner)->get(route('bookings.show', $booking))->assertOk();
    }

    public function test_organisation_notifications_only_reach_initiators_who_can_still_access_the_record(): void
    {
        [$organisation, $owner] = $this->organisationWithOwner();
        $bookingManager = $this->member($organisation, OrganisationRole::BookingManager);
        $payloads = app(LifecycleNotificationPayload::class);
        $booking = $this->payableBooking($bookingManager, $organisation);
        $approval = activity('booking')->performedOn($booking)->causedBy($owner)->event('booking.approved')
            ->withProperties(['financial_status' => 'awaiting_payment', 'payment_due_at' => '2026-10-02 12:00'])->log('Approved');
        $invoiceBooking = $this->invoiceBooking($bookingManager, ['organisation_id' => $organisation->id]);
        $invoice = app(IssueInvoice::class)->handle($this->manager($invoiceBooking), [$invoiceBooking->id]);
        $issued = Activity::query()->where('subject_type', Invoice::class)->where('event', 'invoice.issued')->sole();

        $approvalPayload = $payloads->fromActivity($approval);
        $this->assertNotNull($approvalPayload);
        $this->assertSame(route('bookings.show', $booking, absolute: false), $approvalPayload['payload']['action_url']);
        $this->assertNull($payloads->fromActivity($issued));

        $bookingManager->organisationMembership($organisation)?->update(['role' => OrganisationRole::Finance]);
        $this->assertSame(route('invoices.show', $invoice, absolute: false), $payloads->fromActivity($issued)['payload']['action_url'] ?? null);

        $bookingManager->organisationMemberships()->delete();
        $this->assertNull($payloads->fromActivity($approval));
        $this->assertNull($payloads->fromActivity($issued));
    }

    /** @return array{0: Organisation, 1: User} */
    private function organisationWithOwner(): array
    {
        $owner = $this->customer();

        return [app(CreateOrganisation::class)->handle($owner, fake()->company()), $owner];
    }

    private function member(Organisation $organisation, OrganisationRole $role): User
    {
        $user = $this->customer();
        OrganisationMembership::factory()->for($organisation)->for($user)->create(['role' => $role]);

        return $user;
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    private function manager(Booking $booking): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($booking->centre_id);

        return $manager;
    }

    /** @return array{centre: Centre, facility: Facility, resource: resource, unit: AllocationUnit} */
    private function bookableFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['setup_minutes' => 0, 'cleanup_minutes' => 0]);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '22:00:00',
        ]);
        $unit = AllocationUnit::factory()->for($facility)->create();
        $resource->syncAllocationUnits($unit);
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);

        return compact('centre', 'facility', 'resource', 'unit');
    }

    /** @return array{resource_id: int, starts_at: string, ends_at: string, equipment: list<never>} */
    private function bookingPayload(Resource $resource): array
    {
        return ['resource_id' => $resource->id, 'starts_at' => '2026-10-05 18:00:00', 'ends_at' => '2026-10-05 19:00:00', 'equipment' => []];
    }

    /** @return array<string, mixed> */
    private function recurringPayload(Resource $resource): array
    {
        return [
            ...$this->bookingPayload($resource),
            'interval_weeks' => 1,
            'occurrence_count' => 3,
            'timezone' => 'Europe/London',
        ];
    }

    /**
     * @param  array{centre: Centre, facility: Facility, resource: resource, unit: AllocationUnit}  $fixture
     * @param  array<string, mixed>  $attributes
     */
    private function booking(User $customer, array $fixture, array $attributes = []): Booking
    {
        return Booking::factory()->create([
            'customer_id' => $customer->id,
            'centre_id' => $fixture['centre']->id,
            'facility_id' => $fixture['facility']->id,
            'resource_id' => $fixture['resource']->id,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:00:00',
            ...$attributes,
        ]);
    }

    private function payableBooking(User $customer, Organisation $organisation): Booking
    {
        return $this->protectedBooking([
            'customer_id' => $customer->id,
            'organisation_id' => $organisation->id,
            'status' => BookingStatus::Approved,
            'financial_status' => FinancialStatus::AwaitingPayment,
            'billing_method' => BillingMethod::Card,
            'payment_due_at' => now()->addDay(),
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function invoiceBooking(User $customer, array $attributes = []): Booking
    {
        return $this->protectedBooking([
            'customer_id' => $customer->id,
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::InvoiceOutstanding,
            'billing_method' => BillingMethod::Invoice,
            'invoice_term_days' => 30,
            'payment_due_at' => null,
            ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function approvalBooking(User $customer, array $attributes = []): Booking
    {
        $booking = $this->protectedBooking([
            'customer_id' => $customer->id,
            'starts_at' => now()->addDays(4)->setTime(18, 0),
            'ends_at' => now()->addDays(4)->setTime(20, 0),
            'status' => BookingStatus::Requested,
            'financial_status' => FinancialStatus::NotDue,
            'billing_method' => BillingMethod::Card,
            'invoice_term_days' => null,
            ...$attributes,
        ]);
        $booking->allocationOccupancy->update(['expires_at' => now()->addHour()]);
        foreach ([FacilityBookableHour::factory()->for($booking->resource->facility), ResourceBookableHour::factory()->for($booking->resource)] as $hours) {
            $hours->create(['day_of_week' => $booking->starts_at->dayOfWeekIso, 'opens_at' => '00:00:00', 'closes_at' => '23:59:59']);
        }

        return $booking;
    }

    /** @param array<string, mixed> $attributes */
    private function protectedBooking(array $attributes): Booking
    {
        $booking = Booking::factory()->create([
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
            ...$attributes,
        ]);
        $snapshot = BookingPriceSnapshot::factory()->for($booking)->create();
        BookingPriceLine::factory()->for($snapshot, 'snapshot')->create();
        $unit = $booking->resource->allocationUnits()->first();
        if ($unit === null) {
            $unit = AllocationUnit::factory()->for($booking->resource->facility)->create();
            $booking->resource->syncAllocationUnits($unit);
        }
        $period = app(OperationalOccupancyCalculator::class)->calculate($booking->resource, $booking->starts_at, $booking->ends_at);
        $this->assertNotNull($period);
        $occupancy = AllocationOccupancy::factory()->for($booking)->create([
            'starts_at' => $period->startsAt,
            'ends_at' => $period->endsAt,
            'expires_at' => null,
        ]);
        $occupancy->allocationUnits()->attach($unit);

        return $booking->fresh() ?? $booking;
    }
}
