<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import RecurringOccurrenceList from '@/components/RecurringOccurrenceList.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    canSubmitRecurringBooking,
    isRecurringBookingPreviewResponse,
    isRecurringBookingSubmissionResponse,
    recurringBookingError,
    recurringBookingSelectionPayload,
    recurringBookingSubmissionPayload,
    sendRecurringBookingPreview,
    sendRecurringBookingRequest,
    withBookingOrganisation,
} from '@/lib/booking';
import type {
    BookingReviewSelection,
    RecurrenceConstraints,
    RecurringBookingConfirmation,
    RecurringBookingPreviewData,
    RecurringBookingSelectionPayload,
} from '@/types/booking';

const props = defineProps<{
    selection: BookingReviewSelection;
    recurrence: RecurrenceConstraints;
    organisationId?: number | null;
}>();

const emit = defineEmits<{
    confirmed: [confirmation: RecurringBookingConfirmation];
}>();

const intervalWeeks = ref(1);
const occurrenceCount = ref(4);
const preview = ref<RecurringBookingPreviewData | null>(null);
const acceptedAvailableOccurrences = ref(false);
const isPreviewing = ref(false);
const isSubmitting = ref(false);
const error = ref('');
const intervalError = ref('');
const countError = ref('');

const canSubmit = computed(() =>
    canSubmitRecurringBooking(
        preview.value,
        acceptedAvailableOccurrences.value,
    ),
);

const submissionCount = computed(
    () => preview.value?.valid_occurrence_indexes.length ?? 0,
);

const submitLabel = computed(
    () =>
        'Submit ' +
        submissionCount.value +
        ' recurring booking request' +
        (submissionCount.value === 1 ? '' : 's'),
);

function csrfToken(): string | undefined {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length))
        : undefined;
}

function validateInputs(): boolean {
    intervalError.value = '';
    countError.value = '';

    if (
        !Number.isInteger(intervalWeeks.value) ||
        intervalWeeks.value < props.recurrence.minimum_interval_weeks ||
        intervalWeeks.value > props.recurrence.maximum_interval_weeks
    ) {
        intervalError.value =
            'Choose between ' +
            props.recurrence.minimum_interval_weeks +
            ' and ' +
            props.recurrence.maximum_interval_weeks +
            ' weeks.';
    }

    if (
        !Number.isInteger(occurrenceCount.value) ||
        occurrenceCount.value < props.recurrence.minimum_occurrences ||
        occurrenceCount.value > props.recurrence.maximum_occurrences
    ) {
        countError.value =
            'Choose between ' +
            props.recurrence.minimum_occurrences +
            ' and ' +
            props.recurrence.maximum_occurrences +
            ' bookings.';
    }

    return intervalError.value === '' && countError.value === '';
}

function selectionPayload(): RecurringBookingSelectionPayload {
    return withBookingOrganisation(
        recurringBookingSelectionPayload(
            props.selection,
            intervalWeeks.value,
            occurrenceCount.value,
            props.recurrence.timezone,
        ),
        props.organisationId ?? null,
    );
}

watch(
    () => props.organisationId,
    () => {
        preview.value = null;
        acceptedAvailableOccurrences.value = false;
    },
);

async function previewOccurrences(): Promise<void> {
    if (!validateInputs() || isPreviewing.value || isSubmitting.value) {
        return;
    }

    isPreviewing.value = true;
    error.value = '';
    preview.value = null;
    acceptedAvailableOccurrences.value = false;

    try {
        const response = await sendRecurringBookingPreview(
            selectionPayload(),
            csrfToken(),
        );
        const payload: unknown = await response.json();

        if (!response.ok || !isRecurringBookingPreviewResponse(payload)) {
            error.value = recurringBookingError(response.status);

            return;
        }

        preview.value = payload.data;
    } catch {
        error.value = recurringBookingError(500);
    } finally {
        isPreviewing.value = false;
    }
}

function acceptAvailableOccurrences(): void {
    if (
        preview.value === null ||
        preview.value.conflict_count === 0 ||
        preview.value.valid_occurrence_indexes.length === 0
    ) {
        return;
    }

    acceptedAvailableOccurrences.value = true;
    error.value = '';
}

function changeRecurrence(): void {
    preview.value = null;
    acceptedAvailableOccurrences.value = false;
    error.value = '';
}

