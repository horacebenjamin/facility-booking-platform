<script setup lang="ts">
import { CheckCircle2 } from '@lucide/vue';
import BookingSummary from '@/components/BookingSummary.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type {
    BookingReviewQuote,
    BookingReviewSelection,
    BookingSubmissionResponse,
} from '@/types/booking';

defineProps<{
    booking: BookingSubmissionResponse['data'];
    selection: BookingReviewSelection;
    quote: BookingReviewQuote;
}>();
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
                    Booking request received
                </CardTitle>
                <CardDescription class="text-base">
                    Your request has been received and is waiting for management
                    review.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-muted-foreground">
                            Booking reference
                        </p>
                        <p class="text-xl font-semibold tracking-tight">
                            {{ booking.reference }}
                        </p>
                    </div>
                    <StatusBadge :label="booking.status_label" tone="warning" />
                </div>
                <div v-if="booking.organisation_name" class="text-sm">
                    <p class="text-muted-foreground">Organisation</p>
                    <p class="font-medium">{{ booking.organisation_name }}</p>
                    <p
                        v-if="booking.booked_by_name"
                        class="text-muted-foreground"
                    >
                        Booked by {{ booking.booked_by_name }}
                    </p>
                </div>
                <p
                    v-else-if="booking.booked_by_name"
                    class="text-sm text-muted-foreground"
                >
                    Booking for:
                    <span class="font-medium text-foreground">{{
                        booking.booked_by_name
                    }}</span>
                </p>
                <p class="text-sm leading-6 text-muted-foreground">
                    A provisional reservation is protecting this time while the
                    request is reviewed. Management will review the request and
                    decide what happens next.
                </p>
            </CardContent>
        </Card>

        <BookingSummary
            :selection="selection"
            :quote="quote"
            :show-price="false"
        />
    </div>
</template>
