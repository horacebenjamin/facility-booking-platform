<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { RecurringBookingConfirmation } from '@/types/booking';

defineProps<{
    confirmation: RecurringBookingConfirmation;
}>();

function formatDateTime(dateTime: string): string {
    const [date, time] = dateTime.split(' ');
    const formattedDate = new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(date + 'T00:00:00Z'));

    return formattedDate + ', ' + time?.slice(0, 5);
}
</script>

<template>
    <div class="space-y-6">
        <Card class="border-success bg-card">
            <CardHeader>
                <div
                    class="flex size-11 items-center justify-center rounded-full bg-success text-success-foreground"
                >
                    <CheckCircle2 class="size-6" aria-hidden="true" />
                </div>
                <CardTitle class="pt-2 text-2xl">
                    Recurring booking request received
                </CardTitle>
                <CardDescription class="text-base">
                    {{ confirmation.occurrence_count }} booking request(s) from
                    {{ confirmation.first_date }} to
                    {{ confirmation.last_date }} have been received.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-muted-foreground">
                            Series identifier
                        </p>
                        <p class="font-mono text-sm font-semibold">
                            {{ confirmation.identifier }}
                        </p>
                    </div>
                    <StatusBadge
                        :label="confirmation.status_label"
                        tone="warning"
                    />
                </div>
                <div v-if="confirmation.organisation_name" class="text-sm">
                    <p class="text-muted-foreground">Organisation</p>
                    <p class="font-medium">
                        {{ confirmation.organisation_name }}
                    </p>
                    <p
                        v-if="confirmation.booked_by_name"
                        class="text-muted-foreground"
                    >
                        Booked by {{ confirmation.booked_by_name }}
                    </p>
                </div>
                <p
                    v-else-if="confirmation.booked_by_name"
                    class="text-sm text-muted-foreground"
                >
                    Booking for:
                    <span class="font-medium text-foreground">{{
                        confirmation.booked_by_name
                    }}</span>
                </p>
                <p class="text-sm leading-6 text-muted-foreground">
                    Each booking is a separate request and still requires
                    management review. A decision on one occurrence does not
                    decide the others.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Submitted occurrences</CardTitle>
            </CardHeader>
            <CardContent>
                <ol class="divide-y">
                    <li
                        v-for="occurrence in confirmation.occurrences"
                        :key="occurrence.reference"
                        class="grid min-w-0 gap-1 py-4 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_auto]"
                    >
                        <div>
                            <p class="font-medium">
                                Occurrence {{ occurrence.index }} —
                                {{ formatDateTime(occurrence.starts_at) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ occurrence.reference }}
                            </p>
                        </div>
                        <StatusBadge
                            label="Requested"
                            tone="warning"
                            class="w-fit"
                        />
                    </li>
                </ol>
            </CardContent>
        </Card>
    </div>
</template>
