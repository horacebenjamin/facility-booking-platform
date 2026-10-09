<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Building2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
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

        <div class="mx-auto w-full max-w-5xl min-w-0 space-y-6">
            <div
                v-if="page.props.flash?.success"
                class="rounded-md border border-green-600/30 bg-green-50 p-3 text-sm text-green-900 dark:bg-green-950/30 dark:text-green-100"
                role="status"
            >
                {{ page.props.flash.success }}
            </div>

            <PageHeader
                title="Organisations"
                description="Clubs, businesses and groups you book or manage finances for."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Create an organisation</CardTitle>
                    <CardDescription>
                        You will become its owner and can then add other members
                        who already have an account.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="store.form()"
                        reset-on-success
                        class="grid gap-2"
                        #default="{ errors, processing }"
                    >
                        <Label for="organisation-name">Organisation name</Label>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <Input
                                id="organisation-name"
                                name="name"
                                required
                                maxlength="255"
                                autocomplete="organization"
                                class="min-w-0 flex-1"
                            />
                            <Button
                                type="submit"
                                class="h-11 sm:h-9"
                                :disabled="processing"
                            >
                                Create organisation
                            </Button>
                        </div>
                        <InputError :message="errors.name" />
                    </Form>
                </CardContent>
            </Card>

            <p
                v-if="organisations.length === 0"
                class="rounded-xl border bg-card p-6 text-sm text-muted-foreground"
            >
                You are not a member of any organisation yet.
            </p>
            <Card v-else class="gap-0 py-0">
                <CardContent class="p-0">
                    <ul class="divide-y">
                        <li
                            v-for="organisation in organisations"
                            :key="organisation.id"
                            class="flex min-w-0 flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 md:px-6"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                                    aria-hidden="true"
                                >
                                    <Building2 class="size-5" />
                                </span>
                                <div
                                    class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1"
                                >
                                    <h2
                                        class="text-base font-semibold [overflow-wrap:anywhere]"
                                    >
                                        {{ organisation.name }}
                                    </h2>
                                    <Badge variant="secondary">{{
                                        organisation.role_label
                                    }}</Badge>
                                </div>
                            </div>
                            <Button
                                as-child
                                variant="outline"
                                class="h-11 w-full shrink-0 sm:h-9 sm:w-auto"
                            >
                                <Link
                                    :href="show(organisation.id)"
                                    :aria-label="`View organisation ${organisation.name}`"
                                    >View organisation</Link
                                >
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
