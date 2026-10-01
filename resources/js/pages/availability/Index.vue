<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AvailabilityResult from '@/components/AvailabilityResult.vue';
import InputError from '@/components/InputError.vue';
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
import { check as checkAvailability } from '@/routes/availability';
import { quote as quotePricing } from '@/routes/pricing';
import type {
    AvailabilityReason,
    AvailabilityResponse,
    Centre,
    Equipment,
    Facility,
    PricingResponse,
    Resource,
} from '@/types/availability';

const props = defineProps<{
    centres: Centre[];
    facilities: Facility[];
    resources: Resource[];
    equipment: Equipment[];
}>();

const selectedCentreId = ref<number | null>(null);
const selectedFacilityId = ref<number | null>(null);
const selectedResourceId = ref<number | null>(null);
const selectedDate = ref('');
const startsAt = ref('');
const endsAt = ref('');
const selectedEquipment = ref<Record<number, number>>({});
const result = ref<AvailabilityResponse | null>(null);
const quote = ref<PricingResponse | null>(null);
const fieldErrors = ref<Record<string, string>>({});
const requestError = ref('');
const quoteError = ref('');
const isChecking = ref(false);
const isQuoting = ref(false);
const requestVersion = ref(0);

const facilitiesForCentre = computed(() =>
    props.facilities.filter(
        (facility) => facility.centre_id === selectedCentreId.value,
    ),
);

const resourcesForFacility = computed(() =>
    props.resources.filter(
        (resource) => resource.facility_id === selectedFacilityId.value,
    ),
);

const compatibleEquipment = computed(() =>
    props.equipment.filter(
        (equipment) =>
            equipment.centre_id === selectedCentreId.value &&
            (equipment.facility_id === null ||
                equipment.facility_id === selectedFacilityId.value),
    ),
);

const selectedEquipmentItems = computed(() =>
    compatibleEquipment.value
        .filter(
            (equipment) => selectedEquipment.value[equipment.id] !== undefined,
        )
        .map((equipment) => ({
            equipment_id: equipment.id,
            quantity: selectedEquipment.value[equipment.id],
        })),
);

const selectedCentre = computed(() =>
    props.centres.find((centre) => centre.id === selectedCentreId.value),
);

const selectedFacility = computed(() =>
    props.facilities.find(
        (facility) => facility.id === selectedFacilityId.value,
    ),
);

const duration = computed(() => {
    if (!selectedDate.value || !startsAt.value || !endsAt.value) {
        return null;
    }

    const start = new Date(`${selectedDate.value}T${startsAt.value}`);
    const end = new Date(`${selectedDate.value}T${endsAt.value}`);
    const minutes = Math.round((end.getTime() - start.getTime()) / 60000);

    if (!Number.isFinite(minutes) || minutes <= 0) {
        return null;
    }

    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return [
        hours > 0 ? `${hours} hr${hours === 1 ? '' : 's'}` : '',
        remainingMinutes > 0
            ? `${remainingMinutes} min${remainingMinutes === 1 ? '' : 's'}`
            : '',
    ]
        .filter(Boolean)
        .join(' ');
});

function clearAvailabilityState(): void {
    requestVersion.value += 1;
    result.value = null;
    quote.value = null;
    requestError.value = '';
    quoteError.value = '';
    isChecking.value = false;
    isQuoting.value = false;
}

function clearFieldError(field: string): void {
    delete fieldErrors.value[field];
}

function resetEquipmentToCompatibleOptions(): void {
    selectedEquipment.value = Object.fromEntries(
        Object.entries(selectedEquipment.value).filter(([equipmentId]) =>
            compatibleEquipment.value.some(
                (equipment) => equipment.id === Number(equipmentId),
            ),
        ),
    );
}

function updateEquipmentSelection(equipment: Equipment, event: Event): void {
    const input = event.target as HTMLInputElement;

    if (input.checked) {
        selectedEquipment.value[equipment.id] = 1;
    } else {
        delete selectedEquipment.value[equipment.id];
    }

    clearAvailabilityState();
}

