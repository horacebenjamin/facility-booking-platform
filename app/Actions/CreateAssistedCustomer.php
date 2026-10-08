<?php

namespace App\Actions;

use App\Concerns\ProfileValidationRules;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\AssistedCustomerWelcomeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Creates a customer account on behalf of a caller or walk-in so that staff can
 * attach an assisted booking to a real customer record. No password is shared:
 * the customer is sent a single welcome email that links to the standard password
 * reset flow. A failure to queue that email never undoes the account creation.
 *
 * Staff with the organisation onboarding capability may also add the new customer
 * to an existing organisation or create a new organisation they own. Onboarding is
 * atomic with the account: if it fails, no customer is created and no email is sent.
 */
class CreateAssistedCustomer
{
    use ProfileValidationRules;

    public const string DUPLICATE_EMAIL_MESSAGE = 'An account already uses this email address. Search for the existing customer instead.';

    public function __construct(
        private AddOrganisationMember $addOrganisationMember,
        private CreateOrganisation $createOrganisation,
    ) {}

    public function handle(
        User $actor,
        string $firstName,
        string $lastName,
        string $email,
        ?string $phone = null,
        ?int $existingOrganisationId = null,
        ?OrganisationRole $existingOrganisationRole = null,
        ?string $newOrganisationName = null,
    ): User {
        Gate::forUser($actor)->authorize('createCustomer', User::class);
        $existingOrganisation = $this->authorizeOrganisationOnboarding($actor, $existingOrganisationId, $existingOrganisationRole, $newOrganisationName);

        $input = [
            'first_name' => trim($firstName),
            'last_name' => trim($lastName),
            'email' => Str::lower(trim($email)),
            'phone' => trim((string) $phone) === '' ? null : trim((string) $phone),
        ];
        $input['name'] = trim($input['first_name'].' '.$input['last_name']);

        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'phone' => $this->phoneRules(),
        ], [
            'email.unique' => self::DUPLICATE_EMAIL_MESSAGE,
            'phone.regex' => self::PHONE_FORMAT_MESSAGE,
        ])->validate();

        $customer = DB::transaction(function () use ($actor, $input, $existingOrganisation, $existingOrganisationRole, $newOrganisationName): User {
            if ($existingOrganisation !== null) {
                $existingOrganisation = Organisation::query()->lockForUpdate()->findOrFail($existingOrganisation->id);
                Gate::forUser($actor)->authorize('addAssistedCustomer', $existingOrganisation);
            }
            $customer = User::query()->create([
                'name' => $input['name'],
                'email' => $input['email'],
                'phone' => $input['phone'],
                'password' => Str::password(40),
            ]);

            $customer->assignRole(Role::findByName('customer'));

            activity('customer')
                ->performedOn($customer)
                ->causedBy($actor)
                ->event('customer.created')
                ->withProperties([
                    'customer_id' => $customer->id,
                    'creation_channel' => 'staff_assisted',
                ])
                ->log('Customer account created by staff');

            if ($existingOrganisation !== null && $existingOrganisationRole !== null) {
                $this->addOrganisationMember->handleForAssistedCustomer($actor, $existingOrganisation, $customer, $existingOrganisationRole);
            }

            if ($newOrganisationName !== null) {
                $this->createOrganisation->handleForAssistedCustomer($actor, $customer, $newOrganisationName);
            }

            return $customer;
        });

        try {
            $customer->notify(new AssistedCustomerWelcomeNotification);
        } catch (Throwable $exception) {
            Log::warning('Assisted customer welcome email could not be queued.', [
                'customer_id' => $customer->id,
                'exception_type' => $exception::class,
            ]);
        }

        return $customer;
    }

    /**
     * Checks onboarding authorisation and the requested organisation before any account
     * is created, so staff get a clear validation error rather than a half-finished record.
     */
    private function authorizeOrganisationOnboarding(User $actor, ?int $existingOrganisationId, ?OrganisationRole $role, ?string $newOrganisationName): ?Organisation
    {
        if ($existingOrganisationId === null && $newOrganisationName === null) {
            return null;
        }

        Validator::make([
            'organisation_id' => $existingOrganisationId,
            'organisation_role' => $role?->value,
            'organisation_name' => $newOrganisationName,
        ], [
            'organisation_id' => ['nullable', 'integer', 'prohibits:organisation_name'],
            'organisation_role' => [
                Rule::requiredIf($existingOrganisationId !== null),
                'nullable',
                Rule::in(array_map(fn (OrganisationRole $assignable): string => $assignable->value, OrganisationRole::staffAssignable())),
            ],
            'organisation_name' => ['nullable', 'string', 'max:255'],
        ], [
            'organisation_id.prohibits' => 'Choose either an existing organisation or a new organisation, not both.',
            'organisation_role.in' => 'Select a valid organisation role.',
        ])->validate();

        if ($newOrganisationName !== null) {
            Gate::forUser($actor)->authorize('createForAssistedCustomer', Organisation::class);

            return null;
        }

        $organisation = Organisation::query()->find($existingOrganisationId);

        if (! $organisation instanceof Organisation) {
            throw ValidationException::withMessages(['organisation_id' => 'Select an existing organisation.']);
        }

        Gate::forUser($actor)->authorize('addAssistedCustomer', $organisation);

        return $organisation;
    }
}
