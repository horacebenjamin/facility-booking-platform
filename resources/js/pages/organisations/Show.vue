<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, ReceiptText, Search } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as availabilityIndex } from '@/routes/availability';
import { index as bookingIndex } from '@/routes/bookings';
import { index as invoiceIndex } from '@/routes/invoices';
import { index as organisationIndex } from '@/routes/organisations';
import { statement as organisationStatement } from '@/routes/organisations';
import {
    destroy as removeMember,
    store as addMember,
    update as updateMember,
} from '@/routes/organisations/memberships';
import type {
    OrganisationDetail,
    OrganisationMember,
    OrganisationRoleOption,
} from '@/types/organisation';

const props = defineProps<{
    organisation: OrganisationDetail;
    members: OrganisationMember[];
    roles: OrganisationRoleOption[];
}>();

const page = usePage<{
    flash?: { success?: string };
    errors?: Record<string, string>;
    old?: Record<string, unknown>;
}>();

function statementValue(field: 'from' | 'to'): string {
    const value = page.props.old?.[field];

    return typeof value === 'string' ? value : '';
}

function statementError(field: 'from' | 'to'): string | undefined {
    return page.props.errors?.[field];
}

function canChange(member: OrganisationMember): boolean {
    return props.organisation.role === 'owner' || member.role !== 'owner';
}

function memberErrors(errors: Record<string, string>): string | undefined {
    return errors.role ?? errors.membership;
}
</script>

