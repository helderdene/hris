<script setup lang="ts">
const props = defineProps<{
    status: string;
    label: string;
    color: string;
}>();

function getBadgeClasses(color: string): string {
    const colorMap: Record<string, string> = {
        slate: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-300',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
        green: 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        red: 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
        rose: 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-300',
    };
    return colorMap[color] ?? colorMap.slate;
}
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="getBadgeClasses(color)"
    >
        {{ label }}
    </span>
</template>
