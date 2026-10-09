<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    Bell,
    Building2,
    CalendarDays,
    CreditCard,
    LayoutGrid,
    Search,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Button } from '@/components/ui/button';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { dashboard, login } from '@/routes';
import { index as availabilityIndex } from '@/routes/availability';
import { index as bookingIndex } from '@/routes/bookings';
import { index as invoiceIndex } from '@/routes/invoices';
import { index as notificationIndex } from '@/routes/notifications';
import { index as organisationIndex } from '@/routes/organisations';
import type { NavItem } from '@/types';

const auth = computed(() => usePage().props.auth);
const { setOpenMobile } = useSidebar();
const brandDestination = computed(() =>
    auth.value.canUseCustomerArea
        ? dashboard()
        : !auth.value.user
          ? availabilityIndex()
          : auth.value.workspaceUrl,
);
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
                  title: 'My bookings',
                  href: bookingIndex(),
                  icon: CalendarDays,
              },
              {
                  title: 'Invoices & payments',
                  href: invoiceIndex(),
                  icon: CreditCard,
              },
              {
                  title: 'Organisations',
                  href: organisationIndex(),
                  icon: Building2,
              },
              {
                  title: 'Notifications',
                  href: notificationIndex(),
                  icon: Bell,
              },
          ]
        : !auth.value.user
          ? [
                {
                    title: 'Find a facility',
                    href: availabilityIndex(),
                    icon: Search,
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
                    <SidebarMenuButton
                        size="lg"
                        class="h-16 pr-12 md:pr-2"
                        as-child
                    >
                        <component
                            :is="
                                isStaffWorkspace && !auth.canUseCustomerArea
                                    ? 'a'
                                    : Link
                            "
                            :href="brandDestination"
                            @click="setOpenMobile(false)"
                        >
                            <AppLogo />
                        </component>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <nav
                v-if="isStaffWorkspace"
                aria-label="Staff workspace"
                class="px-2"
            >
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton class="h-11" as-child>
                            <a
                                :href="auth.workspaceUrl"
                                @click="setOpenMobile(false)"
                            >
                                <LayoutGrid aria-hidden="true" />
                                <span>{{ workspaceLabel }}</span>
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </nav>
            <NavMain v-if="mainNavItems.length" :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-sidebar-border/50">
            <NavUser v-if="auth.user" />
            <Button
                v-else
                as-child
                class="group-data-[collapsible=icon]:hidden"
            >
                <Link :href="login()" @click="setOpenMobile(false)"
                    >Sign in</Link
                >
            </Button>
            <p
                class="px-2 py-1 text-xs text-sidebar-foreground/80 group-data-[collapsible=icon]:hidden"
            >
                Powered by Facility4Hire
            </p>
        </SidebarFooter>
    </Sidebar>
</template>
