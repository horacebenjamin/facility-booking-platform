<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, read } from '@/routes/notifications';
import type { NotificationPage } from '@/types/notification';

defineProps<{ notifications: NotificationPage; unread_count: number }>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Notifications', href: index() }] },
});

function timestamp(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
</script>

<template>
    <div class="w-full flex-1 p-4 md:p-6">
        <Head title="Notifications" />
        <Card class="mx-auto max-w-3xl">
            <CardContent class="space-y-6 py-6">
                <div class="space-y-2">
                    <h1 class="text-2xl font-semibold">Notifications</h1>
                    <p class="text-sm text-muted-foreground">
                        Booking, payment and invoice updates ·
                        {{ unread_count }} unread
                    </p>
                </div>
                <p v-if="notifications.data.length === 0">
                    You have no notifications yet.
                </p>
                <ul v-else class="space-y-6">
                    <li
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        class="space-y-3 border-b pb-6"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">
                                {{ notification.title }}
                            </h2>
                            <span
                                v-if="!notification.read_at"
                                class="rounded bg-primary/10 px-2 py-1 text-xs font-medium"
                                >Unread</span
                            >
                            <span v-else class="text-xs text-muted-foreground"
                                >Read</span
                            >
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
                        <div class="flex flex-wrap items-center gap-4">
                            <Link
                                v-if="notification.action_url"
                                :href="notification.action_url"
                                class="text-sm underline"
                                >{{
                                    notification.action_label ?? 'View details'
                                }}</Link
                            >
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
