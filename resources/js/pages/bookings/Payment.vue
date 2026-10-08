<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import BookingPaymentStatus from '@/components/BookingPaymentStatus.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index as bookingIndex } from '@/routes/bookings';
import { store as initiatePayment } from '@/routes/bookings/payment';
import type { PaymentBooking, PaymentSummary } from '@/types/payment';
import { formatBookingDateTime } from '@/lib/bookingDateTime';

const props = defineProps<{
    booking: PaymentBooking;
    payment: PaymentSummary | null;
}>();
const page = usePage<{ bookingTimezone: string }>();

const form = useForm<{ payment?: string }>({});

function pay() {
    form.post(initiatePayment.url(props.booking.id));
}

function formatAmount() {
    if (
        props.booking.amount_minor === null ||
        props.booking.currency === null
    ) {
        return 'Price requires review';
    }
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: props.booking.currency,
    }).format(props.booking.amount_minor / 100);
}
</script>

<template>
    <div class="w-full flex-1 p-4 md:p-6">
        <Head title="Booking payment" />
        <Card class="mx-auto max-w-3xl">
            <CardContent class="space-y-6 py-6">
                <Link :href="bookingIndex()" class="text-sm underline">
                    My bookings
                </Link>
                <h1 class="text-2xl font-semibold">Booking payment</h1>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-muted-foreground">Reference</dt>
                        <dd>{{ booking.reference }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Facility</dt>
                        <dd>{{ booking.resource_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Booking status</dt>
                        <dd>{{ booking.status_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Financial status</dt>
                        <dd>{{ booking.financial_status_label }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Starts</dt>
                        <dd>
                            {{
                                formatBookingDateTime(
                                    booking.starts_at,
                                    page.props.bookingTimezone,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Ends</dt>
                        <dd>
                            {{
                                formatBookingDateTime(
                                    booking.ends_at,
                                    page.props.bookingTimezone,
                                )
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Booking total</dt>
                        <dd class="text-lg font-semibold">
                            {{ formatAmount() }}
                        </dd>
                    </div>
                    <div v-if="booking.payment_due_at">
                        <dt class="text-muted-foreground">Payment deadline</dt>
                        <dd>
                            {{
                                formatBookingDateTime(
                                    booking.payment_due_at,
                                    page.props.bookingTimezone,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
                <BookingPaymentStatus
                    :booking-status="booking.status"
                    :financial-status="booking.financial_status"
                    :payment="payment"
                />
                <p
                    v-if="
                        booking.unavailable_reason &&
                        booking.status !== 'confirmed'
                    "
                    class="text-sm text-muted-foreground"
                >
                    {{ booking.unavailable_reason }}
                </p>
                <p
                    v-if="form.errors.payment"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.payment }}
                </p>
                <form v-if="booking.can_pay" @submit.prevent="pay">
                    <p class="mb-4 text-sm text-muted-foreground">
                        Stripe securely collects your card details. A new
                        checkout needs at least 30 minutes before the deadline.
                    </p>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing || payment?.status === 'processing'
                        "
                    >
                        {{
                            form.processing
                                ? 'Opening secure checkout…'
                                : payment?.status === 'pending'
                                  ? 'Resume secure checkout'
                                  : 'Pay securely with Stripe'
                        }}
                    </Button>
                </form>
                <Button
                    variant="outline"
                    @click="router.reload({ only: ['booking', 'payment'] })"
                    >Refresh payment status</Button
                >
            </CardContent>
        </Card>
    </div>
</template>
