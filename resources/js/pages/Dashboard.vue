<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/PageHeader.vue';
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
    <div class="mx-auto w-full max-w-7xl min-w-0 flex-1 space-y-6 p-4 md:p-6">
        <Head title="Dashboard" />
        <PageHeader
            title="Dashboard"
            description="Welcome to Sheffield Community Venues."
        />
        <Card>
            <CardContent class="flex flex-col gap-4 py-4 sm:py-8">
                <h2 class="text-lg font-semibold">Plan your next booking</h2>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                    Find an available facility and submit a booking request for
                    management approval.
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button as-child>
                        <Link :href="availabilityIndex()">
                            Find a facility
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
