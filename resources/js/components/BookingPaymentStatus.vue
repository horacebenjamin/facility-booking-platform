<script setup lang="ts">
import { computed } from 'vue';
import type { PaymentSummary } from '@/types/payment';

const props = defineProps<{
    bookingStatus: string;
    financialStatus: string;
    payment: PaymentSummary | null;
}>();

const message = computed(() => {
    if (
        props.bookingStatus === 'confirmed' &&
        props.financialStatus === 'paid'
    ) {
        return 'Payment received. Your booking is confirmed.';
    }
    if (props.payment?.requires_review) {
        return 'Payment received. Your booking needs a centre review before confirmation. Please contact the centre.';
    }
    if (props.payment?.status === 'processing') {
        return 'Your payment is processing. Your booking is not confirmed yet. Please wait for the verified payment result.';
    }
    if (props.payment?.status === 'pending') {
        return 'Your payment is awaiting a verified result. Returning from Stripe does not confirm your booking. You can resume the same checkout safely.';
    }
    if (
        props.payment?.status === 'failed' ||
        props.payment?.status === 'expired'
    ) {
        return 'This payment was unsuccessful or the checkout expired. Your booking is not confirmed. You may retry if payment is still available.';
    }
    if (props.bookingStatus === 'approved') {
        return 'Your booking is approved, but it is not confirmed until payment has been verified.';
    }
    return 'Payment becomes available after management approval.';
});
</script>

<template>
    <p role="status" aria-live="polite" class="text-sm leading-6">
        {{ message }}
    </p>
</template>
