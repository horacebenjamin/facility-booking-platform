<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as availabilityIndex } from '@/routes/availability';
import { index as bookingIndex } from '@/routes/bookings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="w-full flex-1 p-4 md:p-6">
        <Card class="mx-auto w-full max-w-3xl">
            <CardContent class="flex flex-col gap-4 py-4 sm:py-8">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Welcome to Facility4Hire
                </h1>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                    Find an available facility and submit a booking request for
                    management approval.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button as-child>
                        <Link :href="availabilityIndex()">
                            Find a Facility
                        </Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link :href="bookingIndex()">My bookings</Link>
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
