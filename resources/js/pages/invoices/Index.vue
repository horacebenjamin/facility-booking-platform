<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
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
