<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
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
        <Card class="border-green-700/30 bg-green-50 dark:bg-green-950/20">
            <CardHeader>
                <div
                    class="flex size-11 items-center justify-center rounded-full bg-green-700 text-white"
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
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Series identifier
                        </p>
                        <p class="font-mono text-sm font-semibold">
                            {{ confirmation.identifier }}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        class="border-amber-700/30 bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-50"
                    >
                        {{ confirmation.status_label }}
                    </Badge>
                </div>
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
                <CardDescription>
                    Times use {{ confirmation.timezone }}.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol class="divide-y">
                    <li
                        v-for="occurrence in confirmation.occurrences"
                        :key="occurrence.reference"
                        class="grid gap-1 py-4 first:pt-0 last:pb-0 sm:grid-cols-[1fr_auto]"
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
                        <Badge variant="outline" class="w-fit">
                            Requested
                        </Badge>
                    </li>
                </ol>
            </CardContent>
        </Card>
    </div>
</template>
