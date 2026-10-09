<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { currentUrl } = useCurrentUrl();
const { setOpenMobile } = useSidebar();
function isActive(item: NavItem): boolean {
    const path = toUrl(item.href);
    return currentUrl.value === path || currentUrl.value.startsWith(`${path}/`);
}
</script>

<template>
    <nav aria-label="Booking navigation">
        <SidebarGroup class="px-2 pt-2 pb-0">
            <SidebarMenu>
                <SidebarMenuItem v-for="item in items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        class="h-11 data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground motion-reduce:transition-none"
                        :is-active="isActive(item)"
                        :tooltip="item.title"
                    >
                        <Link
                            :href="item.href"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            @click="setOpenMobile(false)"
                        >
                            <component :is="item.icon" aria-hidden="true" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </nav>
</template>
