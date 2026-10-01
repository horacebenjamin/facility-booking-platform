<script setup lang="ts">
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { AvailabilityResponse } from '@/types/availability';

defineProps<{
    result: AvailabilityResponse;
    reasons: string[];
}>();
</script>

<template>
    <Alert
        :class="
            result.data.available
                ? 'border-green-700/30 bg-green-50 text-green-950 dark:border-green-400/30 dark:bg-green-950/30 dark:text-green-50'
                : 'border-amber-700/30 bg-amber-50 text-amber-950 dark:border-amber-400/30 dark:bg-amber-950/30 dark:text-amber-50'
        "
    >
        <AlertTitle>
            {{
                result.data.available
                    ? 'This time is currently available.'
                    : 'This selection is not currently available.'
            }}
        </AlertTitle>
        <AlertDescription v-if="result.data.available">
            Availability is checked live and will be revalidated when you submit
            a booking request.
        </AlertDescription>
        <AlertDescription v-else>
            <p>Please choose another time or selection.</p>
            <ul v-if="reasons.length" class="mt-2 list-disc space-y-1 pl-5">
                <li v-for="reason in reasons" :key="reason">{{ reason }}</li>
            </ul>
        </AlertDescription>
    </Alert>
</template>
