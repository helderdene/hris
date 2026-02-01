<script setup lang="ts">
const props = defineProps<{
    percentage: number;
    approvedCount?: number;
    totalCount?: number;
}>();
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-between text-sm">
            <span class="font-medium text-slate-700 dark:text-slate-300">
                Progress
            </span>
            <span class="text-slate-500 dark:text-slate-400">
                <template v-if="approvedCount !== undefined && totalCount !== undefined">
                    {{ approvedCount }}/{{ totalCount }} items
                </template>
                <template v-else>
                    {{ percentage }}%
                </template>
            </span>
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
            <div
                class="h-full rounded-full transition-all duration-500"
                :class="percentage === 100 ? 'bg-green-500' : 'bg-blue-500'"
                :style="{ width: `${percentage}%` }"
            />
        </div>
    </div>
</template>
