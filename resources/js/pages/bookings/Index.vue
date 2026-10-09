<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Building2, CalendarDays, Repeat } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { index as availabilityIndex } from '@/routes/availability';
import { index as bookingIndex, show as showBooking } from '@/routes/bookings';
import { show as showPayment } from '@/routes/bookings/payment';
import { index as invoiceIndex, show as showInvoice } from '@/routes/invoices';
import { formatBookingDateTime } from '@/lib/bookingDateTime';
import { shortInvoiceReference } from '@/lib/invoice';

defineProps<{
    bookings: {
        data: {
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
            owner_type: 'individual' | 'organisation';
            owner_name: string;
            booked_by_name: string;
        }[];
        current_page: number;
        last_page: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}>();

const page = usePage<{ bookingTimezone: string }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'My bookings', href: bookingIndex() }] },
});

function statusTone(
    status: string,
): 'success' | 'warning' | 'danger' | 'neutral' {
    if (['confirmed', 'completed'].includes(status)) return 'success';
    if (['requested', 'approved'].includes(status)) return 'warning';
    if (status === 'rejected') return 'danger';
    return 'neutral';
}
</script>

<template>
    <div class="mx-auto w-full max-w-7xl min-w-0 flex-1 space-y-6 p-4 md:p-6">
        <Head title="My bookings" />
        <PageHeader
            title="My bookings"
            description="Your booking occurrences, latest first, including bookings for your organisations. Payment is available after approval where required."
        >
            <template #actions>
                <Button variant="outline" as-child
                    ><Link :href="invoiceIndex()"
                        >Invoices & payments</Link
                    ></Button
                >
            </template>
        </PageHeader>
        <EmptyState
            v-if="bookings.data.length === 0"
            title="Make your first booking"
            description="You have no booking requests yet."
        >
            <template #actions
                ><Button as-child
                    ><Link :href="availabilityIndex()"
                        >Find a facility</Link
                    ></Button
                ></template
            >
        </EmptyState>
        <ul v-else class="space-y-4">
            <li
                v-for="booking in bookings.data"
                :key="booking.id"
                class="grid min-w-0 rounded-xl border bg-card text-card-foreground shadow-xs md:grid-cols-[minmax(0,13fr)_minmax(0,7fr)]"
            >
                <div class="min-w-0 space-y-3 p-4 md:px-6">
                    <div>
                        <h2
                            class="text-base font-semibold [overflow-wrap:anywhere]"
                        >
                            {{ booking.resource_name }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            <template
                                v-if="
                                    booking.facility_name !==
                                    booking.resource_name
                                "
                                >{{ booking.facility_name }} ·
                            </template>
                            {{ booking.centre_name }}
                        </p>
                        <p
                            class="text-xs [overflow-wrap:anywhere] text-muted-foreground"
                        >
                            Ref. {{ booking.reference }}
                        </p>
                    </div>
                    <dl class="space-y-2 text-sm">
                        <div class="flex min-w-0 gap-2">
                            <CalendarDays
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="sr-only">Date and time</dt>
                                <dd class="font-medium">
                                    {{
                                        formatBookingDateTime(
                                            booking.starts_at,
                                            page.props.bookingTimezone,
                                        )
                                    }}
                                </dd>
                            </div>
                        </div>
                        <div
                            v-if="booking.is_recurring"
                            class="flex min-w-0 gap-2"
                        >
                            <Repeat
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="sr-only">Recurring booking</dt>
                                <dd>
                                    Recurring · occurrence
                                    {{ booking.occurrence_index }} of
                                    {{ booking.occurrence_count }}
                                </dd>
                            </div>
                        </div>
                        <div
                            v-if="booking.owner_type === 'organisation'"
                            class="flex min-w-0 gap-2"
                        >
                            <Building2
                                class="mt-0.5 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <dt class="sr-only">Organisation</dt>
                                <dd class="[overflow-wrap:anywhere]">
                                    {{ booking.owner_name }}
                                    <span class="text-muted-foreground"
                                        >· booked by
                                        {{ booking.booked_by_name }}</span
                                    >
                                </dd>
                            </div>
                        </div>
                    </dl>
                </div>
                <div
                    class="flex min-w-0 flex-col gap-3 border-t p-4 md:items-end md:border-t-0 md:px-6"
                >
                    <StatusBadge
                        :label="booking.status_label"
                        :tone="statusTone(booking.status)"
                    />
                    <div class="text-sm md:my-auto">
                        <div class="min-w-0 md:text-right">
                            <p class="text-xs text-muted-foreground">Payment</p>
                            <p class="font-medium [overflow-wrap:anywhere]">
                                {{ booking.financial_status_label }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-if="booking.invoice !== null"
                        class="text-xs [overflow-wrap:anywhere] text-muted-foreground md:text-right"
                        :title="booking.invoice.reference"
                    >
                        Invoice
                        {{ shortInvoiceReference(booking.invoice.reference) }}
                    </p>
                    <div
                        class="flex w-full flex-wrap gap-2 md:mt-auto md:w-auto md:justify-end md:pt-4"
                    >
                        <Button
                            v-if="booking.can_pay"
                            as-child
                            class="h-11 flex-1 md:h-9 md:flex-none"
                            ><Link :href="showPayment(booking.id)"
                                >Make payment</Link
                            ></Button
                        >
                        <Button
                            v-if="booking.invoice !== null"
                            variant="outline"
                            as-child
                            class="h-11 flex-1 md:h-9 md:flex-none"
                            ><Link
                                :href="showInvoice(booking.invoice.id)"
                                :aria-label="`View invoice ${booking.invoice.reference}`"
                                >View invoice</Link
                            ></Button
                        >
                        <Button
                            variant="outline"
                            as-child
                            class="h-11 flex-1 md:h-9 md:flex-none"
                            ><Link
                                :href="showBooking(booking.id)"
                                :aria-label="`View details for booking ${booking.reference}`"
                                >View details</Link
                            ></Button
                        >
                    </div>
                </div>
            </li>
        </ul>
        <nav
            v-if="bookings.last_page > 1"
            aria-label="Booking pages"
            class="flex flex-wrap items-center justify-between gap-3 text-sm"
        >
            <Button
                v-if="bookings.prev_page_url"
                as-child
                variant="outline"
                class="h-11 sm:h-9"
                ><Link :href="bookings.prev_page_url">Previous</Link></Button
            >
            <span v-else aria-hidden="true"></span>
            <span
                >Page {{ bookings.current_page }} of
                {{ bookings.last_page }}</span
            >
            <Button
                v-if="bookings.next_page_url"
                as-child
                variant="outline"
                class="h-11 sm:h-9"
                ><Link :href="bookings.next_page_url">Next</Link></Button
            >
            <span v-else aria-hidden="true"></span>
        </nav>
    </div>
</template>
