<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    direction: 'in' | 'out' | string | null;
}

const props = defineProps<Props>();

const badgeClasses = computed(() => {
    const normalizedDirection = props.direction?.toLowerCase();

    if (normalizedDirection === 'in') {
        return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
    }

    if (normalizedDirection === 'out') {
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    }

    return 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400';
});

const displayText = computed(() => {
    const normalizedDirection = props.direction?.toLowerCase();

    if (normalizedDirection === 'in') {
        return 'IN';
    }

    if (normalizedDirection === 'out') {
        return 'OUT';
    }

    return props.direction?.toUpperCase() || '-';
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
            badgeClasses,
        ]"
    >
        {{ displayText }}
    </span>
</template>
