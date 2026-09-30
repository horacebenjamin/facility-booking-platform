<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';

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
            <CardContent class="flex flex-col gap-2 py-4 sm:py-8">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Welcome to Facility4Hire
                </h1>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                    Your account is ready. Facility booking and account features
                    will appear here as they become available.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
