<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { index as bookingIndex } from '@/routes/bookings';
import { show } from '@/routes/invoices';
import type { InvoiceSummary } from '@/types/invoice';

defineProps<{ invoices: InvoiceSummary[] }>();

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amount / 100);
}
</script>

<template>
    <div class="w-full flex-1 p-4 md:p-6">
        <Head title="My invoices" />
        <Card class="mx-auto max-w-3xl">
            <CardContent class="space-y-4 py-6">
                <h1 class="text-2xl font-semibold">My invoices</h1>
                <Link :href="bookingIndex()" class="text-sm underline"
                    >My bookings</Link
                >
                <p class="text-sm text-muted-foreground">
                    Your latest 50 invoices under agreed billing terms,
                    including invoices for organisations where you have finance
                    access.
                </p>
                <p v-if="invoices.length === 0">You have no issued invoices.</p>
                <ul class="space-y-4">
                    <li
                        v-for="invoice in invoices"
                        :key="invoice.id"
                        class="space-y-2 border-b pb-4"
                    >
                        <Link
                            :href="show(invoice.id)"
                            class="font-medium underline"
                            >{{ invoice.reference }}</Link
                        >
                        <p>
                            {{ invoice.status_label
                            }}{{ invoice.overdue ? ' · Overdue' : '' }} ·
                            {{ money(invoice.total_minor, invoice.currency) }}
                        </p>
                        <p
                            v-if="invoice.owner_type === 'organisation'"
                            class="text-sm text-muted-foreground"
                        >
                            Organisation: {{ invoice.owner_name }}
                        </p>
                        <p class="text-sm">
                            Due {{ invoice.due_date }} · Outstanding
                            {{
                                money(
                                    invoice.outstanding_minor,
                                    invoice.currency,
                                )
                            }}
                        </p>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