function availabilityReasonMessage(reason: AvailabilityReason): string {
    const messages: Record<AvailabilityReason, string> = {
        invalid_period: 'Choose a valid start and end time.',
        inactive_centre: 'This venue is not currently available.',
        inactive_facility: 'This venue is not currently available.',
        inactive_resource: 'This resource is not currently available.',
        outside_bookable_hours:
            'That time is outside the bookable hours for this facility.',
        resource_conflict:
            'That resource is not available for the selected time.',
        blockout: 'That resource is unavailable during the selected time.',
        equipment_unavailable:
            'Some of the requested equipment is not available in the quantity selected.',
    };

    return messages[reason];
}

function isAvailabilityReason(reason: unknown): reason is AvailabilityReason {
    return [
        'invalid_period',
        'inactive_centre',
        'inactive_facility',
        'inactive_resource',
        'outside_bookable_hours',
        'resource_conflict',
        'blockout',
        'equipment_unavailable',
    ].includes(reason as AvailabilityReason);
}

const resultReasons = computed(() => {
    if (!result.value) {
        return [];
    }

    return [
        ...new Set(result.value.data.reasons.map(availabilityReasonMessage)),
    ];
});

function dateTime(date: string, time: string): string {
    return `${date} ${time.length === 5 ? `${time}:00` : time}`;
}

function selectionPayload(): {
    resource_id: number | null;
    starts_at: string;
    ends_at: string;
    equipment: { equipment_id: number; quantity: number }[];
} {
    return {
        resource_id: selectedResourceId.value,
        starts_at: dateTime(selectedDate.value, startsAt.value),
        ends_at: dateTime(selectedDate.value, endsAt.value),
        equipment: selectedEquipmentItems.value,
    };
}

function csrfToken(): string | undefined {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.substring('XSRF-TOKEN='.length))
        : undefined;
}

function setClientValidationErrors(): boolean {
    const errors: Record<string, string> = {};

    if (!selectedCentreId.value) {
        errors.centre_id = 'Choose a venue.';
    }

    if (!selectedFacilityId.value) {
        errors.facility_id = 'Choose a facility.';
    }

    if (!selectedResourceId.value) {
        errors.resource_id = 'Choose a resource.';
    }

    if (!selectedDate.value) {
        errors.date = 'Choose a date.';
    }

    if (!startsAt.value) {
        errors.starts_at = 'Choose a start time.';
    }

    if (!endsAt.value) {
        errors.ends_at = 'Choose an end time.';
    } else if (!duration.value) {
        errors.ends_at = 'End time must be later than start time.';
    }

    fieldErrors.value = errors;

    return Object.keys(errors).length > 0;
}

function responseValidationErrors(payload: unknown): Record<string, string> {
    if (
        typeof payload !== 'object' ||
        payload === null ||
        !('errors' in payload) ||
        typeof payload.errors !== 'object' ||
        payload.errors === null
    ) {
        return {};
    }

    return Object.fromEntries(
        Object.entries(payload.errors).flatMap(([field, messages]) =>
            Array.isArray(messages) && typeof messages[0] === 'string'
                ? [[field, messages[0]]]
                : [],
        ),
    );
}

function isAvailabilityResponse(
    payload: unknown,
): payload is AvailabilityResponse {
    return (
        typeof payload === 'object' &&
        payload !== null &&
        'data' in payload &&
        typeof payload.data === 'object' &&
        payload.data !== null &&
        'available' in payload.data &&
        'reasons' in payload.data &&
        typeof payload.data.available === 'boolean' &&
        Array.isArray(payload.data.reasons) &&
        payload.data.reasons.every(isAvailabilityReason)
    );
}

