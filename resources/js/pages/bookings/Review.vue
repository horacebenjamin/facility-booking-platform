<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import BookingConfirmation from '@/components/BookingConfirmation.vue';
import BookingFrequencyChoice from '@/components/BookingFrequencyChoice.vue';
import BookingOwnerChoice from '@/components/BookingOwnerChoice.vue';
import BookingSummary from '@/components/BookingSummary.vue';
import PageHeader from '@/components/PageHeader.vue';
import RecurringBookingConfirmation from '@/components/RecurringBookingConfirmation.vue';
import RecurringBookingReview from '@/components/RecurringBookingReview.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    bookingReviewQuery,
    bookingSubmissionError,
    bookingSubmissionPayload,
    canStartBookingSubmission,
    isBookingSubmissionResponse,
    sendBookingRequest,
    withBookingOrganisation,
} from '@/lib/booking';
import { dashboard } from '@/routes';
import { index as availabilityIndex } from '@/routes/availability';
import {
    index as bookingIndex,
    review as reviewBooking,
} from '@/routes/bookings';
import type {
    BookingCustomer,
    BookingOrganisationContext,
    BookingReviewQuote,
    BookingReviewSelection,
    BookingSubmissionResponse,
    RecurrenceConstraints,
    RecurringBookingConfirmation as RecurringBookingConfirmationData,
} from '@/types/booking';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Find a facility', href: availabilityIndex() },
            { title: 'Review booking', href: reviewBooking() },
        ],
    },
});

const props = defineProps<{
    selection: BookingReviewSelection;
    quote: BookingReviewQuote;
    customer: BookingCustomer;
    bookingContexts: BookingOrganisationContext[];
    recurrence: RecurrenceConstraints;
}>();

const bookingType = ref<'one_off' | 'recurring'>('one_off');
const organisationId = ref<number | null>(null);
const isSubmitting = ref(false);
const submitError = ref('');
const confirmation = ref<BookingSubmissionResponse['data'] | null>(null);
const recurringConfirmation = ref<RecurringBookingConfirmationData | null>(
    null,
);
const changeSelectionHref = availabilityIndex({
    query: bookingReviewQuery(bookingSubmissionPayload(props.selection)),
});

function csrfToken(): string | undefined {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length))
        : undefined;
}

async function submitBookingRequest(): Promise<void> {
    if (
        !canStartBookingSubmission(
            isSubmitting.value,
            confirmation.value !== null,
        )
    ) {
        return;
    }

    isSubmitting.value = true;
    submitError.value = '';
    try {
        const response = await sendBookingRequest(
            withBookingOrganisation(
                bookingSubmissionPayload(props.selection),
                organisationId.value,
            ),
            csrfToken(),
        );
        const payload: unknown = await response.json();

        if (!response.ok || !isBookingSubmissionResponse(payload)) {
            submitError.value = bookingSubmissionError(response.status);

            return;
        }

        confirmation.value = payload.data;
    } catch {
        submitError.value = bookingSubmissionError(500);
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="w-full min-w-0 flex-1 p-4 md:p-6">
        <Head
            :title="
                confirmation || recurringConfirmation
                    ? 'Booking request received'
                    : 'Review booking'
            "
        />

        <div class="mx-auto max-w-4xl min-w-0 space-y-6">
            <PageHeader
                :title="
                    confirmation || recurringConfirmation
                        ? 'Request submitted'
                        : bookingType === 'one_off'
                          ? 'Review your booking'
                          : 'Set up recurring bookings'
                "
                :description="
                    confirmation || recurringConfirmation
                        ? 'Your request still requires management review.'
                        : 'Choose whether this is one booking or a weekly series, then review everything before sending it for management approval.'
                "
            />
            <nav aria-label="Booking progress">
                <ol class="grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                    <li class="rounded-md bg-muted px-2 py-2 text-center">
                        Details
                    </li>
                    <li class="rounded-md bg-muted px-2 py-2 text-center">
                        Availability
                    </li>
                    <li
                        class="rounded-md px-2 py-2 text-center font-medium"
                        :aria-current="
                            confirmation || recurringConfirmation
                                ? undefined
                                : 'step'
                        "
                        :class="
                            confirmation || recurringConfirmation
                                ? 'bg-muted'
                                : 'bg-primary text-primary-foreground'
                        "
                    >
                        Review
                    </li>
                    <li
                        class="rounded-md px-2 py-2 text-center font-medium"
                        :aria-current="
                            confirmation || recurringConfirmation
                                ? 'step'
                                : undefined
                        "
                        :class="
                            confirmation || recurringConfirmation
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted'
                        "
                    >
                        Confirmation
                    </li>
                </ol>
            </nav>

            <template v-if="confirmation || recurringConfirmation">
                <RecurringBookingConfirmation
                    v-if="recurringConfirmation"
                    :confirmation="recurringConfirmation"
                />
                <BookingConfirmation
                    v-else-if="confirmation"
                    :booking="confirmation"
                    :selection="selection"
                    :quote="quote"
                />
                <div class="flex flex-col gap-3 sm:flex-row">
                    <Button as-child>
                        <Link :href="availabilityIndex()">
                            Find another facility
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="bookingIndex()">My bookings</Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="dashboard()">Go to dashboard</Link>
                    </Button>
                </div>
            </template>

            <template v-else>
                <BookingOwnerChoice
                    v-if="bookingContexts.length > 0"
                    v-model="organisationId"
                    :customer="customer"
                    :contexts="bookingContexts"
                />

                <BookingFrequencyChoice v-model="bookingType" />

                <BookingSummary
                    :selection="selection"
                    :quote="quote"
                    :customer="customer"
                    :show-price="bookingType === 'one_off'"
                />

                <Card v-if="bookingType === 'one_off'">
                    <CardHeader>
                        <CardTitle>Before you submit</CardTitle>
                        <CardDescription>
                            Checking availability did not reserve or confirm
                            this booking.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <p class="text-sm leading-6 text-muted-foreground">
                            Availability and pricing will be revalidated by
                            Facility4Hire when you submit. The price shown above
                            is an estimate, and every request still requires
                            management approval.
                        </p>

                        <Alert v-if="submitError" variant="destructive">
                            <AlertTitle>
                                We could not submit this request
                            </AlertTitle>
                            <AlertDescription>
                                {{ submitError }}
                            </AlertDescription>
                        </Alert>

                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Button
                                type="button"
                                :disabled="isSubmitting"
                                @click="submitBookingRequest"
                            >
                                <Spinner v-if="isSubmitting" />
                                {{
                                    isSubmitting
                                        ? 'Submitting booking request…'
                                        : 'Submit booking request'
                                }}
                            </Button>
                            <Button
                                v-if="!isSubmitting"
                                variant="outline"
                                as-child
                            >
                                <Link :href="changeSelectionHref">
                                    Change selection
                                </Link>
                            </Button>
                            <Button v-else variant="outline" disabled>
                                Change selection
                            </Button>
                        </div>
                        <p v-if="isSubmitting" class="sr-only" role="status">
                            Submitting your booking request.
                        </p>
                    </CardContent>
                </Card>

                <RecurringBookingReview
                    v-else
                    :selection="selection"
                    :recurrence="recurrence"
                    :organisation-id="organisationId"
                    @confirmed="recurringConfirmation = $event"
                />
            </template>
        </div>
    </div>
</template>
