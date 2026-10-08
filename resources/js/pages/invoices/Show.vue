<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index } from '@/routes/invoices';
import { pdf as invoicePdf } from '@/routes/invoices';
import { pdf as receiptPdf } from '@/routes/receipts';
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
            <CardContent class="space-y-6 py-6">
                <Link
                    :href="index()"
                    class="text-sm text-muted-foreground underline"
                    >My invoices</Link
                >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold break-words">
                            Invoice {{ invoice.reference }}
                        </h1>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Issued {{ invoice.issue_date }} · Due
                            {{ invoice.due_date }}
                        </p>
                    </div>
                    <Button as-child variant="outline">
                        <a :href="invoicePdf.url(invoice.id)"
                            >Download invoice PDF</a
                        >
                    </Button>
                </div>
                <Badge variant="secondary">
                    {{ invoice.status_label
                    }}{{ invoice.overdue ? ' · Overdue' : '' }}
                </Badge>
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
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg border bg-muted/20 p-4">
                        <p class="text-sm text-muted-foreground">
                            Invoice total
                        </p>
                        <p class="mt-1 text-lg font-semibold">
                            {{ money(invoice.total_minor, invoice.currency) }}
                        </p>
                    </div>
                    <div class="rounded-lg border bg-muted/20 p-4">
                        <p class="text-sm text-muted-foreground">Outstanding</p>
                        <p class="mt-1 text-lg font-semibold">
                            {{
                                money(
                                    invoice.outstanding_minor,
                                    invoice.currency,
                                )
                            }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="invoice.receipts.length"
                    class="space-y-2 border-t pt-4"
                >
                    <p class="font-medium">Payment receipts</p>
                    <div class="grid gap-2 sm:flex sm:flex-wrap">
                        <Button
                            v-for="receipt in invoice.receipts"
                            :key="receipt.id"
                            as-child
                            variant="ghost"
                            class="justify-start px-0 sm:px-3"
                        >
                            <a :href="receiptPdf.url(receipt.id)">
                                Download receipt {{ receipt.reference }}
                            </a>
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
