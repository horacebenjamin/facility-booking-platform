<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show, store } from '@/routes/organisations';
import type { OrganisationSummary } from '@/types/organisation';

defineProps<{ organisations: OrganisationSummary[] }>();

const page = usePage<{ flash?: { success?: string } }>();
</script>

<template>
    <div class="w-full flex-1 space-y-6 p-4 md:p-6">
        <Head title="Organisations" />

        <div
            v-if="page.props.flash?.success"
            class="mx-auto max-w-3xl rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-900 dark:bg-green-950/30 dark:text-green-100"
            role="status"
        >
            {{ page.props.flash.success }}
        </div>

        <Card class="mx-auto max-w-3xl">
            <CardHeader>
                <CardTitle>
                    <h1 class="text-2xl font-semibold">Organisations</h1>
                </CardTitle>
                <CardDescription>
                    Clubs, businesses and groups you book or manage finances
                    for.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <p
                    v-if="organisations.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    You are not a member of any organisation yet.
                </p>
                <ul v-else class="space-y-3">
                    <li
                        v-for="organisation in organisations"
                        :key="organisation.id"
                        class="flex flex-wrap items-center justify-between gap-3 border-b pb-3 last:border-b-0"
                    >
                        <Link
                            :href="show(organisation.id)"
                            class="font-medium underline-offset-4 hover:underline"
                        >
                            {{ organisation.name }}
                        </Link>
                        <Badge variant="secondary">{{
                            organisation.role_label
                        }}</Badge>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card class="mx-auto max-w-3xl">
            <CardHeader>
                <CardTitle>Create an organisation</CardTitle>
                <CardDescription>
                    You will become its owner and can then add other members who
                    already have an account.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="store.form()"
                    reset-on-success
                    class="space-y-4"
                    #default="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label for="organisation-name">Organisation name</Label>
                        <Input
                            id="organisation-name"
                            name="name"
                            required
                            maxlength="255"
                            autocomplete="organization"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <Button type="submit" :disabled="processing">
                        Create organisation
                    </Button>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
