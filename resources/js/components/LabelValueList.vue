<script setup lang="ts">
interface LabelValueItem {
    label: string;
    value: string | number | null | undefined;
}

interface Props {
    items: LabelValueItem[];
}

defineProps<Props>();

function formatValue(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
        return '\u2014'; // Em-dash
    }
    return String(value);
}
</script>

<template>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        <div
            v-for="(item, index) in items"
            :key="index"
            class="flex flex-col gap-0.5 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-baseline sm:justify-between"
        >
            <span class="text-sm text-slate-500 dark:text-slate-400">
                {{ item.label }}
            </span>
            <span
                class="text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                {{ formatValue(item.value) }}
            </span>
        </div>
    </div>
</template>
