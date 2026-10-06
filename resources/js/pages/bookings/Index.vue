<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { show as showBooking } from '@/routes/bookings';
import { show as showPayment } from '@/routes/bookings/payment';
import { index as invoiceIndex, show as showInvoice } from '@/routes/invoices';
import { formatBookingDateTime } from '@/lib/bookingDateTime';

defineProps<{
    bookings: {
        id: number;
        reference: string;
        status: string;
        status_label: string;
        resource_name: string;
        facility_name: string;
        centre_name: string;
        starts_at: string;
        financial_status_label: string;
        billing_method: 'card' | 'invoice';
        invoice: { id: number; reference: string } | null;
        is_recurring: boolean;
        occurrence_index: number | null;
        occurrence_count: number | null;
        can_pay: boolean;
    }[];
}>();
</script>

<template>
    <div class="w-full flex-1 p-4 md:p-6">
        <Head title="My bookings" />
        <Card class="mx-auto max-w-3xl">
            <CardContent class="space-y-4 py-6">
                <h1 class="text-2xl font-semibold">My bookings</h1>
                <Link :href="invoiceIndex()" class="text-sm underline"
                    >My invoices</Link
                >
                <p class="text-sm text-muted-foreground">
                    Your latest 50 booking occurrences. Payment is available
                    after approval where required.
                </p>
                <p v-if="bookings.length === 0">
                    You have no booking requests yet.
                </p>
                <ul v-else class="space-y-4">
                    <li
                        v-for="booking in bookings"
                        :key="booking.id"
                        class="space-y-3 border-b pb-4 last:border-b-0"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="space-y-1">
                                <Link
                                    :href="showBooking(booking.id)"
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    {{ booking.reference }} —
                                    {{ booking.resource_name }}
                                </Link>
                                <p class="text-sm text-muted-foreground">
                                    {{ booking.facility_name }} ·
                                    {{ booking.centre_name }}
                                </p>
                            </div>
                            <Badge variant="secondary">{{
                                booking.status_label
                            }}</Badge>
                        </div>
                        <p class="text-sm">
                            {{ formatBookingDateTime(booking.starts_at) }} ·
                            {{ booking.financial_status_label }}
                        </p>
                        <p
                            v-if="booking.is_recurring"
                            class="text-sm text-muted-foreground"
                        >
                            Recurring booking · occurrence
                            {{ booking.occurrence_index }} of
                            {{ booking.occurrence_count }}
                        </p>
                        <div class="flex flex-wrap gap-3 text-sm">
                            <Link
                                :href="showBooking(booking.id)"
                                class="underline"
                                >View details</Link
                            >
                            <Link
                                v-if="booking.can_pay"
                                :href="showPayment(booking.id)"
                                class="font-medium underline"
                                >Make payment</Link
                            >
                            <Link
                                v-if="booking.invoice !== null"
                                :href="showInvoice(booking.invoice.id)"
                                class="underline"
                                >Invoice {{ booking.invoice.reference }}</Link
                            >
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
