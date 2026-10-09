<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

withDefaults(
    defineProps<{
        label: string;
        tone?: 'success' | 'warning' | 'danger' | 'information' | 'neutral';
    }>(),
    { tone: 'neutral' },
);

const tones = {
    success: 'border-success bg-success text-success-foreground',
    warning: 'border-warning bg-warning text-warning-foreground',
    danger: 'border-destructive bg-destructive text-destructive-foreground',
    information: 'border-info bg-info text-info-foreground',
    neutral: 'border-border bg-muted text-muted-foreground',
};
</script>

<template>
    <Badge
        variant="outline"
        class="max-w-full overflow-visible text-left leading-5 [overflow-wrap:anywhere] whitespace-normal"
        :class="tones[tone]"
    >
        {{ label }}
    </Badge>
</template>
