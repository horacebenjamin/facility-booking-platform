<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { show as showPayment } from '@/routes/bookings/payment';
import { index as invoiceIndex, show as showInvoice } from '@/routes/invoices';

defineProps<{
    bookings: {
        id: number;
        reference: string;
        resource_name: string;
        starts_at: string;
        status_label: string;
        financial_status_label: string;
        billing_method: 'card' | 'invoice';
        invoice_id: number | null;
        invoice_reference: string | null;
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
                    after approval.
                </p>
                <p v-if="bookings.length === 0">
                    You have no booking requests yet.
                </p>
                <ul class="space-y-4">
                    <li
                        v-for="booking in bookings"
                        :key="booking.id"
                        class="space-y-2 border-b pb-4"
                    >
                        <Link
                            v-if="booking.billing_method !== 'invoice'"
                            :href="showPayment(booking.id)"
                            class="font-medium underline"
                            >{{ booking.reference }} —
                            {{ booking.resource_name }}</Link
                        >
                        <p v-else class="font-medium">
                            {{ booking.reference }} —
                            {{ booking.resource_name }}
                        </p>
                        <p class="text-sm">
                            {{ booking.status_label }} ·
                            {{ booking.financial_status_label }}
                        </p>
                        <template v-if="booking.billing_method === 'invoice'">
                            <Link
                                v-if="booking.invoice_id !== null"
                                :href="showInvoice(booking.invoice_id)"
                                class="text-sm underline"
                                >Invoice {{ booking.invoice_reference }}</Link
                            >
                            <p v-else class="text-sm text-muted-foreground">
                                Confirmed under invoice terms. Your invoice will
                                be issued by the centre.
                            </p>
                        </template>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
