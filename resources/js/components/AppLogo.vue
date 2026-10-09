<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Building2 } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const isCustomerBrand = computed(
    () => !page.props.auth.user || page.props.auth.canUseCustomerArea,
);
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <div
            class="flex size-8 shrink-0 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        >
            <Building2
                v-if="isCustomerBrand"
                class="size-5"
                aria-hidden="true"
            />
            <AppLogoIcon
                v-else
                class="size-5 fill-current"
                aria-hidden="true"
            />
        </div>
        <div
            class="min-w-0 text-left text-sm leading-tight group-data-[collapsible=icon]:sr-only"
        >
            <span v-if="isCustomerBrand" class="block font-semibold"
                >Sheffield<br />Community Venues</span
            >
            <span v-else class="font-semibold">Facility4Hire</span>
        </div>
    </div>
</template>