async function submitRecurringRequest(): Promise<void> {
    if (!canSubmit.value || preview.value === null || isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    error.value = '';
    const submissionMode =
        preview.value.conflict_count === 0
            ? 'all_occurrences'
            : 'available_occurrences';
    const submission = recurringBookingSubmissionPayload(
        selectionPayload(),
        submissionMode,
        preview.value.valid_occurrence_indexes,
    );

    try {
        const response = await sendRecurringBookingRequest(
            submission,
            csrfToken(),
        );
        const payload: unknown = await response.json();

        if (
            response.status === 409 &&
            isRecurringBookingPreviewResponse(payload)
        ) {
            preview.value = payload.data;
            acceptedAvailableOccurrences.value = false;
            error.value = recurringBookingError(response.status);

            return;
        }

        if (!response.ok || !isRecurringBookingSubmissionResponse(payload)) {
            error.value = recurringBookingError(response.status);

            return;
        }

        emit('confirmed', payload.data);
    } catch {
        error.value = recurringBookingError(500);
    } finally {
        isSubmitting.value = false;
    }
}

watch([intervalWeeks, occurrenceCount], () => {
    preview.value = null;
    acceptedAvailableOccurrences.value = false;
    error.value = '';
});
</script>

<template>
    <div class="space-y-6">
        <Card>
            <CardHeader>
                <CardTitle>Recurrence</CardTitle>
                <CardDescription>
                    Repeat this booking weekly. You will review every generated
                    date before anything is submitted.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="interval-weeks">Repeat every</Label>
                        <div class="flex items-center gap-3">
                            <Input
                                id="interval-weeks"
                                v-model.number="intervalWeeks"
                                type="number"
                                :min="recurrence.minimum_interval_weeks"
                                :max="recurrence.maximum_interval_weeks"
                                class="max-w-24"
                                :aria-invalid="Boolean(intervalError)"
                                aria-describedby="interval-weeks-error"
                            />
                            <span class="text-sm text-muted-foreground">
                                week(s)
                            </span>
                        </div>
                        <InputError
                            id="interval-weeks-error"
                            :message="intervalError"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="occurrence-count">
                            Number of bookings
                        </Label>
                        <Input
                            id="occurrence-count"
                            v-model.number="occurrenceCount"
                            type="number"
                            :min="recurrence.minimum_occurrences"
                            :max="recurrence.maximum_occurrences"
                            class="max-w-32"
                            :aria-invalid="Boolean(countError)"
                            aria-describedby="occurrence-count-error"
                        />
                        <InputError
                            id="occurrence-count-error"
                            :message="countError"
                        />
                    </div>
                </div>

                <p class="text-sm text-muted-foreground">
                    Availability and prices are calculated by Facility4Hire for
                    every booking.
                </p>

                <Button
                    type="button"
                    :disabled="isPreviewing || isSubmitting"
                    @click="previewOccurrences"
                >
                    <Spinner v-if="isPreviewing" />
                    {{
                        isPreviewing
                            ? 'Checking every date…'
                            : 'Preview recurring dates'
                    }}
                </Button>
            </CardContent>
        </Card>

        <Alert v-if="error" variant="destructive">
            <AlertTitle>Review the recurring request</AlertTitle>
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <RecurringOccurrenceList v-if="preview" :preview="preview" />

        <Card v-if="preview">
            <CardHeader>
                <CardTitle>Submit recurring request</CardTitle>
                <CardDescription v-if="preview.conflict_count === 0">
                    Every occurrence is currently available. All dates will be
                    revalidated and repriced when you submit.
                </CardDescription>
                <CardDescription v-else>
                    {{ preview.conflict_count }} occurrence(s) conflict. Choose
                    how you want to proceed.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-5">
                <template v-if="preview.conflict_count > 0">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="isSubmitting"
                            @click="changeRecurrence"
                        >
                            Change recurring selection
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="
                                isSubmitting ||
                                preview.valid_occurrence_indexes.length === 0
                            "
                            @click="acceptAvailableOccurrences"
                        >
                            Continue with available dates only
                        </Button>
                    </div>

                    <Alert v-if="acceptedAvailableOccurrences">
                        <AlertTitle>Available dates selected</AlertTitle>
                        <AlertDescription>
                            You explicitly chose to submit the
                            {{ submissionCount }} available occurrence(s). The
                            conflicted dates will not be created. The selected
                            dates will be checked again on submission.
                        </AlertDescription>
                    </Alert>
                </template>

                <Button
                    v-if="canSubmit"
                    type="button"
                    :disabled="isSubmitting"
                    @click="submitRecurringRequest"
                >
                    <Spinner v-if="isSubmitting" />
                    {{
                        isSubmitting
                            ? 'Submitting recurring request…'
                            : submitLabel
                    }}
                </Button>
                <p v-if="isSubmitting" class="sr-only" role="status">
                    Revalidating and submitting the selected recurring booking
                    requests.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
