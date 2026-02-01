<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import GoalProgressBar from '@/components/Goals/GoalProgressBar.vue';
import { computed } from 'vue';

interface KeyResult {
    id: number;
    title: string;
    description?: string;
    metric_type: string;
    metric_unit?: string;
    target_value: number;
    starting_value: number;
    current_value: number | null;
    achievement_percentage: number | null;
    weight: number;
    status: string;
    status_label?: string;
}

const props = withDefaults(
    defineProps<{
        keyResults: KeyResult[];
        showActions?: boolean;
    }>(),
    {
        showActions: false,
    },
);

const emit = defineEmits<{
    updateProgress: [keyResult: KeyResult];
    edit: [keyResult: KeyResult];
}>();

function formatValue(keyResult: KeyResult, value: number | null): string {
    if (value === null) return '-';

    switch (keyResult.metric_type) {
        case 'percentage':
            return `${value}%`;
        case 'currency':
            return `${keyResult.metric_unit || '$'}${value.toLocaleString()}`;
        case 'boolean':
            return value >= 1 ? 'Yes' : 'No';
        default:
            return keyResult.metric_unit
                ? `${value.toLocaleString()} ${keyResult.metric_unit}`
                : value.toLocaleString();
    }
}

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        pending: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        in_progress: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        completed: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    };
    return colors[status] || colors.pending;
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="keyResult in keyResults"
            :key="keyResult.id"
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <div class="mb-3 flex items-start justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-slate-900 dark:text-slate-100">
                            {{ keyResult.title }}
                        </h4>
                        <Badge :class="getStatusColor(keyResult.status)">
                            {{ keyResult.status_label || keyResult.status }}
                        </Badge>
                    </div>
                    <p v-if="keyResult.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ keyResult.description }}
                    </p>
                </div>
                <div v-if="showActions" class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="emit('updateProgress', keyResult)"
                    >
                        Update
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-4 text-sm">
                <div>
                    <div class="text-slate-500 dark:text-slate-400">Start</div>
                    <div class="font-medium text-slate-900 dark:text-slate-100">
                        {{ formatValue(keyResult, keyResult.starting_value) }}
                    </div>
                </div>
                <div>
                    <div class="text-slate-500 dark:text-slate-400">Current</div>
                    <div class="font-medium text-slate-900 dark:text-slate-100">
                        {{ formatValue(keyResult, keyResult.current_value) }}
                    </div>
                </div>
                <div>
                    <div class="text-slate-500 dark:text-slate-400">Target</div>
                    <div class="font-medium text-slate-900 dark:text-slate-100">
                        {{ formatValue(keyResult, keyResult.target_value) }}
                    </div>
                </div>
                <div>
                    <div class="text-slate-500 dark:text-slate-400">Weight</div>
                    <div class="font-medium text-slate-900 dark:text-slate-100">
                        {{ keyResult.weight }}x
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <GoalProgressBar
                    :progress="keyResult.achievement_percentage ?? 0"
                    size="sm"
                />
            </div>
        </div>
    </div>
</template>
