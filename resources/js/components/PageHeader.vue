<script setup lang="ts">
import Heading from '@/components/Heading.vue';

defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div
        class="flex min-w-0 flex-col gap-4 rounded-xl border bg-card p-4 text-card-foreground shadow-xs sm:flex-row sm:items-start sm:justify-between md:px-6"
    >
        <div class="min-w-0 space-y-3">
            <div v-if="$slots.back"><slot name="back" /></div>
            <Heading
                :title="title"
                :description="description"
                :level="1"
                class="mb-0 space-y-2"
            />
        </div>
        <div
            v-if="$slots.actions"
            class="flex shrink-0 flex-wrap items-center gap-3"
        >
            <slot name="actions" />
        </div>
    </div>
</template>
