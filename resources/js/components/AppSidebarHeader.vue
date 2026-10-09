<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import AppLogo from '@/components/AppLogo.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex min-h-18 shrink-0 items-center gap-3 border-b border-border bg-card px-4 md:px-6"
    >
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <AppLogo class="md:hidden" />
            <div class="hidden min-w-0 md:block">
                <template v-if="breadcrumbs && breadcrumbs.length > 0">
                    <Breadcrumbs :breadcrumbs="breadcrumbs" />
                </template>
            </div>
        </div>
    </header>
</template>
