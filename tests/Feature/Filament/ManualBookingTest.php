<?php

namespace Tests\Feature\Filament;

use App\Actions\AddOrganisationMember;
use App\Actions\ApproveBooking;
use App\Actions\CreateAssistedCustomer;
use App\Actions\CreateManualBooking;
use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BookingStatus;
use App\Enums\DayOfWeek;
use App\Enums\FinancialStatus;
use App\Enums\OrganisationRole;
use App\Exceptions\BookingSubmissionUnavailable;
use App\Filament\Pages\CreateAssistedBooking;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\AllocationUnit;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Centre;
use App\Models\Equipment;
use App\Models\EquipmentAllocation;
use App\Models\EquipmentRate;
use App\Models\Facility;
use App\Models\FacilityBookableHour;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\Resource;
use App\Models\ResourceBookableHour;
use App\Models\ResourceRate;
use App\Models\User;
use App\Notifications\AssistedCustomerWelcomeNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\SystemRoleSeeder;
use Filament\Forms\Components\TextInput;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SystemRoleSeeder::class);
        CarbonImmutable::setTestNow('2026-10-01 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_authorised_manager_creates_a_customer_owned_booking_with_staff_audit_attribution(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $booking = $this->createManual($manager, $customer, $fixture);
        $activity = $booking->activities()->where('event', 'booking.requested')->sole();

        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($fixture['centre']->id, $booking->centre_id);
        $this->assertSame(BookingStatus::Requested, $booking->status);
        $this->assertSame(FinancialStatus::NotDue, $booking->financial_status);
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame($customer->id, $activity->properties['customer_id'] ?? null);
        $this->assertSame('staff_assisted', $activity->properties['booking_channel'] ?? null);
        $this->assertTrue(Gate::forUser($customer)->allows('viewCustomer', $booking));
        $this->assertSame($booking->id, $customer->fresh()->bookings()->sole()->id);
    }

    public function test_assisted_booking_rechecks_assignment_inside_its_transaction(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();
        $revoked = false;
        DB::connection()->beforeStartingTransaction(function () use ($manager, $fixture, &$revoked): void {
            if (! $revoked) {
                $revoked = true;
                $manager->assignedCentres()->detach($fixture['centre']);
            }
        });
        try {
            $this->createManual($manager, $customer, $fixture);
            $this->fail('Revoked centre access must prevent the booking.');
        } catch (AuthorizationException) {
        }
        $this->assertTrue($revoked);
        $this->assertDatabaseEmpty('bookings');
        $this->assertDatabaseEmpty('allocation_occupancies');
        $this->assertDatabaseEmpty('booking_price_snapshots');
    }

    public function test_only_an_assigned_manager_can_access_the_assisted_booking_page_and_action(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();
        $assistant = User::factory()->create();
        $assistant->assignRole('leisure-assistant');
        $assistant->assignedCentres()->attach($fixture['centre']);

        $this->actingAs($manager)->get(CreateAssistedBooking::getUrl())->assertOk();
        $this->actingAs($assistant)->get(CreateAssistedBooking::getUrl())->assertForbidden();

        $this->expectException(AuthorizationException::class);
        $this->createManual($assistant, $customer, $fixture);
    }

    public function test_assisted_booking_page_previews_and_submits_through_the_shared_action(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('preview')
            ->assertSet('quote.final_total_minor', 7500)
            ->call('submit')
            ->assertSet('successReference', Booking::query()->sole()->reference);

        $this->assertDatabaseHas('bookings', ['customer_id' => $customer->id, 'centre_id' => $fixture['centre']->id, 'organisation_id' => null]);
    }

    public function test_manager_cannot_create_a_booking_in_an_unassigned_centre(): void
    {
        $fixture = $this->bookableFixture();
        $foreign = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->expectException(AuthorizationException::class);
        $this->createManual($manager, $this->customer(), $foreign);
    }

    public function test_manual_booking_rejects_resource_conflicts_and_leaves_no_partial_records(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $this->createManual($manager, $this->customer(), $fixture);

        try {
            $this->createManual($manager, $this->customer(), $fixture);
            $this->fail('A conflicting resource booking should be rejected.');
        } catch (BookingSubmissionUnavailable) {
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseCount('booking_price_snapshots', 1);
            $this->assertDatabaseCount('booking_price_lines', 1);
            $this->assertDatabaseCount('allocation_occupancies', 1);
        }
    }

    public function test_manual_booking_rejects_closures_and_exhausted_equipment(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
            'starts_at' => '2026-10-05 17:00:00',
            'ends_at' => '2026-10-05 20:00:00',
        ]);

        try {
            $this->createManual($manager, $this->customer(), $fixture);
            $this->fail('A blocked resource should be rejected.');
        } catch (BookingSubmissionUnavailable) {
            $this->assertDatabaseEmpty('bookings');
        }

        AvailabilityBlock::query()->delete();
        EquipmentAllocation::factory()->for($fixture['equipment'])->create([
            'quantity' => $fixture['equipment']->quantity,
            'starts_at' => '2026-10-05 18:00:00',
            'ends_at' => '2026-10-05 19:30:00',
        ]);

        $this->expectException(BookingSubmissionUnavailable::class);
        $this->createManual($manager, $this->customer(), $fixture, [
            ['equipment_id' => $fixture['equipment']->id, 'quantity' => 1],
        ]);
    }

    public function test_manual_booking_rejects_cross_centre_equipment(): void
    {
        $fixture = $this->bookableFixture();
        $foreign = Equipment::factory()->for(Centre::factory())->create(['quantity' => 2]);
        EquipmentRate::factory()->for($foreign)->create(['amount_minor' => 500]);
        $manager = $this->manager($fixture['centre']);

        $this->expectException(BookingSubmissionUnavailable::class);
        $this->createManual($manager, $this->customer(), $fixture, [
            ['equipment_id' => $foreign->id, 'quantity' => 1],
        ]);
    }

    public function test_authorised_price_override_is_snapshotted_and_audited(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $booking = $this->createManual($manager, $this->customer(), $fixture, [], 6000, 'Community partnership rate');
        $snapshot = $booking->priceSnapshot()->firstOrFail();
        $activity = $booking->activities()->where('event', 'booking.requested')->sole();

        $this->assertSame(7500, $snapshot->calculated_total_minor);
        $this->assertSame(6000, $snapshot->final_total_minor);
        $this->assertSame($manager->id, $snapshot->override_responsible_user_id);
        $this->assertSame('Community partnership rate', $snapshot->override_reason);
        $this->assertSame(6000, $activity->properties['price_override']['adjusted_total_minor'] ?? null);
        $this->assertSame($manager->id, $activity->properties['price_override']['responsible_user_id'] ?? null);
    }

    public function test_price_override_requires_a_reason(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->expectException(ValidationException::class);
        $this->createManual($manager, $this->customer(), $fixture, [], 6000);
    }

    public function test_assisted_booking_shows_an_inline_error_when_override_reason_is_missing(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->set('overrideAmountMinor', 6000)
            ->set('overrideReason', '')
            ->call('submit')
            ->assertHasErrors(['overrideReason' => 'required'])
            ->assertSee('The override reason field is required.');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_manager_creates_a_new_customer_in_context_and_completes_their_booking(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->actingAs($manager);

        $page = Livewire::test(CreateAssistedBooking::class)
            ->set('customerSearch', 'Ada Lovelace')
            ->callAction('createCustomer', data: [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'Ada.Lovelace@Example.test',
                'phone' => '+44 7700 900123',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified('Customer created and selected');

        $customer = User::query()->where('email', 'ada.lovelace@example.test')->sole();
        $activity = Activity::query()->where('event', 'customer.created')->sole();

        $this->assertSame('Ada Lovelace', $customer->name);
        $this->assertSame('+44 7700 900123', $customer->phone);
        $this->assertTrue($customer->hasRole('customer'));
        $this->assertSame(0, $customer->organisationMemberships()->count());
        $this->assertSame($manager->id, $activity->causer_id);
        $this->assertSame($customer->id, $activity->subject_id);
        $this->assertSame('staff_assisted', $activity->properties['creation_channel'] ?? null);

        $page->assertSet('customerId', $customer->id)
            ->assertSet('customerSearch', 'ada.lovelace@example.test')
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('preview')
            ->assertSet('quote.final_total_minor', 7500)
            ->call('submit')
            ->assertSet('successReference', Booking::query()->sole()->reference);

        $this->assertSame($customer->id, Booking::query()->sole()->customer_id);
    }

    public function test_new_customer_creation_rejects_an_email_that_already_has_an_account(): void
    {
        $fixture = $this->bookableFixture();
        $existing = $this->customer();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Duplicate',
                'last_name' => 'Caller',
                'email' => $existing->email,
            ])
            ->assertHasFormErrors(['email' => [CreateAssistedCustomer::DUPLICATE_EMAIL_MESSAGE]])
            ->assertSet('customerId', null);

        $this->assertDatabaseCount('users', 2);
    }

    public function test_new_customer_creation_rejects_an_invalid_phone_number(): void
    {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'email' => 'grace@example.test',
                'phone' => 'call reception',
            ])
            ->assertHasFormErrors(['phone']);

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.test']);
    }

    /** @return array<string, array{0: string, 1: array<string, string>}> */
    public static function customerSearchPrefills(): array
    {
        return [
            'full name' => ['Ada King Lovelace', ['first_name' => 'Ada', 'last_name' => 'King Lovelace']],
            'email' => ['ada@example.test', ['email' => 'ada@example.test']],
            'phone' => ['07700 900123', ['phone' => '07700 900123']],
        ];
    }

    /** @param array<string, string> $expectedState */
    #[DataProvider('customerSearchPrefills')]
    public function test_new_customer_form_is_prefilled_from_the_customer_search(string $search, array $expectedState): void
    {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerSearch', $search)
            ->mountAction('createCustomer')
            ->assertSchemaStateSet($expectedState);
    }

    public function test_only_staff_who_can_create_assisted_bookings_can_create_customers(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $unassignedManager = User::factory()->create();
        $unassignedManager->assignRole('manager');
        $assistant = User::factory()->create();
        $assistant->assignRole('leisure-assistant');
        $assistant->assignedCentres()->attach($fixture['centre']);

        $this->assertTrue(Gate::forUser($manager)->allows('createCustomer', User::class));
        $this->assertFalse(Gate::forUser($unassignedManager)->allows('createCustomer', User::class));
        $this->assertFalse(Gate::forUser($assistant)->allows('createCustomer', User::class));
        $this->assertFalse(Gate::forUser($this->customer())->allows('createCustomer', User::class));

        $this->expectException(AuthorizationException::class);
        app(CreateAssistedCustomer::class)->handle($assistant, 'Walk', 'In', 'walk-in@example.test');
    }

    public function test_customer_search_matches_phone_and_keeps_the_selected_customer_listed(): void
    {
        $fixture = $this->bookableFixture();
        $selected = $this->customer();
        $phoneMatch = User::factory()->create(['phone' => '07700 900456']);
        $phoneMatch->assignRole('customer');

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $selected->id)
            ->set('customerSearch', '900456')
            ->assertViewHas('customers', fn ($customers): bool => $customers->pluck('id')->sort()->values()->all() === collect([$selected->id, $phoneMatch->id])->sort()->values()->all());
    }

    public function test_changing_the_booking_after_a_price_check_clears_the_stale_quote(): void
    {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $this->customer()->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('preview')
            ->assertSet('quote.final_total_minor', 7500)
            ->set('selectedEquipmentIds', [$fixture['equipment']->id])
            ->assertSet('quote', null)
            ->assertSet('equipmentQuantities.'.$fixture['equipment']->id, 1)
            ->call('preview')
            ->assertSet('quote.final_total_minor', 8250);
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}> */
    public static function localBookingTimes(): array
    {
        return [
            'British Summer Time' => ['2026-10-05T18:00', '2026-10-05T19:30', '2026-10-05 17:00:00', '2026-10-05 18:30:00', 'Mon 5 Oct 2026, 18:00–19:30 (UK time)'],
            'Greenwich Mean Time' => ['2026-11-02T18:00', '2026-11-02T19:30', '2026-11-02 18:00:00', '2026-11-02 19:30:00', 'Mon 2 Nov 2026, 18:00–19:30 (UK time)'],
        ];
    }

    #[DataProvider('localBookingTimes')]
    public function test_assisted_booking_reads_staff_times_as_uk_local_time_and_stores_utc(
        string $localStartsAt,
        string $localEndsAt,
        string $expectedUtcStartsAt,
        string $expectedUtcEndsAt,
        string $expectedConfirmation,
    ): void {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $this->customer()->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', $localStartsAt)
            ->set('endsAt', $localEndsAt)
            ->call('preview')
            ->assertSet('quote.final_total_minor', 7500)
            ->call('submit')
            ->assertSee($expectedConfirmation);

        $booking = Booking::query()->sole();

        $this->assertSame($expectedUtcStartsAt, $booking->starts_at->utc()->toDateTimeString());
        $this->assertSame($expectedUtcEndsAt, $booking->ends_at->utc()->toDateTimeString());
    }

    public function test_assisted_availability_check_uses_the_uk_local_instant(): void
    {
        $fixture = $this->bookableFixture();
        AvailabilityBlock::factory()->forResource($fixture['resource'])->create([
            'starts_at' => '2026-10-05 16:00:00',
            'ends_at' => '2026-10-05 17:00:00',
        ]);

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $this->customer()->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('preview')
            ->assertSet('quote', null)
            ->assertSet('errorMessage', 'This selection is unavailable: blockout.');
    }

    public function test_new_customer_receives_one_welcome_email_with_a_password_setup_link_and_no_password(): void
    {
        Notification::fake();
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.test',
            ])
            ->assertHasNoFormErrors();

        $customer = User::query()->where('email', 'ada@example.test')->sole();
        Notification::assertSentToTimes($customer, AssistedCustomerWelcomeNotification::class, 1);

        $mail = (new AssistedCustomerWelcomeNotification)->toMail($customer);
        $renderedText = strip_tags((string) $mail->render());
        $token = basename((string) parse_url((string) $mail->actionUrl, PHP_URL_PATH));

        $this->assertStringStartsWith(route('password.reset', ['token' => $token]), (string) $mail->actionUrl);
        $this->assertTrue(Password::broker()->tokenExists($customer, $token));
        $this->assertStringContainsString('phone or walk-in booking', $renderedText);

        foreach (array_unique(preg_split('/\s+/', $renderedText, flags: PREG_SPLIT_NO_EMPTY)) as $word) {
            $this->assertFalse(Hash::check($word, $customer->password), 'The welcome email must not contain the account password.');
        }
    }

    public function test_selecting_an_existing_customer_does_not_send_a_welcome_email(): void
    {
        Notification::fake();
        $fixture = $this->bookableFixture();
        $customer = $this->customer();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('submit')
            ->assertSet('successReference', Booking::query()->sole()->reference);

        Notification::assertNotSentTo($customer, AssistedCustomerWelcomeNotification::class);
    }

    public function test_new_customer_is_still_created_and_selected_when_the_welcome_email_cannot_be_queued(): void
    {
        $fixture = $this->bookableFixture();
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Queue unavailable'));

        $this->actingAs($this->manager($fixture['centre']));

        $page = Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'email' => 'grace@example.test',
            ])
            ->assertHasNoFormErrors();

        $page->assertSet('customerId', User::query()->where('email', 'grace@example.test')->sole()->id);
    }

    public function test_assisted_booking_can_be_owned_by_an_organisation_the_customer_may_book_for(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $bookable = $this->organisationFor($customer, OrganisationRole::BookingManager, 'Riverside Netball Club');
        $this->organisationFor($customer, OrganisationRole::Member, 'Read-only Society');
        Organisation::factory()->create(['name' => 'Unrelated Trust']);

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->assertViewHas('organisationOptions', fn ($organisations): bool => $organisations->pluck('id')->all() === [$bookable->id])
            ->set('organisationId', $bookable->id)
            ->assertSee('Organisation: Riverside Netball Club')
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('submit')
            ->assertSet('successReference', Booking::query()->sole()->reference);

        $booking = Booking::query()->sole();

        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($bookable->id, $booking->organisation_id);
    }

    /** @return array<string, array{0: OrganisationRole|null}> */
    public static function organisationsTheCustomerCannotBookFor(): array
    {
        return [
            'member without booking rights' => [OrganisationRole::Member],
            'forged organisation the customer does not belong to' => [null],
        ];
    }

    #[DataProvider('organisationsTheCustomerCannotBookFor')]
    public function test_assisted_booking_rejects_an_organisation_the_customer_cannot_book_for(?OrganisationRole $role): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $organisation = $role === null
            ? Organisation::factory()->create()
            : $this->organisationFor($customer, $role, 'Read-only Society');

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('organisationId', $organisation->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('submit')
            ->assertHasErrors(['organisationId'])
            ->assertSee('This customer cannot make bookings for the selected organisation.');

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_changing_the_customer_clears_the_selected_organisation(): void
    {
        $fixture = $this->bookableFixture();
        $customer = $this->customer();
        $organisation = $this->organisationFor($customer, OrganisationRole::Owner, 'Riverside Netball Club');

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('organisationId', $organisation->id)
            ->set('customerId', $this->customer()->id)
            ->assertSet('organisationId', null);
    }

    public function test_internal_staff_note_is_audited_and_visible_to_staff_but_not_the_customer(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $customer->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->set('staffNote', 'Caller requested wheelchair access')
            ->call('submit')
            ->assertHasNoErrors();

        $booking = Booking::query()->sole();
        $note = $booking->activities()->where('event', Booking::STAFF_NOTE_EVENT)->sole();

        $this->assertSame($manager->id, $note->causer_id);
        $this->assertSame('Caller requested wheelchair access', $note->properties['note'] ?? null);

        $this->get(BookingResource::getUrl('view', ['record' => $booking], panel: 'management'))
            ->assertOk()
            ->assertSee('Caller requested wheelchair access');

        $this->actingAs($customer)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Caller requested wheelchair access');
    }

    public function test_internal_staff_note_longer_than_the_limit_is_rejected(): void
    {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->set('customerId', $this->customer()->id)
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('staffNote', str_repeat('a', CreateManualBooking::STAFF_NOTE_MAX_LENGTH + 1))
            ->call('submit')
            ->assertHasErrors(['staffNote' => 'max']);

        $this->assertDatabaseEmpty('bookings');
    }

    public function test_new_customer_modal_explains_the_password_setup_email(): void
    {
        $fixture = $this->bookableFixture();

        $this->actingAs($this->manager($fixture['centre']));

        Livewire::test(CreateAssistedBooking::class)
            ->mountAction('createCustomer')
            ->assertFormFieldExists('email', fn (TextInput $field): bool => str_contains(
                (string) $field->getChildSchema(TextInput::BELOW_CONTENT_SCHEMA_KEY)?->toHtmlString(),
                'We’ll email the customer a secure link to set their password once the account is created.',
            ))
            ->assertSchemaStateSet(['organisation_mode' => 'personal']);
    }

    /** @return array<string, array{0: OrganisationRole, 1: bool}> */
    public static function staffAssignableRoles(): array
    {
        return [
            'booking manager can book for the organisation' => [OrganisationRole::BookingManager, true],
            'member cannot book for the organisation' => [OrganisationRole::Member, false],
        ];
    }

    #[DataProvider('staffAssignableRoles')]
    public function test_new_customer_can_join_an_existing_organisation_with_the_selected_role(OrganisationRole $role, bool $selectsOrganisation): void
    {
        Notification::fake();
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $organisation = $this->organisationFor($this->customer(), OrganisationRole::Owner, 'Riverside Netball Club');

        $this->actingAs($manager);

        $page = Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.test',
                'organisation_mode' => 'existing',
                'organisation_id' => $organisation->id,
                'organisation_role' => $role->value,
            ])
            ->assertHasNoFormErrors();

        $customer = User::query()->where('email', 'ada@example.test')->sole();
        $membership = $customer->organisationMemberships()->sole();
        $audit = $organisation->activities()->where('event', 'organisation.member_added')->sole();

        $this->assertSame($organisation->id, $membership->organisation_id);
        $this->assertSame($role, $membership->role);
        $this->assertSame($manager->id, $audit->causer_id);
        $this->assertSame($customer->id, $audit->getProperty('member_user_id'));
        $this->assertSame($role->value, $audit->getProperty('role'));
        $this->assertSame('staff_assisted', $audit->getProperty('creation_channel'));
        Notification::assertSentToTimes($customer, AssistedCustomerWelcomeNotification::class, 1);

        $page->assertSet('customerId', $customer->id)
            ->assertSet('organisationId', $selectsOrganisation ? $organisation->id : null)
            ->assertSee($selectsOrganisation ? 'Organisation: Riverside Netball Club' : 'Personal booking');
    }

    public function test_new_customer_can_own_a_new_organisation_and_book_for_it(): void
    {
        Notification::fake();
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->actingAs($manager);

        $page = Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Grace',
                'last_name' => 'Hopper',
                'email' => 'grace@example.test',
                'organisation_mode' => 'new',
                'organisation_name' => 'Harbour Rowing Club',
            ])
            ->assertHasNoFormErrors();

        $customer = User::query()->where('email', 'grace@example.test')->sole();
        $organisation = Organisation::query()->sole();
        $membership = $organisation->memberships()->sole();
        $created = $organisation->activities()->where('event', 'organisation.created')->sole();
        $ownerAdded = $organisation->activities()->where('event', 'organisation.member_added')->sole();

        $this->assertSame('Harbour Rowing Club', $organisation->name);
        $this->assertSame($customer->id, $membership->user_id);
        $this->assertSame(OrganisationRole::Owner, $membership->role);
        $this->assertSame($manager->id, $created->causer_id);
        $this->assertSame($customer->id, $created->getProperty('owner_user_id'));
        $this->assertSame($membership->id, $created->getProperty('membership_id'));
        $this->assertSame('staff_assisted', $created->getProperty('creation_channel'));
        $this->assertSame($manager->id, $ownerAdded->causer_id);
        $this->assertSame(OrganisationRole::Owner->value, $ownerAdded->getProperty('role'));
        Notification::assertSentToTimes($customer, AssistedCustomerWelcomeNotification::class, 1);

        $page->assertSet('customerId', $customer->id)
            ->assertSet('organisationId', $organisation->id)
            ->assertSee('Organisation: Harbour Rowing Club')
            ->set('centreId', $fixture['centre']->id)
            ->set('resourceId', $fixture['resource']->id)
            ->set('startsAt', '2026-10-05T18:00')
            ->set('endsAt', '2026-10-05T19:30')
            ->call('submit')
            ->assertSet('successReference', Booking::query()->sole()->reference);

        $this->assertSame($organisation->id, Booking::query()->sole()->organisation_id);
    }

    public function test_new_organisation_onboarding_is_atomic_with_the_customer_account(): void
    {
        Notification::fake();
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        Event::listen('eloquent.creating: '.OrganisationMembership::class, function (): void {
            throw new RuntimeException('Simulated membership failure.');
        });

        try {
            app(CreateAssistedCustomer::class)->handle($manager, 'Grace', 'Hopper', 'grace@example.test', newOrganisationName: 'Harbour Rowing Club');
            $this->fail('Onboarding should have failed.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseMissing('users', ['email' => 'grace@example.test']);
        $this->assertDatabaseEmpty('organisations');
        $this->assertDatabaseEmpty('organisation_memberships');
        Notification::assertNothingSent();
    }

    public function test_staff_onboarding_rejects_a_duplicate_organisation_membership(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $customer = $this->customer();
        $organisation = $this->organisationFor($customer, OrganisationRole::Member, 'Riverside Netball Club');

        try {
            app(AddOrganisationMember::class)->handleForAssistedCustomer($manager, $organisation, $customer, OrganisationRole::Admin);
            $this->fail('A duplicate membership should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([AddOrganisationMember::DUPLICATE_MEMBERSHIP_MESSAGE], $exception->errors()['organisation_id'] ?? null);
        }

        $this->assertSame(OrganisationRole::Member, $customer->organisationMemberships()->sole()->role);
    }

    public function test_staff_onboarding_rejects_a_forged_organisation_and_creates_no_customer(): void
    {
        Notification::fake();
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->callAction('createCustomer', data: [
                'first_name' => 'Ada',
                'last_name' => 'Lovelace',
                'email' => 'ada@example.test',
                'organisation_mode' => 'existing',
                'organisation_id' => 999999,
                'organisation_role' => OrganisationRole::Member->value,
            ])
            ->assertSet('customerId', null);

        try {
            app(CreateAssistedCustomer::class)->handle($manager, 'Ada', 'Lovelace', 'ada@example.test', existingOrganisationId: 999999, existingOrganisationRole: OrganisationRole::Member);
            $this->fail('A forged organisation should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Select an existing organisation.'], $exception->errors()['organisation_id'] ?? null);
        }

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.test']);
        Notification::assertNothingSent();
    }

    public function test_staff_onboarding_cannot_grant_organisation_ownership_to_an_existing_organisation(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $organisation = $this->organisationFor($this->customer(), OrganisationRole::Owner, 'Riverside Netball Club');

        $this->expectException(ValidationException::class);
        app(CreateAssistedCustomer::class)->handle($manager, 'Ada', 'Lovelace', 'ada@example.test', existingOrganisationId: $organisation->id, existingOrganisationRole: OrganisationRole::Owner);
    }

    public function test_only_staff_with_the_onboarding_capability_can_onboard_customers_into_organisations(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $unassignedManager = User::factory()->create();
        $unassignedManager->assignRole('manager');
        $assistant = User::factory()->create();
        $assistant->assignRole('leisure-assistant');
        $assistant->assignedCentres()->attach($fixture['centre']);
        $owner = $this->customer();
        $organisation = $this->organisationFor($owner, OrganisationRole::Owner, 'Riverside Netball Club');

        $this->assertTrue(Gate::forUser($manager)->allows('addAssistedCustomer', $organisation));
        $this->assertTrue(Gate::forUser($manager)->allows('createForAssistedCustomer', Organisation::class));
        $this->assertFalse(Gate::forUser($unassignedManager)->allows('addAssistedCustomer', $organisation));
        $this->assertFalse(Gate::forUser($assistant)->allows('addAssistedCustomer', $organisation));
        $this->assertFalse(Gate::forUser($owner)->allows('addAssistedCustomer', $organisation));
        $this->assertFalse(Gate::forUser($owner)->allows('createForAssistedCustomer', Organisation::class));

        Role::findByName('manager')->revokePermissionTo('organisations.onboard');
        $manager = $manager->fresh();

        $this->assertTrue(Gate::forUser($manager)->allows('createCustomer', User::class));
        $this->assertFalse(Gate::forUser($manager)->allows('addAssistedCustomer', $organisation));
        $this->assertFalse(Gate::forUser($manager)->allows('createForAssistedCustomer', Organisation::class));

        $this->actingAs($manager);

        Livewire::test(CreateAssistedBooking::class)
            ->mountAction('createCustomer')
            ->assertFormFieldHidden('organisation_mode');

        try {
            app(CreateAssistedCustomer::class)->handle($manager, 'Ada', 'Lovelace', 'ada@example.test', existingOrganisationId: $organisation->id, existingOrganisationRole: OrganisationRole::Member);
            $this->fail('Staff without the onboarding capability must not add memberships.');
        } catch (AuthorizationException) {
        }

        $this->assertDatabaseMissing('users', ['email' => 'ada@example.test']);
        $this->assertSame(1, $organisation->memberships()->count());
    }

    public function test_manual_booking_remains_compatible_with_invoice_and_manual_payment_workflows(): void
    {
        $fixture = $this->bookableFixture();
        $manager = $this->manager($fixture['centre']);
        $booking = $this->createManual($manager, $this->customer(), $fixture);

        app(SetCustomerInvoiceTerms::class)->handle($manager, $booking, true, 30);
        $confirmed = app(ApproveBooking::class)->handle($manager, $booking);
        $invoice = app(IssueInvoice::class)->handle($manager, [$confirmed->id]);
        $payment = app(RecordManualInvoicePayment::class)->handle($manager, $invoice, 'BANK-M18', 'Verified staff-assisted booking payment.');

        $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
        $this->assertSame(FinancialStatus::Paid, $booking->fresh()->financial_status);
        $this->assertSame($booking->customer_id, $invoice->customer_id);
        $this->assertSame($invoice->id, $payment->invoice_id);
    }

    /** @return array{centre: Centre, facility: Facility, resource: resource, equipment: Equipment, units: list<AllocationUnit>} */
    private function bookableFixture(): array
    {
        $centre = Centre::factory()->create();
        $facility = Facility::factory()->for($centre)->create();
        $resource = Resource::factory()->for($facility)->create(['setup_minutes' => 15, 'cleanup_minutes' => 15]);
        FacilityBookableHour::factory()->for($facility)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);
        ResourceBookableHour::factory()->for($resource)->create([
            'day_of_week' => DayOfWeek::Monday,
            'opens_at' => '08:00:00',
            'closes_at' => '21:00:00',
        ]);
        $units = AllocationUnit::factory()->count(2)->for($facility)->create();
        $resource->syncAllocationUnits(...$units);
        ResourceRate::factory()->for($resource)->create(['amount_minor' => 5000]);
        $equipment = Equipment::factory()->for($centre)->create(['quantity' => 4]);
        EquipmentRate::factory()->for($equipment)->create(['amount_minor' => 500]);

        return compact('centre', 'facility', 'resource', 'equipment', 'units');
    }

    private function manager(Centre $centre): User
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $manager->assignedCentres()->attach($centre);

        return $manager;
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }

    private function organisationFor(User $customer, OrganisationRole $role, string $name): Organisation
    {
        $organisation = Organisation::factory()->create(['name' => $name]);
        OrganisationMembership::factory()->for($organisation)->for($customer)->create(['role' => $role]);

        return $organisation;
    }

    /** @param array{centre: Centre, facility: Facility, resource: resource, equipment: Equipment, units: list<AllocationUnit>} $fixture */
    private function createManual(User $actor, User $customer, array $fixture, array $equipmentSelections = [], ?int $overrideAmountMinor = null, ?string $overrideReason = null): Booking
    {
        return app(CreateManualBooking::class)->handle(
            actor: $actor,
            customer: $customer,
            centreId: $fixture['centre']->id,
            resourceId: $fixture['resource']->id,
            startsAt: CarbonImmutable::parse('2026-10-05 18:00:00', config('app.timezone')),
            endsAt: CarbonImmutable::parse('2026-10-05 19:30:00', config('app.timezone')),
            equipmentSelections: $equipmentSelections,
            overrideAmountMinor: $overrideAmountMinor,
            overrideReason: $overrideReason,
        );
    }
}
