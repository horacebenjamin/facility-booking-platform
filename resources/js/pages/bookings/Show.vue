<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import InputError from '@/components/InputError.vue';
import {
    index as bookingIndex,
    amend as amendBooking,
    cancel as cancelBooking,
} from '@/routes/bookings';
import { show as showInvoice } from '@/routes/invoices';
import { show as showPayment } from '@/routes/bookings/payment';
import { confirmation } from '@/routes/bookings';
import { pdf as receiptPdf } from '@/routes/receipts';
import {
    bookingLocalInput,
    formatBookingDateTime,
} from '@/lib/bookingDateTime';

type HistoryEntry = {
    title: string;
    description: string;
    event: string;
    occurred_at: string;
};

type Occurrence = {
    id: number;
    reference: string;
    occurrence_index: number;
    starts_at: string;
    status_label: string;
};

const props = defineProps<{
    booking: {
        id: number;
        reference: string;
        status: string;
        status_label: string;
        financial_status_label: string;
        billing_method: 'card' | 'invoice';
        resource_name: string;
        facility_name: string;
        centre_name: string;
        starts_at: string;
        ends_at: string;
        payment_due_at: string | null;
        invoice: { id: number; reference: string } | null;
        receipts: { id: number; reference: string }[];
        is_recurring: boolean;
        occurrence_index: number | null;
        occurrence_count: number | null;
        can_pay: boolean;
        can_download_confirmation: boolean;
        owner_type: 'individual' | 'organisation';
        owner_name: string;
        booked_by_name: string;
        can_cancel: boolean;
        cancellation_unavailable_reason: string | null;
        can_amend: boolean;
        amendment_unavailable_reason: string | null;
        next_step: string;
        financial_message: string | null;
        history: HistoryEntry[];
        recurring: {
            occurrence_index: number;
            occurrence_count: number;
            occurrences: Occurrence[];
        } | null;
    };
}>();

const page = usePage<{
    bookingTimezone: string;
    flash?: { success?: string };
}>();

function localInput(value: string): string {
    return bookingLocalInput(value, page.props.bookingTimezone);
}
</script>

