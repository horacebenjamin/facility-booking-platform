<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
    level?: 1 | 2 | 3;
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
    level: 2,
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-0.5'">
        <component
            :is="`h${level}`"
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base font-medium'
                    : 'text-2xl font-semibold tracking-tight sm:text-3xl'
            "
        >
            {{ title }}
        </component>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
