<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { formatBookingDateTime } from '@/lib/bookingDateTime';
import { index, read } from '@/routes/notifications';
import type { NotificationPage } from '@/types/notification';

defineProps<{ notifications: NotificationPage; unread_count: number }>();
const page = usePage<{ bookingTimezone: string }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: index() }] },
});

function timestamp(value: string): string {
    return formatBookingDateTime(value, page.props.bookingTimezone);
}
</script>

<template>
    <div class="mx-auto w-full max-w-4xl min-w-0 flex-1 space-y-6 p-4 md:p-6">
        <Head title="Notifications" />
        <PageHeader
            title="Notifications"
            :description="`Booking, payment and invoice updates · ${unread_count} unread`"
        />
        <EmptyState
            v-if="notifications.data.length === 0"
            title="You're up to date"
            description="You have no notifications yet."
        />
        <Card v-else>
            <CardContent class="space-y-6 py-6">
                <ul class="space-y-6">
                    <li
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        class="space-y-3 border-b pb-6"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <h2
                                class="min-w-0 font-semibold [overflow-wrap:anywhere]"
                            >
                                {{ notification.title }}
                            </h2>
                            <StatusBadge
                                :label="
                                    notification.read_at ? 'Read' : 'Unread'
                                "
                                :tone="
                                    notification.read_at
                                        ? 'neutral'
                                        : 'information'
                                "
                                class="shrink-0"
                            />
                        </div>
                        <p class="text-sm leading-6 whitespace-pre-line">
                            {{ notification.body }}
                        </p>
                        <time
                            v-if="notification.occurred_at"
                            :datetime="notification.occurred_at"
                            class="block text-xs text-muted-foreground"
                            >{{ timestamp(notification.occurred_at) }}</time
                        >
                        <div
                            class="flex flex-wrap items-center justify-end gap-3"
                        >
                            <Button
                                v-if="notification.action_url"
                                as-child
                                variant="outline"
                                size="sm"
                                class="h-11 sm:h-8"
                            >
                                <Link :href="notification.action_url">{{
                                    notification.action_label ?? 'View details'
                                }}</Link>
                            </Button>
                            <Form
                                v-if="!notification.read_at"
                                v-bind="read.form(notification.id)"
                                #default="{ processing }"
                                :options="{ preserveScroll: true }"
                            >
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    class="h-11 sm:h-8"
                                    :disabled="processing"
                                    :aria-label="`Mark ${notification.title} as read`"
                                    >Mark as read</Button
                                >
                            </Form>
                        </div>
                    </li>
                </ul>
                <nav
                    v-if="notifications.last_page > 1"
                    aria-label="Notification pages"
                    class="flex flex-wrap items-center justify-between gap-4 text-sm"
                >
                    <Link
                        v-if="notifications.prev_page_url"
                        :href="notifications.prev_page_url"
                        class="underline"
                        >Previous</Link
                    >
                    <span
                        >Page {{ notifications.current_page }} of
                        {{ notifications.last_page }}</span
                    >
                    <Link
                        v-if="notifications.next_page_url"
                        :href="notifications.next_page_url"
                        class="underline"
                        >Next</Link
                    >
                </nav>
            </CardContent>
        </Card>
    </div>
</template>
