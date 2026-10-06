<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Bell, Building2, LayoutGrid, Search } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as availabilityIndex } from '@/routes/availability';
import { index as notificationIndex } from '@/routes/notifications';
import { index as organisationIndex } from '@/routes/organisations';
import type { NavItem } from '@/types';

const auth = computed(() => usePage().props.auth);
const isStaffWorkspace = computed(() =>
    ['management', 'operations'].includes(auth.value.workspace),
);
const workspaceLabel = computed(() =>
    auth.value.workspace === 'management' ? 'Management' : 'Operations',
);

const mainNavItems = computed<NavItem[]>(() =>
    auth.value.canUseCustomerArea
        ? [
              {
                  title: 'Notifications',
                  href: notificationIndex(),
                  icon: Bell,
              },
              {
                  title: 'Dashboard',
                  href: dashboard(),
                  icon: LayoutGrid,
              },
              {
                  title: 'Find a facility',
                  href: availabilityIndex(),
                  icon: Search,
              },
              {
                  title: 'Organisations',
                  href: organisationIndex(),
                  icon: Building2,
              },
          ]
        : [],
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <component
                            :is="isStaffWorkspace ? 'a' : Link"
                            :href="auth.workspaceUrl"
                        >
                            <AppLogo />
                        </component>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarMenu v-if="isStaffWorkspace">
                <SidebarMenuItem>
                    <SidebarMenuButton as-child>
                        <a :href="auth.workspaceUrl">
                            <LayoutGrid />
                            <span>{{ workspaceLabel }}</span>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
