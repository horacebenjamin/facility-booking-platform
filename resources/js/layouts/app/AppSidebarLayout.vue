<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <a
            href="#main-content"
            class="sr-only z-50 rounded-md bg-card p-3 text-foreground focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >Skip to content</a
        >
        <AppSidebar />
        <AppContent
            id="main-content"
            tabindex="-1"
            variant="sidebar"
            class="min-w-0 [overflow-wrap:anywhere]"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster />
    </AppShell>
</template>
