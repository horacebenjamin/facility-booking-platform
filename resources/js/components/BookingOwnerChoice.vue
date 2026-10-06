<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import type {
    BookingCustomer,
    BookingOrganisationContext,
} from '@/types/booking';

defineProps<{
    customer: BookingCustomer;
    contexts: BookingOrganisationContext[];
}>();

const organisationId = defineModel<number | null>({ required: true });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Who is this booking for?</CardTitle>
            <CardDescription>
                Organisation bookings are visible to that organisation's
                members. You remain recorded as the person who made the request.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <fieldset class="grid gap-3 sm:grid-cols-2">
                <legend class="sr-only">Booking owner</legend>
                <Label
                    for="booking-owner-self"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                    :class="
                        organisationId === null
                            ? 'border-primary bg-primary/5'
                            : ''
                    "
                >
                    <input
                        id="booking-owner-self"
                        v-model="organisationId"
                        type="radio"
                        name="booking-owner"
                        :value="null"
                        class="mt-1 size-4"
                    />
                    <span>
                        <span class="block font-medium">Myself</span>
                        <span
                            class="block text-sm font-normal text-muted-foreground"
                        >
                            {{ customer.name }}
                        </span>
                    </span>
                </Label>
                <Label
                    v-for="context in contexts"
                    :key="context.organisation_id"
                    :for="`booking-owner-${context.organisation_id}`"
                    class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                    :class="
                        organisationId === context.organisation_id
                            ? 'border-primary bg-primary/5'
                            : ''
                    "
                >
                    <input
                        :id="`booking-owner-${context.organisation_id}`"
                        v-model="organisationId"
                        type="radio"
                        name="booking-owner"
                        :value="context.organisation_id"
                        class="mt-1 size-4"
                    />
                    <span>
                        <span class="block font-medium">{{
                            context.name
                        }}</span>
                        <span
                            class="block text-sm font-normal text-muted-foreground"
                        >
                            Your role: {{ context.role_label }}
                        </span>
                    </span>
                </Label>
            </fieldset>
        </CardContent>
    </Card>
</template>