<template>
    <div class="w-full flex-1 space-y-6 p-4 md:p-6">
        <Head :title="organisation.name" />

        <div class="mx-auto max-w-3xl space-y-4">
            <Button as-child variant="outline" class="h-11 sm:h-9">
                <Link :href="organisationIndex()">
                    <ArrowLeft aria-hidden="true" />
                    Back to organisations
                </Link>
            </Button>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-semibold">{{ organisation.name }}</h1>
                <Badge variant="secondary"
                    >Your role: {{ organisation.role_label }}</Badge
                >
            </div>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="mx-auto max-w-3xl rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-900 dark:bg-green-950/30 dark:text-green-100"
            role="status"
        >
            {{ page.props.flash.success }}
        </div>

        <Card class="mx-auto max-w-3xl">
            <CardHeader>
                <CardTitle>What you can do</CardTitle>
                <CardDescription>
                    Access follows your role in this organisation. It does not
                    change your access anywhere else.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4 text-sm">
                <div class="flex flex-wrap gap-3">
                    <Button
                        v-if="organisation.can_create_bookings"
                        as-child
                        class="h-auto min-h-11 py-2 text-left whitespace-normal sm:min-h-9"
                    >
                        <Link :href="availabilityIndex()">
                            <Search aria-hidden="true" />
                            Find a facility to book for this organisation
                        </Link>
                    </Button>
                    <Button
                        v-if="organisation.can_view_bookings"
                        as-child
                        variant="outline"
                        class="h-auto min-h-11 py-2 text-left whitespace-normal sm:min-h-9"
                    >
                        <Link :href="bookingIndex()">
                            <CalendarDays aria-hidden="true" />
                            View organisation bookings
                        </Link>
                    </Button>
                    <Button
                        v-if="organisation.can_view_finance"
                        as-child
                        variant="outline"
                        class="h-auto min-h-11 py-2 text-left whitespace-normal sm:min-h-9"
                    >
                        <Link :href="invoiceIndex()">
                            <ReceiptText aria-hidden="true" />
                            View organisation invoices
                        </Link>
                    </Button>
                </div>
                <form
                    v-if="organisation.can_view_finance"
                    method="get"
                    :action="organisationStatement.url(organisation.id)"
                    class="w-full space-y-3 rounded-lg border bg-muted/20 p-4"
                >
                    <div>
                        <h3 class="font-medium">
                            Download an organisation statement
                        </h3>
                        <p class="text-sm text-muted-foreground">
                            Include invoices and payments for a selected period.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-start gap-3">
                        <label
                            class="grid gap-1 text-sm"
                            for="organisation-statement-from"
                        >
                            <span class="font-medium">Statement from</span>
                            <input
                                id="organisation-statement-from"
                                type="date"
                                name="from"
                                :value="statementValue('from')"
                                :aria-invalid="
                                    statementError('from') ? 'true' : undefined
                                "
                                :aria-describedby="
                                    statementError('from')
                                        ? 'organisation-statement-from-error'
                                        : undefined
                                "
                                class="rounded border bg-background p-2"
                            />
                            <span
                                v-if="statementError('from')"
                                id="organisation-statement-from-error"
                                class="text-sm text-destructive"
                            >
                                {{ statementError('from') }}
                            </span>
                        </label>
                        <label
                            class="grid gap-1 text-sm"
                            for="organisation-statement-to"
                        >
                            <span class="font-medium">Statement to</span>
                            <input
                                id="organisation-statement-to"
                                type="date"
                                name="to"
                                :value="statementValue('to')"
                                :aria-invalid="
                                    statementError('to') ? 'true' : undefined
                                "
                                :aria-describedby="
                                    statementError('to')
                                        ? 'organisation-statement-to-error'
                                        : undefined
                                "
                                class="rounded border bg-background p-2"
                            />
                            <span
                                v-if="statementError('to')"
                                id="organisation-statement-to-error"
                                class="text-sm text-destructive"
                            >
                                {{ statementError('to') }}
                            </span>
                        </label>
                        <button
                            type="submit"
                            class="rounded-md border bg-background px-3 py-2 text-sm font-medium shadow-sm transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:mt-6"
                        >
                            Download statement PDF
                        </button>
                    </div>
                </form>
            </CardContent>
        </Card>

        <template v-if="organisation.can_manage_members">
            <Card class="mx-auto max-w-3xl">
                <CardHeader>
                    <CardTitle>Members</CardTitle>
                    <CardDescription>
                        Removing a member stops their access to this
                        organisation's bookings and invoices straight away.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul class="space-y-4">
                        <li
                            v-for="member in members"
                            :key="member.id"
                            class="space-y-3 border-b pb-4 last:border-b-0"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ member.name }}
                                        <span
                                            v-if="
                                                member.user_id ===
                                                page.props.auth.user?.id
                                            "
                                            class="text-sm font-normal text-muted-foreground"
                                            >(you)</span
                                        >
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ member.email }}
                                    </p>
                                </div>
                                <Badge
                                    v-if="!canChange(member)"
                                    variant="secondary"
                                    >{{ member.role_label }}</Badge
                                >
                            </div>
                            <div
                                v-if="canChange(member)"
                                class="flex flex-wrap items-end gap-3"
                            >
                                <Form
                                    v-bind="
                                        updateMember.form({
                                            organisation: organisation.id,
                                            membership: member.id,
                                        })
                                    "
                                    :error-bag="`member-${member.id}`"
                                    :options="{ preserveScroll: true }"
                                    class="flex flex-wrap items-end gap-3"
                                    #default="{ errors, processing }"
                                >
                                    <div class="grid gap-1">
                                        <Label :for="`member-role-${member.id}`"
                                            >Role</Label
                                        >
                                        <select
                                            :id="`member-role-${member.id}`"
                                            name="role"
                                            :value="member.role"
                                            class="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option
                                                v-for="role in roles"
                                                :key="role.value"
                                                :value="role.value"
                                            >
                                                {{ role.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        :disabled="processing"
                                        >Update role</Button
                                    >
                                    <InputError
                                        class="w-full"
                                        :message="memberErrors(errors)"
                                    />
                                </Form>
                                <Form
                                    v-bind="
                                        removeMember.form({
                                            organisation: organisation.id,
                                            membership: member.id,
                                        })
                                    "
                                    :error-bag="`member-${member.id}`"
                                    :options="{ preserveScroll: true }"
                                    #default="{ processing }"
                                >
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        :disabled="processing"
                                        >{{
                                            member.user_id ===
                                            page.props.auth.user?.id
                                                ? 'Leave organisation'
                                                : 'Remove member'
                                        }}</Button
                                    >
                                </Form>
                            </div>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card class="mx-auto max-w-3xl">
                <CardHeader>
                    <CardTitle>Add a member</CardTitle>
                    <CardDescription>
                        The person must already have a Facility4Hire customer
                        account. Email invitations are not available yet.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="addMember.form(organisation.id)"
                        error-bag="addMember"
                        reset-on-success
                        :options="{ preserveScroll: true }"
                        class="space-y-4"
                        #default="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="member-email">Email address</Label>
                            <Input
                                id="member-email"
                                name="email"
                                type="email"
                                required
                                autocomplete="off"
                            />
                            <InputError :message="errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="member-role">Role</Label>
                            <select
                                id="member-role"
                                name="role"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="role in roles"
                                    :key="role.value"
                                    :value="role.value"
                                    :selected="role.value === 'member'"
                                >
                                    {{ role.label }}
                                </option>
                            </select>
                            <InputError :message="errors.role" />
                        </div>
                        <Button type="submit" :disabled="processing">
                            Add member
                        </Button>
                    </Form>
                </CardContent>
            </Card>
        </template>
    </div>
</template>