function isPricingResponse(payload: unknown): payload is PricingResponse {
    if (
        typeof payload !== 'object' ||
        payload === null ||
        !('data' in payload) ||
        typeof payload.data !== 'object' ||
        payload.data === null
    ) {
        return false;
    }

    const data = payload.data;

    return (
        'currency' in data &&
        'duration_seconds' in data &&
        'resource' in data &&
        'equipment' in data &&
        'subtotal_minor' in data &&
        'calculated_total_minor' in data &&
        'final_total_minor' in data &&
        typeof data.currency === 'string' &&
        typeof data.duration_seconds === 'number' &&
        Array.isArray(data.equipment) &&
        typeof data.subtotal_minor === 'number' &&
        typeof data.calculated_total_minor === 'number' &&
        typeof data.final_total_minor === 'number'
    );
}

function formatMoney(amountMinor: number, currency: string): string {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);
}

function formatDuration(durationSeconds: number): string {
    const minutes = Math.round(durationSeconds / 60);
    const hours = Math.floor(minutes / 60);
    const remainingMinutes = minutes % 60;

    return [
        hours > 0 ? `${hours} hour${hours === 1 ? '' : 's'}` : '',
        remainingMinutes > 0
            ? `${remainingMinutes} minute${remainingMinutes === 1 ? '' : 's'}`
            : '',
    ]
        .filter(Boolean)
        .join(' ');
}

async function requestQuote(
    version: number,
    selection: ReturnType<typeof selectionPayload>,
): Promise<void> {
    isQuoting.value = true;
    const pricingRoute = quotePricing();

    try {
        const response = await fetch(pricingRoute.url, {
            method: pricingRoute.method.toUpperCase(),
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken() ? { 'X-XSRF-TOKEN': csrfToken() } : {}),
            },
            body: JSON.stringify(selection),
        });
        const payload: unknown = await response.json();

        if (version !== requestVersion.value) {
            return;
        }

        if (!response.ok || !isPricingResponse(payload)) {
            quoteError.value =
                'Pricing is not available for this selection right now.';

            return;
        }

        quote.value = payload;
    } catch {
        if (version !== requestVersion.value) {
            return;
        }

        quoteError.value =
            'Pricing is not available for this selection right now.';
    } finally {
        if (version === requestVersion.value) {
            isQuoting.value = false;
        }
    }
}

async function submit(): Promise<void> {
    clearAvailabilityState();

    if (setClientValidationErrors()) {
        return;
    }

    isChecking.value = true;
    const version = requestVersion.value;
    const selection = selectionPayload();

    try {
        const response = await fetch(checkAvailability.url(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(csrfToken() ? { 'X-XSRF-TOKEN': csrfToken() } : {}),
            },
            body: JSON.stringify(selection),
        });
        const payload: unknown = await response.json();

        if (version !== requestVersion.value) {
            return;
        }

        if (response.status === 422) {
            fieldErrors.value = responseValidationErrors(payload);
            requestError.value =
                'Please correct the highlighted fields and try again.';

            return;
        }

        if (!response.ok || !isAvailabilityResponse(payload)) {
            requestError.value =
                'We could not check availability right now. Please try again.';

            return;
        }

        fieldErrors.value = {};
        result.value = payload;

        if (!payload.data.available) {
            return;
        }

        isChecking.value = false;
        await requestQuote(version, selection);
    } catch {
        if (version !== requestVersion.value) {
            return;
        }

        requestError.value =
            'We could not check availability right now. Please try again.';
    } finally {
        if (version === requestVersion.value) {
            isChecking.value = false;
        }
    }
}

watch(selectedCentreId, () => {
    selectedFacilityId.value = null;
    selectedResourceId.value = null;
    resetEquipmentToCompatibleOptions();
    clearFieldError('centre_id');
    clearAvailabilityState();
});

watch(selectedFacilityId, () => {
    selectedResourceId.value = null;
    resetEquipmentToCompatibleOptions();
    clearFieldError('facility_id');
    clearAvailabilityState();
});

watch(selectedResourceId, () => {
    clearFieldError('resource_id');
    clearAvailabilityState();
});

watch([selectedDate, startsAt, endsAt], () => {
    fieldErrors.value = {};
    clearAvailabilityState();
});

