<?php

namespace Tests\Feature\Policies;

use App\Actions\IssueInvoice;
use App\Actions\RecordManualInvoicePayment;
use App\Actions\SetCustomerInvoiceTerms;
use App\Enums\BillingMethod;
use App\Enums\BookingStatus;
use App\Enums\FinancialStatus;
use App\Enums\OrganisationRole;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Database\Seeders\SystemRoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganisationAuthorizationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SystemRoleSeeder::class);
    }

    /** @return array<string, array{OrganisationRole, bool, bool, bool, bool}> */
    public static function roleCapabilities(): array
    {
        return [
            'owner' => [OrganisationRole::Owner, true, true, true, true],
            'admin' => [OrganisationRole::Admin, true, true, true, true],
            'booking manager' => [OrganisationRole::BookingManager, false, true, true, false],
            'finance' => [OrganisationRole::Finance, false, false, true, true],
            'member' => [OrganisationRole::Member, false, false, true, false],
        ];
    }

    #[DataProvider('roleCapabilities')]
    public function test_organisation_role_grants_only_its_documented_capabilities(
        OrganisationRole $role,
        bool $managesMembers,
        bool $managesBookings,
        bool $viewsBookings,
        bool $managesFinance,
    ): void {
        $organisation = Organisation::factory()->create();
        $user = $this->customer();
        OrganisationMembership::factory()->for($organisation)->for($user)->create(['role' => $role]);
        $booking = Booking::factory()->create(['organisation_id' => $organisation->id]);
        $invoice = Invoice::factory()->create(['organisation_id' => $organisation->id, 'customer_id' => $booking->customer_id]);

        $this->assertSame($managesMembers, $user->can('manageMembers', $organisation));
        $this->assertSame($managesBookings, $user->can('createBooking', $organisation));
        $this->assertSame($viewsBookings, $user->can('viewCustomer', $booking));
        $this->assertSame($managesBookings, $user->can('cancel', $booking));
        $this->assertSame($managesBookings, $user->can('amend', $booking));
        $this->assertSame($managesFinance, $user->can('viewPayment', $booking));
        $this->assertSame($managesFinance, $user->can('pay', $booking));
        $this->assertSame($managesFinance, $user->can('viewCustomer', $invoice));
    }

    public function test_initiating_customer_id_alone_never_grants_organisation_access(): void
    {
        $organisation = Organisation::factory()->create();
        $initiator = $this->customer();
        $booking = Booking::factory()->create(['customer_id' => $initiator->id, 'organisation_id' => $organisation->id]);
        $invoice = Invoice::factory()->create(['customer_id' => $initiator->id, 'organisation_id' => $organisation->id]);

        foreach (['viewCustomer', 'cancel', 'amend', 'viewPayment', 'pay'] as $ability) {
            $this->assertFalse($initiator->can($ability, $booking), $ability);
        }
        $this->assertFalse($initiator->can('viewCustomer', $invoice));
        $this->assertFalse($initiator->can('view', $organisation));
    }

    public function test_membership_in_one_organisation_grants_nothing_in_another(): void
    {
        $organisation = Organisation::factory()->create();
        $otherOrganisation = Organisation::factory()->create();
        $owner = $this->customer();
        OrganisationMembership::factory()->for($organisation)->for($owner)->owner()->create();
        $foreignBooking = Booking::factory()->create(['organisation_id' => $otherOrganisation->id]);

        $this->assertFalse($owner->can('view', $otherOrganisation));
        $this->assertFalse($owner->can('manageMembers', $otherOrganisation));
        $this->assertFalse($owner->can('createBooking', $otherOrganisation));
        $this->assertFalse($owner->can('viewCustomer', $foreignBooking));
        $this->assertFalse($owner->can('pay', $foreignBooking));
    }

    public function test_organisation_roles_are_separate_from_system_roles_and_permissions(): void
    {
        $organisation = Organisation::factory()->create();
        $user = $this->customer();
        OrganisationMembership::factory()->for($organisation)->for($user)->owner()->create();

        $this->assertSame(['customer'], $user->getRoleNames()->all());
        $this->assertFalse(Role::query()->whereIn('name', array_column(OrganisationRole::cases(), 'value'))->exists());
        foreach (['invoices.manage', 'payments.record', 'bookings.approve'] as $permission) {
            $this->assertFalse($user->can($permission), $permission);
        }

        $user->syncRoles([]);
        $this->assertFalse($user->fresh()->can('view', $organisation));
        $this->assertFalse($user->fresh()->can('createBooking', $organisation));
    }

    public function test_organisation_membership_never_grants_staff_panel_access(): void
    {
        $organisation = Organisation::factory()->create();

        foreach ([OrganisationRole::Owner, OrganisationRole::Admin, OrganisationRole::Finance] as $role) {
            $user = $this->customer();
            OrganisationMembership::factory()->for($organisation)->for($user)->create(['role' => $role]);

            $this->actingAs($user)->get('/management')->assertForbidden();
            $this->actingAs($user)->get('/operations')->assertForbidden();
        }
    }

    public function test_organisation_finance_role_cannot_perform_staff_financial_actions(): void
    {
        $organisation = Organisation::factory()->create();
        $finance = $this->customer();
        OrganisationMembership::factory()->for($organisation)->for($finance)->create(['role' => OrganisationRole::Finance]);
        $booking = Booking::factory()->create([
            'organisation_id' => $organisation->id,
            'status' => BookingStatus::Confirmed,
            'financial_status' => FinancialStatus::InvoiceOutstanding,
            'billing_method' => BillingMethod::Invoice,
            'invoice_term_days' => 30,
        ]);
        $finance->assignedCentres()->attach($booking->centre_id);
        $invoice = Invoice::factory()->create(['organisation_id' => $organisation->id, 'customer_id' => $booking->customer_id]);

        $attempts = [
            fn () => app(IssueInvoice::class)->handle($finance, [$booking->id]),
            fn () => app(SetCustomerInvoiceTerms::class)->handle($finance, $booking, true, 30),
            fn () => app(RecordManualInvoicePayment::class)->handle($finance, $invoice, 'REF', 'Note'),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('An organisation Finance member must not act as staff.');
            } catch (AuthorizationException) {
            }
        }
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_staff_centre_scope_is_unchanged_for_organisation_bookings(): void
    {
        $organisation = Organisation::factory()->create();
        $booking = Booking::factory()->create(['organisation_id' => $organisation->id]);
        $assigned = User::factory()->create();
        $assigned->assignRole('manager');
        $assigned->assignedCentres()->attach($booking->centre_id);
        $unassigned = User::factory()->create();
        $unassigned->assignRole('manager');
        OrganisationMembership::factory()->for($organisation)->for($unassigned)->owner()->create();

        $this->assertTrue(Gate::forUser($assigned)->allows('view', $booking));
        $this->assertTrue(Gate::forUser($assigned)->allows('approve', $booking));
        $this->assertFalse(Gate::forUser($unassigned)->allows('view', $booking));
        $this->assertFalse(Gate::forUser($unassigned)->allows('approve', $booking));
        $this->assertFalse(Gate::forUser($assigned)->allows('viewCustomer', $booking));
    }

    public function test_database_rejects_duplicate_memberships_and_unsupported_roles(): void
    {
        $membership = OrganisationMembership::factory()->create();

        try {
            OrganisationMembership::factory()->create([
                'organisation_id' => $membership->organisation_id,
                'user_id' => $membership->user_id,
            ]);
            $this->fail('Duplicate memberships must be rejected by the database.');
        } catch (QueryException) {
        }

        $this->expectException(QueryException::class);
        DB::table('organisation_memberships')->insert([
            'organisation_id' => $membership->organisation_id,
            'user_id' => User::factory()->create()->id,
            'role' => 'superuser',
            'joined_at' => now(),
        ]);
    }

    public function test_invoice_terms_must_belong_to_exactly_one_responsible_owner(): void
    {
        $booking = Booking::factory()->create();
        $organisation = Organisation::factory()->create();
        $authorisedBy = User::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('customer_invoice_terms')->insert([
            'customer_id' => $booking->customer_id,
            'organisation_id' => $organisation->id,
            'centre_id' => $booking->centre_id,
            'enabled' => true,
            'term_days' => 30,
            'authorised_by' => $authorisedBy->id,
            'authorised_at' => now(),
        ]);
    }

    private function customer(): User
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return $customer;
    }
}
