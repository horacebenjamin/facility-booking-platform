<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { RecurringBookingPreviewData } from '@/types/booking';

defineProps<{
    preview: RecurringBookingPreviewData;
}>();

function formatDate(dateTime: string): string {
    const [date] = dateTime.split(' ');

    return new Intl.DateTimeFormat('en-GB', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(date + 'T00:00:00Z'));
}

function formatTime(dateTime: string): string {
    return dateTime.split(' ')[1]?.slice(0, 5) ?? dateTime;
}

function formatMoney(amountMinor: number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);
}

function equipmentSummary(
    equipment: Array<{ name: string; quantity: number }>,
): string {
    return equipment
        .map((item) => item.name + ' × ' + item.quantity)
        .join(', ');
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Review occurrences</CardTitle>
            <CardDescription class="space-y-1">
                <span class="block font-medium text-foreground">
                    {{ preview.summary.cadence }}
                </span>
                <span class="block">{{ preview.summary.date_range }}</span>
            </CardDescription>
        </CardHeader>
        <CardContent class="space-y-4">
            <article
                v-for="occurrence in preview.occurrences"
                :key="occurrence.index"
                class="grid gap-4 rounded-lg border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start"
                :class="
                    occurrence.status === 'conflict'
                        ? 'border-destructive/40 bg-destructive/5'
                        : 'border-green-700/30 bg-green-50 dark:bg-green-950/20'
                "
            >
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">
                            Booking {{ occurrence.index }}
                        </p>
                        <Badge
                            :variant="
                                occurrence.status === 'available'
                                    ? 'outline'
                                    : 'destructive'
                            "
                        >
                            {{
                                occurrence.status === 'available'
                                    ? 'Available'
                                    : 'Conflict'
                            }}
                        </Badge>
                    </div>
                    <p class="text-sm">
                        {{ formatDate(occurrence.starts_at) }},
                        {{ formatTime(occurrence.starts_at) }}–{{
                            formatTime(occurrence.ends_at)
                        }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Equipment:
                        <span v-if="occurrence.equipment.length">
                            {{ equipmentSummary(occurrence.equipment) }}
                        </span>
                        <span v-else>None selected</span>
                    </p>
                    <ul
                        v-if="occurrence.conflict_messages.length"
                        class="list-disc space-y-1 pl-5 text-sm text-destructive"
                    >
                        <li
                            v-for="message in occurrence.conflict_messages"
                            :key="message"
                        >
                            {{ message }}
                        </li>
                    </ul>
                </div>
                <p
                    v-if="occurrence.price"
                    class="text-lg font-semibold sm:text-right"
                >
                    {{
                        formatMoney(
                            occurrence.price.total_minor,
                            occurrence.price.currency,
                        )
                    }}
                </p>
                <p v-else class="text-sm font-medium text-destructive">
                    No booking or price reserved
                </p>
            </article>
        </CardContent>
    </Card>
</template>
