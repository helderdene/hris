<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface KpiAssignment {
    id: number;
    kpi: {
        id: number;
        name: string;
        description: string;
        unit: string;
    };
    target_value: number;
    actual_value: number | null;
    weight: number;
    achievement_percentage: number | null;
}

interface KpiRating {
    rating: number | null;
    comments: string;
}

const props = defineProps<{
    kpiAssignments: KpiAssignment[];
    modelValue: Record<number, KpiRating>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Record<number, KpiRating>): void;
    (e: 'change'): void;
}>();

function setRating(kpiId: number, rating: number) {
    if (props.disabled) return;
    const updated = { ...props.modelValue };
    updated[kpiId] = { ...updated[kpiId], rating };
    emit('update:modelValue', updated);
    emit('change');
}

function updateComments(kpiId: number, comments: string) {
    if (props.disabled) return;
    const updated = { ...props.modelValue };
    updated[kpiId] = { ...updated[kpiId], comments };
    emit('update:modelValue', updated);
    emit('change');
}

function getAchievementColor(percentage: number | null): string {
    if (percentage === null) return 'text-slate-500';
    if (percentage >= 100) return 'text-emerald-600';
    if (percentage >= 80) return 'text-blue-600';
    if (percentage >= 60) return 'text-amber-600';
    return 'text-red-600';
}

function getAchievementBarColor(percentage: number | null): string {
    if (percentage === null) return 'bg-slate-200';
    if (percentage >= 100) return 'bg-emerald-500';
    if (percentage >= 80) return 'bg-blue-500';
    if (percentage >= 60) return 'bg-amber-500';
    return 'bg-red-500';
}

const ratingLabels = ['', 'Needs Improvement', 'Below Expectations', 'Meets Expectations', 'Exceeds Expectations', 'Exceptional'];
</script>

<template>
    <div class="space-y-6">
        <div
            v-for="kpi in kpiAssignments"
            :key="kpi.id"
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <div class="mb-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h4 class="font-medium text-slate-900 dark:text-slate-100">
                            {{ kpi.kpi.name }}
                        </h4>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ kpi.kpi.description }}
                        </p>
                    </div>
                    <span class="ml-2 rounded bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        Weight: {{ kpi.weight }}%
                    </span>
                </div>
            </div>

            <!-- KPI Progress -->
            <div class="mb-4 rounded-lg bg-slate-50 p-3 dark:bg-slate-800/50">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Target</span>
                    <span class="font-medium text-slate-900 dark:text-slate-100">
                        {{ kpi.target_value }} {{ kpi.kpi.unit }}
                    </span>
                </div>
                <div class="mt-2 flex items-center justify-between text-sm">
                    <span class="text-slate-500 dark:text-slate-400">Actual</span>
                    <span class="font-medium text-slate-900 dark:text-slate-100">
                        {{ kpi.actual_value !== null ? `${kpi.actual_value} ${kpi.kpi.unit}` : 'Not recorded' }}
                    </span>
                </div>
                <div class="mt-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-500 dark:text-slate-400">Achievement</span>
                        <span class="font-semibold" :class="getAchievementColor(kpi.achievement_percentage)">
                            {{ kpi.achievement_percentage !== null ? `${kpi.achievement_percentage.toFixed(0)}%` : '-' }}
                        </span>
                    </div>
                    <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            class="h-full rounded-full transition-all"
                            :class="getAchievementBarColor(kpi.achievement_percentage)"
                            :style="{ width: `${Math.min(kpi.achievement_percentage || 0, 100)}%` }"
                        />
                    </div>
                </div>
            </div>

            <!-- Rating -->
            <div class="mb-3">
                <Label class="mb-2 block text-sm">Your Rating</Label>
                <div class="flex gap-2">
                    <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-lg border-2 text-sm font-medium transition-colors"
                        :class="modelValue[kpi.id]?.rating === n
                            ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                            : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'"
                        :disabled="disabled"
                        @click="setRating(kpi.id, n)"
                    >
                        {{ n }}
                    </button>
                </div>
                <p v-if="modelValue[kpi.id]?.rating" class="mt-1 text-xs text-slate-500">
                    {{ ratingLabels[modelValue[kpi.id].rating!] }}
                </p>
            </div>

            <!-- Comments -->
            <div>
                <Label class="mb-2 block text-sm">Comments (Optional)</Label>
                <Textarea
                    :model-value="modelValue[kpi.id]?.comments || ''"
                    placeholder="Add any comments or context..."
                    rows="2"
                    :disabled="disabled"
                    @update:model-value="updateComments(kpi.id, $event)"
                />
            </div>
        </div>

        <div v-if="kpiAssignments.length === 0" class="py-8 text-center text-sm text-slate-500">
            No KPIs assigned for this evaluation period.
        </div>
    </div>
</template>