<template>
    <Head :title="`Booking ${booking.reference}`" />

    <div class="w-full flex-1 space-y-6 p-4 md:p-6">
        <div
            class="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3"
        >
            <div>
                <Link :href="bookingIndex()" class="text-sm underline"
                    >Back to my bookings</Link
                >
                <h1 class="mt-2 text-2xl font-semibold">
                    Booking {{ booking.reference }}
                </h1>
            </div>
            <Badge variant="secondary">{{ booking.status_label }}</Badge>
        </div>

        <div
            v-if="page.props.flash?.success"
            class="mx-auto max-w-4xl rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-900 dark:bg-green-950/30 dark:text-green-100"
            role="status"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="mx-auto grid max-w-4xl gap-6 lg:grid-cols-[1.4fr_1fr]">
            <div class="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Booking details</CardTitle>
                        <CardDescription>{{
                            booking.next_step
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-muted-foreground">Venue</p>
                            <p>{{ booking.centre_name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Facility</p>
                            <p>{{ booking.facility_name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Resource</p>
                            <p>{{ booking.resource_name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Starts</p>
                            <p>
                                {{
                                    formatBookingDateTime(
                                        booking.starts_at,
                                        page.props.bookingTimezone,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Ends</p>
                            <p>
                                {{
                                    formatBookingDateTime(
                                        booking.ends_at,
                                        page.props.bookingTimezone,
                                    )
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">Financial state</p>
                            <p>{{ booking.financial_status_label }}</p>
                        </div>
                        <template v-if="booking.owner_type === 'organisation'">
                            <div>
                                <p class="text-muted-foreground">
                                    Organisation
                                </p>
                                <p>{{ booking.owner_name }}</p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Booked by</p>
                                <p>{{ booking.booked_by_name }}</p>
                            </div>
                        </template>
                        <div
                            v-if="booking.payment_due_at"
                            class="sm:col-span-2"
                        >
                            <p class="text-muted-foreground">
                                Payment deadline
                            </p>
                            <p>
                                {{
                                    formatBookingDateTime(
                                        booking.payment_due_at,
                                        page.props.bookingTimezone,
                                    )
                                }}
                            </p>
                        </div>
                        <p
                            v-if="booking.financial_message"
                            class="rounded-md border border-amber-600/30 bg-amber-50 p-3 text-amber-900 sm:col-span-2 dark:bg-amber-950/30 dark:text-amber-100"
                        >
                            {{ booking.financial_message }}
                        </p>
                        <div v-if="booking.invoice" class="sm:col-span-2">
                            <Link
                                :href="showInvoice(booking.invoice.id)"
                                class="font-medium break-words underline"
                                >View invoice
                                {{ booking.invoice.reference }}</Link
                            >
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="booking.recurring">
                    <CardHeader>
                        <CardTitle>Recurring booking</CardTitle>
                        <CardDescription>
                            Occurrence
                            {{ booking.recurring.occurrence_index }} of
                            {{ booking.recurring.occurrence_count }}. Changes on
                            this page apply to this occurrence only.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul class="space-y-3 text-sm">
                            <li
                                v-for="occurrence in booking.recurring
                                    .occurrences"
                                :key="occurrence.id"
                                class="flex items-center justify-between gap-3 border-b pb-2 last:border-b-0"
                            >
                                <span
                                    >{{ occurrence.occurrence_index }} ·
                                    {{
                                        formatBookingDateTime(
                                            occurrence.starts_at,
                                            page.props.bookingTimezone,
                                        )
                                    }}</span
                                >
                                <span class="text-muted-foreground">{{
                                    occurrence.status_label
                                }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Booking history</CardTitle>
                        <CardDescription
                            >Customer-visible milestones for this
                            booking.</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <ol
                            v-if="booking.history.length"
                            class="space-y-5 border-l pl-5"
                        >
                            <li
                                v-for="entry in booking.history"
                                :key="`${entry.event}-${entry.occurred_at}`"
                                class="relative space-y-1"
                            >
                                <span
                                    class="absolute top-1.5 -left-[1.6rem] size-2 rounded-full bg-primary"
                                    aria-hidden="true"
                                />
                                <p class="font-medium">{{ entry.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ entry.description }}
                                </p>
                                <time
                                    class="text-xs text-muted-foreground"
                                    :datetime="entry.occurred_at"
                                    >{{
                                        formatBookingDateTime(
                                            entry.occurred_at,
                                            page.props.bookingTimezone,
                                        )
                                    }}</time
                                >
                            </li>
                        </ol>
                        <p v-else class="text-sm text-muted-foreground">
                            No customer-visible history is available yet.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-6">
                <Card>
                    <CardHeader
                        ><CardTitle>Available actions</CardTitle></CardHeader
                    >
                    <CardContent class="space-y-4">
                        <Button
                            v-if="booking.can_download_confirmation"
                            as-child
                            variant="outline"
                            class="w-full justify-start"
                        >
                            <a :href="confirmation.url(booking.id)">
                                Download booking confirmation
                            </a>
                        </Button>
                        <Button
                            v-for="receipt in booking.receipts"
                            :key="receipt.id"
                            as-child
                            variant="ghost"
                            class="w-full justify-start"
                        >
                            <a :href="receiptPdf.url(receipt.id)">
                                Download receipt {{ receipt.reference }}
                            </a>
                        </Button>
                        <Button v-if="booking.can_pay" as-child class="w-full">
                            <Link :href="showPayment(booking.id)"
                                >Make payment</Link
                            >
                        </Button>
                        <p
                            v-else-if="booking.status === 'awaiting_payment'"
                            class="text-sm text-muted-foreground"
                        >
                            Payment is not currently available. Check the
                            deadline or contact the centre.
                        </p>

                        <div
                            v-if="booking.can_cancel"
                            class="space-y-3 border-t pt-4"
                        >
                            <p class="text-sm font-medium">
                                Cancel this booking
                            </p>
                            <Form
                                v-bind="cancelBooking.form(booking.id)"
                                #default="{ errors, processing }"
                                class="space-y-3"
                            >
                                <label
                                    for="cancellation-reason"
                                    class="text-sm text-muted-foreground"
                                    >Reason</label
                                >
                                <textarea
                                    id="cancellation-reason"
                                    name="reason"
                                    required
                                    rows="3"
                                    class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    placeholder="Tell us why you are cancelling"
                                />
                                <InputError
                                    :message="errors.reason ?? errors.booking"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="processing"
                                >
                                    {{
                                        processing
                                            ? 'Cancelling…'
                                            : 'Cancel booking'
                                    }}
                                </Button>
                            </Form>
                        </div>
                        <p
                            v-else
                            class="border-t pt-4 text-sm text-muted-foreground"
                        >
                            {{ booking.cancellation_unavailable_reason }}
                        </p>

                        <div
                            v-if="booking.can_amend"
                            class="space-y-3 border-t pt-4"
                        >
                            <p class="text-sm font-medium">
                                Amend this occurrence
                            </p>
                            <Form
                                v-bind="amendBooking.form(booking.id)"
                                #default="{ errors, processing }"
                                class="space-y-3"
                            >
                                <input
                                    type="hidden"
                                    name="scope"
                                    value="occurrence"
                                />
                                <div class="space-y-1">
                                    <label
                                        for="amend-starts-at"
                                        class="text-sm text-muted-foreground"
                                        >New start</label
                                    >
                                    <input
                                        id="amend-starts-at"
                                        name="starts_at"
                                        type="datetime-local"
                                        required
                                        :value="localInput(booking.starts_at)"
                                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                </div>
                                <div class="space-y-1">
                                    <label
                                        for="amend-ends-at"
                                        class="text-sm text-muted-foreground"
                                        >New end</label
                                    >
                                    <input
                                        id="amend-ends-at"
                                        name="ends_at"
                                        type="datetime-local"
                                        required
                                        :value="localInput(booking.ends_at)"
                                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                </div>
                                <InputError
                                    :message="
                                        errors.starts_at ??
                                        errors.ends_at ??
                                        errors.booking
                                    "
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    :disabled="processing"
                                >
                                    {{
                                        processing
                                            ? 'Saving…'
                                            : 'Save amendment'
                                    }}
                                </Button>
                            </Form>
                        </div>
                        <p
                            v-else
                            class="border-t pt-4 text-sm text-muted-foreground"
                        >
                            {{ booking.amendment_unavailable_reason }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
