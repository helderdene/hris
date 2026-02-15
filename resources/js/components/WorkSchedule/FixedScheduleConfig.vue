<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface BreakConfig {
    start_time: string | null;
    duration_minutes: number;
}

interface TimeConfiguration {
    work_days: string[];
    half_day_saturday: boolean;
    start_time: string;
    end_time: string;
    saturday_end_time: string | null;
    break?: BreakConfig | null;
}

const model = defineModel<TimeConfiguration>({ required: true });

const days = [
    { value: 'monday', label: 'Mon' },
    { value: 'tuesday', label: 'Tue' },
    { value: 'wednesday', label: 'Wed' },
    { value: 'thursday', label: 'Thu' },
    { value: 'friday', label: 'Fri' },
    { value: 'saturday', label: 'Sat' },
    { value: 'sunday', label: 'Sun' },
];

const workDays = computed({
    get: () => model.value?.work_days || [],
    set: (value) => {
        if (model.value) {
            model.value = { ...model.value, work_days: value };
        }
    },
});

const halfDaySaturday = computed({
    get: () => model.value?.half_day_saturday || false,
    set: (value) => {
        if (model.value) {
            model.value = {
                ...model.value,
                half_day_saturday: value,
                saturday_end_time: value ? '12:00' : null,
            };
        }
    },
});

const includeBreak = computed({
    get: () => model.value?.break != null,
    set: (value) => {
        if (model.value) {
            model.value = {
                ...model.value,
                break: value
                    ? { start_time: '12:00', duration_minutes: 60 }
                    : null,
            };
        }
    },
});

function toggleDay(day: string) {
    const currentDays = [...workDays.value];
    const index = currentDays.indexOf(day);
    if (index > -1) {
        currentDays.splice(index, 1);
    } else {
        currentDays.push(day);
    }
    workDays.value = currentDays;
}

function isDaySelected(day: string): boolean {
    return workDays.value.includes(day);
}

function updateField(field: keyof TimeConfiguration, value: any) {
    if (model.value) {
        model.value = { ...model.value, [field]: value };
    }
}

function updateBreakField(field: keyof BreakConfig, value: any) {
    if (model.value && model.value.break) {
        model.value = {
            ...model.value,
            break: { ...model.value.break, [field]: value },
        };
    }
}
</script>

<template>
    <div class="space-y-6">
        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <h4
                class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Work Days
            </h4>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="day in days"
                    :key="day.value"
                    type="button"
                    @click="toggleDay(day.value)"
                    class="flex h-10 w-12 items-center justify-center rounded-md border text-sm font-medium transition-colors"
                    :class="
                        isDaySelected(day.value)
                            ? 'border-blue-500 bg-blue-50 text-blue-700 dark:border-blue-400 dark:bg-blue-900/30 dark:text-blue-300'
                            : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'
                    "
                >
                    {{ day.label }}
                </button>
            </div>

            <!-- Half-day Saturday Toggle -->
            <div
                v-if="isDaySelected('saturday')"
                class="mt-4 flex items-center gap-3"
            >
                <input
                    id="half_day_saturday"
                    type="checkbox"
                    :checked="halfDaySaturday"
                    @change="halfDaySaturday = ($event.target as HTMLInputElement).checked"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                />
                <Label for="half_day_saturday" class="cursor-pointer text-sm">
                    Half-day Saturday
                </Label>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Start Time -->
            <div class="space-y-2">
                <Label for="start_time">Start Time</Label>
                <Input
                    id="start_time"
                    type="time"
                    :model-value="model?.start_time || '08:00'"
                    @update:model-value="updateField('start_time', $event)"
                />
            </div>

            <!-- End Time -->
            <div class="space-y-2">
                <Label for="end_time">End Time</Label>
                <Input
                    id="end_time"
                    type="time"
                    :model-value="model?.end_time || '17:00'"
                    @update:model-value="updateField('end_time', $event)"
                />
            </div>
        </div>

        <!-- Saturday End Time (conditional) -->
        <div v-if="halfDaySaturday" class="space-y-2">
            <Label for="saturday_end_time">Saturday End Time</Label>
            <Input
                id="saturday_end_time"
                type="time"
                :model-value="model?.saturday_end_time || '12:00'"
                @update:model-value="updateField('saturday_end_time', $event)"
            />
            <p class="text-xs text-slate-500 dark:text-slate-400">
                End time specifically for half-day Saturdays
            </p>
        </div>

        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <div class="mb-4 flex items-center gap-3">
                <input
                    id="include_break"
                    type="checkbox"
                    :checked="includeBreak"
                    @change="includeBreak = ($event.target as HTMLInputElement).checked"
                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                />
                <Label
                    for="include_break"
                    class="cursor-pointer text-sm font-medium text-slate-900 dark:text-slate-100"
                >
                    Include Break
                </Label>
            </div>
            <div v-if="includeBreak" class="grid gap-4 sm:grid-cols-2">
                <!-- Break Start Time -->
                <div class="space-y-2">
                    <Label for="break_start_time">Break Start Time</Label>
                    <Input
                        id="break_start_time"
                        type="time"
                        :model-value="model?.break?.start_time || '12:00'"
                        @update:model-value="
                            updateBreakField('start_time', $event)
                        "
                    />
                </div>

                <!-- Break Duration -->
                <div class="space-y-2">
                    <Label for="break_duration">Break Duration (minutes)</Label>
                    <Input
                        id="break_duration"
                        type="number"
                        min="1"
                        max="120"
                        :model-value="model?.break?.duration_minutes || 60"
                        @update:model-value="
                            updateBreakField(
                                'duration_minutes',
                                parseInt($event as string) || 60,
                            )
                        "
                    />
                </div>
            </div>
            <p
                v-if="!includeBreak"
                class="text-xs text-slate-500 dark:text-slate-400"
            >
                No break period will be deducted from working hours.
            </p>
        </div>
    </div>
</template>
