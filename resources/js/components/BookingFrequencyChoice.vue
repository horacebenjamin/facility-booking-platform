<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';

const bookingType = defineModel<'one_off' | 'recurring'>({ required: true });
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Booking frequency</CardTitle>
            <CardDescription>
                The one-off journey keeps the current date and time. A recurring
                request repeats it weekly.
            </CardDescription>
        </CardHeader>
        <CardContent class="grid gap-3 sm:grid-cols-2">
            <Label
                for="booking-type-one-off"
                class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                :class="
                    bookingType === 'one_off'
                        ? 'border-primary bg-primary/5'
                        : ''
                "
            >
                <input
                    id="booking-type-one-off"
                    v-model="bookingType"
                    type="radio"
                    value="one_off"
                    class="mt-1 size-4"
                />
                <span>
                    <span class="block font-medium">One-off booking</span>
                    <span
                        class="block text-sm font-normal text-muted-foreground"
                    >
                        Request this date and time once.
                    </span>
                </span>
            </Label>
            <Label
                for="booking-type-recurring"
                class="flex cursor-pointer items-start gap-3 rounded-lg border p-4"
                :class="
                    bookingType === 'recurring'
                        ? 'border-primary bg-primary/5'
                        : ''
                "
            >
                <input
                    id="booking-type-recurring"
                    v-model="bookingType"
                    type="radio"
                    value="recurring"
                    class="mt-1 size-4"
                />
                <span>
                    <span class="block font-medium">Recurring booking</span>
                    <span
                        class="block text-sm font-normal text-muted-foreground"
                    >
                        Repeat weekly and review every date.
                    </span>
                </span>
            </Label>
        </CardContent>
    </Card>
</template>
