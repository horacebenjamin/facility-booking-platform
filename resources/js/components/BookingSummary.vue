<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    BookingCustomer,
    BookingReviewQuote,
    BookingReviewSelection,
} from '@/types/booking';

withDefaults(
    defineProps<{
        selection: BookingReviewSelection;
        quote: BookingReviewQuote;
        customer?: BookingCustomer;
        showPrice?: boolean;
    }>(),
    {
        showPrice: true,
    },
);

function formatMoney(amountMinor: number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);
}

function formatDate(dateTime: string): string {
    const [date] = dateTime.split(' ');

    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(date + 'T00:00:00Z'));
}

function formatTime(dateTime: string): string {
    return dateTime.split(' ')[1]?.slice(0, 5) ?? dateTime;
}

function formatDuration(durationSeconds: number): string {
    const minutes = Math.round(durationSeconds / 60);
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return [
        hours > 0 ? hours + ' hour' + (hours === 1 ? '' : 's') : '',
        remainingMinutes > 0
            ? remainingMinutes + ' minute' + (remainingMinutes === 1 ? '' : 's')
            : '',
    ]
        .filter(Boolean)
        .join(' ');
}
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Booking summary</CardTitle>
            <CardDescription>
                Review the details selected for this booking request.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <dl class="grid gap-x-8 gap-y-5 text-sm sm:grid-cols-2">
                <div class="grid gap-1">
                    <dt class="text-muted-foreground">Centre</dt>
                    <dd class="font-medium">{{ selection.centre_name }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-muted-foreground">Facility</dt>
                    <dd class="font-medium">{{ selection.facility_name }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-muted-foreground">Bookable option</dt>
                    <dd class="font-medium">{{ selection.resource_name }}</dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-muted-foreground">Date</dt>
                    <dd class="font-medium">
                        {{ formatDate(selection.starts_at) }}
                    </dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-muted-foreground">Time</dt>
                    <dd class="font-medium">
                        {{ formatTime(selection.starts_at) }}–{{
                            formatTime(selection.ends_at)
                        }}
                    </dd>
                </div>
                <div class="grid gap-1">
                    <dt class="text-muted-foreground">Duration</dt>
                    <dd class="font-medium">
                        {{ formatDuration(selection.duration_seconds) }}
                    </dd>
                </div>
                <div class="grid gap-1 sm:col-span-2">
                    <dt class="text-muted-foreground">Equipment</dt>
                    <dd v-if="selection.equipment.length" class="font-medium">
                        <ul class="space-y-1">
                            <li
                                v-for="item in selection.equipment"
                                :key="item.equipment_id"
                            >
                                {{ item.name }} × {{ item.quantity }}
                            </li>
                        </ul>
                    </dd>
                    <dd v-else class="font-medium">None selected</dd>
                </div>
                <div v-if="customer" class="grid gap-1 sm:col-span-2">
                    <dt class="text-muted-foreground">Customer</dt>
                    <dd class="font-medium">
                        {{ customer.name }}
                        <span class="font-normal text-muted-foreground">
                            ({{ customer.email }})
                        </span>
                    </dd>
                </div>
                <div
                    v-if="showPrice"
                    class="grid gap-1 border-t pt-5 sm:col-span-2 sm:grid-cols-2 sm:items-end"
                >
                    <dt>
                        <span class="block text-base font-semibold"
                            >Estimated price</span
                        >
                        <span class="text-muted-foreground">
                            Informational until the request is submitted.
                        </span>
                    </dt>
                    <dd class="text-2xl font-semibold sm:text-right">
                        {{ formatMoney(quote.total_minor, quote.currency) }}
                    </dd>
                </div>
            </dl>
        </CardContent>
    </Card>
</template>
