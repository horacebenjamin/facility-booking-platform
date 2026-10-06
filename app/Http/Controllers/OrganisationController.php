<?php

namespace App\Http\Controllers;

use App\Actions\CreateOrganisation;
use App\Enums\OrganisationRole;
use App\Http\Requests\StoreOrganisationRequest;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        Gate::forUser($user)->authorize('viewAny', Organisation::class);

        return Inertia::render('organisations/Index', [
            'organisations' => $user->organisationMemberships()
                ->with('organisation')
                ->latest('joined_at')
                ->latest('id')
                ->get()
                ->map(fn (OrganisationMembership $membership): array => [
                    'id' => $membership->organisation->id,
                    'name' => $membership->organisation->name,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                ])
                ->values(),
        ]);
    }

    public function store(StoreOrganisationRequest $request, CreateOrganisation $createOrganisation): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organisation = $createOrganisation->handle($user, $request->validated('name'));

        return to_route('organisations.show', $organisation)->with('success', 'Organisation created.');
    }

    public function show(Request $request, Organisation $organisation): Response
    {
        /** @var User $user */
        $user = $request->user();
        $membership = $this->membershipOrNotFound($user, $organisation);
        $canManageMembers = $membership->role->canManageMembers();
        $members = $canManageMembers
            ? $organisation->memberships()->with('user')->orderBy('id')->get()
            : collect();
        $roles = collect(OrganisationRole::cases())
            ->when($membership->role === OrganisationRole::Admin, fn ($items) => $items->reject(fn (OrganisationRole $role): bool => $role === OrganisationRole::Owner))
            ->map(fn (OrganisationRole $role): array => ['value' => $role->value, 'label' => $role->label()])
            ->values();

        return Inertia::render('organisations/Show', [
            'organisation' => [
                'id' => $organisation->id,
                'name' => $organisation->name,
                'role' => $membership->role->value,
                'role_label' => $membership->role->label(),
                'can_manage_members' => $canManageMembers,
                'can_create_bookings' => $membership->role->canCreateBookings(),
                'can_view_bookings' => $membership->role->canViewBookings(),
                'can_view_finance' => $membership->role->canManageFinance(),
            ],
            'members' => $members->map(fn (OrganisationMembership $item): array => [
                'id' => $item->id,
                'user_id' => $item->user_id,
                'name' => $item->user->name,
                'email' => $item->user->email,
                'role' => $item->role->value,
                'role_label' => $item->role->label(),
                'joined_at' => $item->joined_at->toIso8601String(),
            ])->values(),
            'roles' => $roles,
        ]);
    }

    private function membershipOrNotFound(User $user, Organisation $organisation): OrganisationMembership
    {
        $membership = $user->organisationMembership($organisation);
        abort_if($membership === null, 404);

        return $membership;
    }
}
