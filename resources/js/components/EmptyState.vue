<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';

defineProps<{ title: string; description: string }>();
</script>

<template>
    <Card>
        <CardContent class="flex flex-col items-start gap-3 py-8 sm:p-8">
            <h2 class="text-lg font-semibold">{{ title }}</h2>
            <p class="max-w-xl text-sm leading-6 text-muted-foreground">
                {{ description }}
            </p>
            <div v-if="$slots.actions" class="mt-2 flex flex-wrap gap-3">
                <slot name="actions" />
            </div>
        </CardContent>
    </Card>
</template>