watch(
    selectedEquipment,
    () => {
        clearAvailabilityState();
    },
    { deep: true },
);
</script>

<template>
    <Head title="Check availability" />

    <main class="min-h-screen bg-muted/30 px-4 py-8 sm:px-6 sm:py-12">
        <div
            class="mx-auto grid max-w-5xl gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]"
        >
            <section class="space-y-8">
                <header class="max-w-2xl space-y-2">
                    <p class="text-sm font-medium text-muted-foreground">
                        Venue booking
                    </p>
                    <h1
                        class="text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Check availability
                    </h1>
                    <p class="text-muted-foreground">
                        Choose a venue, resource and time to check current
                        availability.
                    </p>
                </header>

                <form class="space-y-6" @submit.prevent="submit" novalidate>
                    <Card>
                        <CardHeader>
                            <CardTitle>Venue</CardTitle>
                            <CardDescription
                                >Choose where you would like to
                                book.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="grid gap-5 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="centre">Centre</Label>
                                <select
                                    id="centre"
                                    v-model="selectedCentreId"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                    :aria-invalid="
                                        Boolean(fieldErrors.centre_id)
                                    "
                                    aria-describedby="centre-error"
                                >
                                    <option :value="null">
                                        Choose a centre
                                    </option>
                                    <option
                                        v-for="centre in centres"
                                        :key="centre.id"
                                        :value="centre.id"
                                    >
                                        {{ centre.name }}
                                    </option>
                                </select>
                                <InputError
                                    id="centre-error"
                                    :message="fieldErrors.centre_id"
                                />
                                <p
                                    v-if="centres.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    No venues are currently available.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="facility">Facility</Label>
                                <select
                                    id="facility"
                                    v-model="selectedFacilityId"
                                    :disabled="!selectedCentreId"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    :aria-invalid="
                                        Boolean(fieldErrors.facility_id)
                                    "
                                    aria-describedby="facility-error"
                                >
                                    <option :value="null">
                                        Choose a facility
                                    </option>
                                    <option
                                        v-for="facility in facilitiesForCentre"
                                        :key="facility.id"
                                        :value="facility.id"
                                    >
                                        {{ facility.name }}
                                    </option>
                                </select>
                                <InputError
                                    id="facility-error"
                                    :message="fieldErrors.facility_id"
                                />
                                <p
                                    v-if="
                                        selectedCentreId &&
                                        facilitiesForCentre.length === 0
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    No facilities are currently available at
                                    this venue.
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="resource">Resource</Label>
                                <select
                                    id="resource"
                                    v-model="selectedResourceId"
                                    :disabled="!selectedFacilityId"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                                    :aria-invalid="
                                        Boolean(fieldErrors.resource_id)
                                    "
                                    aria-describedby="resource-error"
                                >
                                    <option :value="null">
                                        Choose a resource
                                    </option>
                                    <option
                                        v-for="resource in resourcesForFacility"
                                        :key="resource.id"
                                        :value="resource.id"
                                    >
                                        {{ resource.name }}
                                    </option>
                                </select>
                                <InputError
                                    id="resource-error"
                                    :message="fieldErrors.resource_id"
                                />
                                <p
                                    v-if="
                                        selectedFacilityId &&
                                        resourcesForFacility.length === 0
                                    "
                                    class="text-sm text-muted-foreground"
                                >
                                    No bookable resources are currently
                                    available for this facility.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Date and time</CardTitle>
                            <CardDescription
                                >Booking lengths vary by resource. Choose the
                                time you need.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="grid gap-5 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="date">Date</Label>
                                <Input
                                    id="date"
                                    v-model="selectedDate"
                                    type="date"
                                    :aria-invalid="Boolean(fieldErrors.date)"
                                    aria-describedby="date-error"
                                />
                                <InputError
                                    id="date-error"
                                    :message="fieldErrors.date"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="starts-at">Start time</Label>
                                <Input
                                    id="starts-at"
                                    v-model="startsAt"
                                    type="time"
                                    :aria-invalid="
                                        Boolean(fieldErrors.starts_at)
                                    "
                                    aria-describedby="starts-at-error"
                                />
                                <InputError
                                    id="starts-at-error"
                                    :message="fieldErrors.starts_at"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ends-at">End time</Label>
                                <Input
                                    id="ends-at"
                                    v-model="endsAt"
                                    type="time"
                                    :aria-invalid="Boolean(fieldErrors.ends_at)"
                                    aria-describedby="ends-at-error"
                                />
                                <InputError
                                    id="ends-at-error"
                                    :message="fieldErrors.ends_at"
                                />
                            </div>
                        </CardContent>
                        <CardContent
                            v-if="duration"
                            class="pt-0 text-sm text-muted-foreground"
                            >Selected duration:
                            <span class="font-medium text-foreground">{{
                                duration
                            }}</span></CardContent
                        >
                    </Card>

                    <Card v-if="selectedCentreId">
                        <CardHeader>
                            <CardTitle
                                >Equipment
                                <span class="font-normal text-muted-foreground"
                                    >(optional)</span
                                ></CardTitle
                            >
                            <CardDescription
                                >Add equipment to this selection if you need
                                it.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <p
                                v-if="compatibleEquipment.length === 0"
                                class="text-sm text-muted-foreground"
                            >
                                No optional equipment is available for this
                                selection.
                            </p>
                            <div v-else class="space-y-3">
                                <div
                                    v-for="item in compatibleEquipment"
                                    :key="item.id"
                                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[minmax(0,1fr)_8rem] sm:items-center"
                                >
                                    <Label
                                        :for="`equipment-${item.id}`"
                                        class="leading-5"
                                    >
                                        <input
                                            :id="`equipment-${item.id}`"
                                            type="checkbox"
                                            class="size-4 rounded border-input focus-visible:ring-2 focus-visible:ring-ring"
                                            :checked="
                                                selectedEquipment[item.id] !==
                                                undefined
                                            "
                                            @change="
                                                updateEquipmentSelection(
                                                    item,
                                                    $event,
                                                )
                                            "
                                        />
                                        <span
                                            >{{ item.name }}
                                            <span
                                                class="font-normal text-muted-foreground"
                                                >({{
                                                    item.quantity
                                                }}
                                                configured)</span
                                            ></span
                                        >
                                    </Label>
                                    <div class="grid gap-1">
                                        <Label
                                            :for="`equipment-quantity-${item.id}`"
                                            class="text-xs"
                                            >Quantity</Label
                                        >
                                        <Input
                                            :id="`equipment-quantity-${item.id}`"
                                            v-model.number="
                                                selectedEquipment[item.id]
                                            "
                                            type="number"
                                            min="1"
                                            :disabled="
                                                selectedEquipment[item.id] ===
                                                undefined
                                            "
                                            @input="clearAvailabilityState"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Button
                        type="submit"
                        class="w-full sm:w-auto"
                        :disabled="isChecking || isQuoting"
                    >
                        <Spinner v-if="isChecking || isQuoting" />
                        {{
                            isChecking
                                ? 'Checking availability…'
                                : isQuoting
                                  ? 'Calculating price…'
                                  : 'Check availability'
                        }}
                    </Button>
                    <p
                        v-if="isChecking || isQuoting"
                        class="sr-only"
                        role="status"
                    >
                        {{
                            isChecking
                                ? 'Checking current availability.'
                                : 'Calculating the estimated price.'
                        }}
                    </p>
                </form>
            </section>

            <aside class="space-y-4 lg:pt-28" aria-live="polite">
                <AvailabilityResult
                    v-if="result"
                    :result="result"
                    :reasons="resultReasons"
                />
                <Card v-if="quote">
                    <CardHeader>
                        <CardTitle>Estimated price</CardTitle>
                        <CardDescription>
                            Estimated price for this selection.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <dl class="grid gap-3 text-sm">
                            <div class="grid gap-1">
                                <dt class="text-muted-foreground">Centre</dt>
                                <dd class="font-medium">
                                    {{ selectedCentre?.name }}
                                </dd>
                            </div>
                            <div class="grid gap-1">
                                <dt class="text-muted-foreground">Facility</dt>
                                <dd class="font-medium">
                                    {{ selectedFacility?.name }}
                                </dd>
                            </div>
                            <div class="grid gap-1">
                                <dt class="text-muted-foreground">
                                    Bookable option
                                </dt>
                                <dd class="font-medium">
                                    {{ quote.data.resource.name }}
                                </dd>
                            </div>
                            <div class="grid gap-1">
                                <dt class="text-muted-foreground">Date</dt>
                                <dd class="font-medium">
                                    {{ selectedDate }}
                                </dd>
                            </div>
                            <div class="grid gap-1">
                                <dt class="text-muted-foreground">Time</dt>
                                <dd class="font-medium">
                                    {{ startsAt }}–{{ endsAt }}
                                </dd>
                            </div>
                            <div class="grid gap-1">
                                <dt class="text-muted-foreground">Duration</dt>
                                <dd class="font-medium">
                                    {{
                                        formatDuration(
                                            quote.data.duration_seconds,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>

                        <div class="space-y-3 border-t pt-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium">
                                        {{ quote.data.resource.name }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            formatMoney(
                                                quote.data.resource
                                                    .hourly_rate_minor,
                                                quote.data.currency,
                                            )
                                        }}
                                        per hour
                                    </p>
                                </div>
                                <p class="font-medium">
                                    {{
                                        formatMoney(
                                            quote.data.resource.amount_minor,
                                            quote.data.currency,
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                v-for="item in quote.data.equipment"
                                :key="item.name"
                                class="flex items-start justify-between gap-4"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ item.name }} × {{ item.quantity }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            item.charge_type === 'included'
                                                ? 'Included'
                                                : formatMoney(
                                                      item.hourly_rate_minor,
                                                      quote.data.currency,
                                                  ) + ' per hour'
                                        }}
                                    </p>
                                </div>
                                <p class="font-medium">
                                    {{
                                        item.charge_type === 'included'
                                            ? 'Included'
                                            : formatMoney(
                                                  item.amount_minor,
                                                  quote.data.currency,
                                              )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2 border-t pt-4 text-sm">
                            <div class="flex justify-between gap-4">
                                <span>Subtotal</span>
                                <span>{{
                                    formatMoney(
                                        quote.data.subtotal_minor,
                                        quote.data.currency,
                                    )
                                }}</span>
                            </div>
                            <div
                                v-if="quote.data.discount_minor !== null"
                                class="flex justify-between gap-4"
                            >
                                <span>Discount</span>
                                <span>{{
                                    formatMoney(
                                        -quote.data.discount_minor,
                                        quote.data.currency,
                                    )
                                }}</span>
                            </div>
                            <div
                                class="flex justify-between gap-4 border-t pt-2 text-base font-semibold"
                            >
                                <span>Estimated total</span>
                                <span>{{
                                    formatMoney(
                                        quote.data.final_total_minor,
                                        quote.data.currency,
                                    )
                                }}</span>
                            </div>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            Availability and pricing will be rechecked when you
                            submit a booking request.
                        </p>
                    </CardContent>
                </Card>
                <Alert
                    v-else-if="result?.data.available && quoteError"
                    variant="destructive"
                >
                    <AlertTitle>Pricing is unavailable</AlertTitle>
                    <AlertDescription>{{ quoteError }}</AlertDescription>
                </Alert>
                <Alert v-else-if="requestError" variant="destructive">
                    <AlertTitle>We need a little more information.</AlertTitle>
                    <AlertDescription>{{ requestError }}</AlertDescription>
                </Alert>
                <Card v-else class="hidden lg:flex">
                    <CardHeader>
                        <CardTitle>Current availability</CardTitle>
                        <CardDescription
                            >Your result will appear here after you check your
                            selection.</CardDescription
                        >
                    </CardHeader>
                </Card>
            </aside>
        </div>
    </main>
</template>
