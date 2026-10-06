<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/invoices';
import type { InvoiceDetail } from '@/types/invoice';

defineProps<{ invoice: InvoiceDetail }>();

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amount / 100);
}
</script>

<template>
    <div class="w-full flex-1 p-4 md:p-6">
        <Head :title="`Invoice ${invoice.reference}`" />
        <Card class="mx-auto max-w-3xl">
            <CardContent class="space-y-4 py-6">
                <Link :href="index()" class="text-sm underline"
                    >My invoices</Link
                >
                <h1 class="text-2xl font-semibold">
                    Invoice {{ invoice.reference }}
                </h1>
                <p>
                    {{ invoice.status_label
                    }}{{ invoice.overdue ? ' · Overdue' : '' }}
                </p>
                <p class="text-sm">
                    Issued {{ invoice.issue_date }} · Due {{ invoice.due_date }}
                </p>
                <p
                    v-if="invoice.owner_type === 'organisation'"
                    class="text-sm text-muted-foreground"
                >
                    Billed to organisation {{ invoice.owner_name }}
                </p>
                <p
                    v-if="invoice.status === 'issued'"
                    class="text-sm text-muted-foreground"
                >
                    This invoice follows your agreed payment terms. Contact the
                    centre to arrange settlement.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    This invoice has been settled in full.
                </p>
                <ul class="space-y-4">
                    <li
                        v-for="line in invoice.lines"
                        :key="line.id"
                        class="space-y-1 border-b pb-4"
                    >
                        <p>{{ line.description }}</p>
                        <p class="text-sm">
                            Booking {{ line.booking_reference }} ·
                            {{ money(line.amount_minor, invoice.currency) }}
                        </p>
                    </li>
                </ul>
                <p class="font-semibold">
                    Total {{ money(invoice.total_minor, invoice.currency) }}
                </p>
                <p>
                    Outstanding
                    {{ money(invoice.outstanding_minor, invoice.currency) }}
                </p>
            </CardContent>
        </Card>
    </div>
</template>
