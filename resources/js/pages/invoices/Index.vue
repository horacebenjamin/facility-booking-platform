<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, FileText } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatInvoiceDate, shortInvoiceReference } from '@/lib/invoice';
import { index as bookingIndex } from '@/routes/bookings';
import { show } from '@/routes/invoices';
import { personal as personalStatement } from '@/routes/statements';
import type { InvoiceSummary } from '@/types/invoice';

defineProps<{ invoices: InvoiceSummary[] }>();

const page = usePage<{
    errors?: Record<string, string>;
    old?: Record<string, unknown>;
}>();

function statementValue(field: 'from' | 'to'): string {
    const value = page.props.old?.[field];

    return typeof value === 'string' ? value : '';
}

function statementError(field: 'from' | 'to'): string | undefined {
    return page.props.errors?.[field];
}

function statusTone(invoice: InvoiceSummary): 'success' | 'neutral' {
    return invoice.status === 'paid' ? 'success' : 'neutral';
}

function money(amount: number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amount / 100);
}
</script>

<template>
    <div class="mx-auto w-full max-w-4xl min-w-0 flex-1 space-y-6 p-4 md:p-6">
        <Head title="My invoices" />
        <PageHeader
            title="My invoices"
            description="Your latest 50 invoices under agreed billing terms, including invoices for organisations where you have finance access."
        >
            <template #actions>
                <Button as-child variant="outline" class="h-11 sm:h-9">
                    <Link :href="bookingIndex()">
                        <CalendarDays aria-hidden="true" />
                        My bookings
                    </Link>
                </Button>
            </template>
        </PageHeader>
        <Card>
            <CardContent class="space-y-4 py-6">
                <form
                    method="get"
                    :action="personalStatement.url()"
                    class="space-y-3 rounded-lg border bg-muted/20 p-4 text-sm"
                >
                    <div>
                        <h2 class="font-medium">
                            Download a personal statement
                        </h2>
                        <p class="text-muted-foreground">
                            Choose a period to include invoices and payments on
                            your personal account.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-start gap-3">
                        <label class="grid gap-1" for="personal-statement-from">
                            <span class="font-medium">Statement from</span>
                            <input
                                id="personal-statement-from"
                                type="date"
                                name="from"
                                :value="statementValue('from')"
                                :aria-invalid="
                                    statementError('from') ? 'true' : undefined
                                "
                                :aria-describedby="
                                    statementError('from')
                                        ? 'personal-statement-from-error'
                                        : undefined
                                "
                                class="rounded border bg-background p-2"
                            />
                            <span
                                v-if="statementError('from')"
                                id="personal-statement-from-error"
                                class="text-sm text-destructive"
                            >
                                {{ statementError('from') }}
                            </span>
                        </label>
                        <label class="grid gap-1" for="personal-statement-to">
                            <span class="font-medium">Statement to</span>
                            <input
                                id="personal-statement-to"
                                type="date"
                                name="to"
                                :value="statementValue('to')"
                                :aria-invalid="
                                    statementError('to') ? 'true' : undefined
                                "
                                :aria-describedby="
                                    statementError('to')
                                        ? 'personal-statement-to-error'
                                        : undefined
                                "
                                class="rounded border bg-background p-2"
                            />
                            <span
                                v-if="statementError('to')"
                                id="personal-statement-to-error"
                                class="text-sm text-destructive"
                            >
                                {{ statementError('to') }}
                            </span>
                        </label>
                        <button
                            type="submit"
                            class="rounded-md border bg-background px-3 py-2 font-medium shadow-sm transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:mt-6"
                        >
                            Download statement PDF
                        </button>
                    </div>
                </form>
                <p v-if="invoices.length === 0">You have no issued invoices.</p>
                <div v-if="invoices.length > 0">
                    <table class="hidden w-full text-sm lg:table">
                        <caption class="sr-only">
                            Your latest invoices
                        </caption>
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th scope="col" class="py-2 pr-4 font-medium">
                                    Total
                                </th>
                                <th scope="col" class="py-2 pr-4 font-medium">
                                    Outstanding
                                </th>
                                <th scope="col" class="py-2 pr-4 font-medium">
                                    Due date
                                </th>
                                <th scope="col" class="py-2 pr-4 font-medium">
                                    Payment status
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 text-right font-medium"
                                >
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="invoice in invoices"
                                :key="invoice.id"
                                class="border-b last:border-b-0"
                            >
                                <td class="min-w-0 py-3 pr-4 align-top">
                                    <p class="font-medium tabular-nums">
                                        {{
                                            money(
                                                invoice.total_minor,
                                                invoice.currency,
                                            )
                                        }}
                                    </p>
                                    <p
                                        class="text-xs text-muted-foreground"
                                        :title="invoice.reference"
                                    >
                                        {{
                                            shortInvoiceReference(
                                                invoice.reference,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            invoice.owner_type ===
                                            'organisation'
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ invoice.owner_name }}
                                    </p>
                                </td>
                                <td class="py-3 pr-4 align-top tabular-nums">
                                    {{
                                        money(
                                            invoice.outstanding_minor,
                                            invoice.currency,
                                        )
                                    }}
                                </td>
                                <td class="py-3 pr-4 align-top tabular-nums">
                                    {{ formatInvoiceDate(invoice.due_date) }}
                                </td>
                                <td class="py-3 pr-4 align-top">
                                    <div class="flex flex-wrap gap-2">
                                        <StatusBadge
                                            :label="invoice.status_label"
                                            :tone="statusTone(invoice)"
                                        />
                                        <StatusBadge
                                            v-if="invoice.overdue"
                                            label="Overdue"
                                            tone="warning"
                                        />
                                    </div>
                                </td>
                                <td class="py-3 text-right align-top">
                                    <Button
                                        as-child
                                        variant="outline"
                                        size="sm"
                                    >
                                        <Link
                                            :href="show(invoice.id)"
                                            :aria-label="`View invoice ${invoice.reference}`"
                                            >View invoice</Link
                                        >
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <ul class="space-y-4 lg:hidden">
                        <li
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="space-y-3 rounded-lg border p-4"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-xs text-muted-foreground"
                                        :title="invoice.reference"
                                    >
                                        {{
                                            shortInvoiceReference(
                                                invoice.reference,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            invoice.owner_type ===
                                            'organisation'
                                        "
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ invoice.owner_name }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <StatusBadge
                                        :label="invoice.status_label"
                                        :tone="statusTone(invoice)"
                                    />
                                    <StatusBadge
                                        v-if="invoice.overdue"
                                        label="Overdue"
                                        tone="warning"
                                    />
                                </div>
                            </div>
                            <dl class="grid grid-cols-3 gap-3 text-sm">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Total
                                    </dt>
                                    <dd class="font-medium tabular-nums">
                                        {{
                                            money(
                                                invoice.total_minor,
                                                invoice.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Outstanding
                                    </dt>
                                    <dd class="font-medium tabular-nums">
                                        {{
                                            money(
                                                invoice.outstanding_minor,
                                                invoice.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Due date
                                    </dt>
                                    <dd class="font-medium tabular-nums">
                                        {{
                                            formatInvoiceDate(invoice.due_date)
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <Button
                                as-child
                                variant="outline"
                                class="h-11 w-full"
                            >
                                <Link
                                    :href="show(invoice.id)"
                                    :aria-label="`View invoice ${invoice.reference}`"
                                >
                                    <FileText aria-hidden="true" />
                                    View invoice
                                </Link>
                            </Button>
                        </li>
                    </ul>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
