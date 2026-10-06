<?php

namespace App\Http\Controllers;

use App\Actions\AddOrganisationMember;
use App\Actions\RemoveOrganisationMember;
use App\Actions\UpdateOrganisationMembership;
use App\Enums\OrganisationRole;
use App\Http\Requests\StoreOrganisationMemberRequest;
use App\Http\Requests\UpdateOrganisationMembershipRequest;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class OrganisationMembershipController extends Controller
{
    public function store(
        StoreOrganisationMemberRequest $request,
        Organisation $organisation,
        AddOrganisationMember $addOrganisationMember,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureMember($actor, $organisation);
        $data = $request->validated();
        $addOrganisationMember->handle($actor, $organisation, $data['email'], OrganisationRole::from($data['role']));

        return back()->with('success', 'Organisation member added.');
    }

    public function update(
        UpdateOrganisationMembershipRequest $request,
        Organisation $organisation,
        OrganisationMembership $membership,
        UpdateOrganisationMembership $updateOrganisationMembership,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = $request->user();
        $this->ensureMembershipContext($actor, $organisation, $membership);
        $updateOrganisationMembership->handle(
            $actor,
            $organisation,
            $membership,
            OrganisationRole::from($request->validated('role')),
        );

        return back()->with('success', 'Organisation role updated.');
    }

    public function destroy(
        Organisation $organisation,
        OrganisationMembership $membership,
        RemoveOrganisationMember $removeOrganisationMember,
    ): RedirectResponse {
        /** @var User $actor */
        $actor = request()->user();
        $this->ensureMembershipContext($actor, $organisation, $membership);
        $removingSelf = $membership->user_id === $actor->id;
        $removeOrganisationMember->handle($actor, $organisation, $membership);

        return $removingSelf
            ? to_route('organisations.index')->with('success', 'You left the organisation.')
            : back()->with('success', 'Organisation member removed.');
    }

    private function ensureMembershipContext(
        User $actor,
        Organisation $organisation,
        OrganisationMembership $membership,
    ): void {
        $this->ensureMember($actor, $organisation);
        abort_unless($membership->organisation_id === $organisation->id, 404);
    }

    private function ensureMember(User $actor, Organisation $organisation): void
    {
        abort_if($actor->organisationMembership($organisation) === null, 404);
    }
}
