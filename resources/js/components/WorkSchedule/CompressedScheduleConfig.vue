<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface HalfDay {
    enabled: boolean;
    day: string | null;
    hours: number | null;
}

interface TimeConfiguration {
    pattern: string;
    work_days: string[];
    daily_hours: number;
    half_day: HalfDay;
}

interface EnumOption {
    value: string;
    label: string;
}

const model = defineModel<TimeConfiguration>({ required: true });

const patternOptions: EnumOption[] = [
    { value: '4x10', label: '4x10 - Four 10-hour days' },
    { value: '4.5-day', label: '4.5-day - Four full days + half day' },
];

const dayOptions: EnumOption[] = [
    { value: 'monday', label: 'Monday' },
    { value: 'tuesday', label: 'Tuesday' },
    { value: 'wednesday', label: 'Wednesday' },
    { value: 'thursday', label: 'Thursday' },
    { value: 'friday', label: 'Friday' },
    { value: 'saturday', label: 'Saturday' },
];

const days = [
    { value: 'monday', label: 'Mon' },
    { value: 'tuesday', label: 'Tue' },
    { value: 'wednesday', label: 'Wed' },
    { value: 'thursday', label: 'Thu' },
    { value: 'friday', label: 'Fri' },
    { value: 'saturday', label: 'Sat' },
];

const workDays = computed({
    get: () => model.value?.work_days || [],
    set: (value) => {
        if (model.value) {
            model.value = { ...model.value, work_days: value };
        }
    },
});

const pattern = computed({
    get: () => model.value?.pattern || '4x10',
    set: (value) => {
        if (model.value) {
            const dailyHours = value === '4x10' ? 10 : 9;
            const halfDayEnabled = value === '4.5-day';
            model.value = {
                ...model.value,
                pattern: value,
                daily_hours: dailyHours,
                half_day: {
                    enabled: halfDayEnabled,
                    day: halfDayEnabled ? 'friday' : null,
                    hours: halfDayEnabled ? 4 : null,
                },
            };
        }
    },
});

const halfDayEnabled = computed({
    get: () => model.value?.half_day?.enabled || false,
    set: (value) => {
        if (model.value) {
            model.value = {
                ...model.value,
                half_day: {
                    ...model.value.half_day,
                    enabled: value,
                    day: value ? 'friday' : null,
                    hours: value ? 4 : null,
                },
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

function updateHalfDayField(field: keyof HalfDay, value: any) {
    if (model.value) {
        model.value = {
            ...model.value,
            half_day: { ...model.value.half_day, [field]: value },
        };
    }
}

// Calculate total weekly hours
const totalWeeklyHours = computed(() => {
    const days = workDays.value.length;
    const dailyHours = model.value?.daily_hours || 10;
    const halfDayHours = model.value?.half_day?.enabled
        ? model.value.half_day.hours || 0
        : 0;

    if (model.value?.half_day?.enabled) {
        return (days - 1) * dailyHours + halfDayHours;
    }
    return days * dailyHours;
});
</script>

<template>
    <div class="space-y-6">
        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <h4
                class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Pattern
            </h4>
            <div class="space-y-2">
                <EnumSelect
                    v-model="pattern"
                    :options="patternOptions"
                    placeholder="Select pattern"
                />
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{
                        pattern === '4x10'
                            ? 'Work 4 days of 10 hours each (40 hours/week)'
                            : 'Work 4 full days plus a half day (36-40 hours/week)'
                    }}
                </p>
            </div>
        </div>

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
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Daily Hours -->
            <div class="space-y-2">
                <Label for="daily_hours">Daily Hours</Label>
                <Input
                    id="daily_hours"
                    type="number"
                    min="1"
                    max="24"
                    :model-value="model?.daily_hours || 10"
                    @update:model-value="
                        updateField(
                            'daily_hours',
                            parseInt($event as string) || 10,
                        )
                    "
                />
            </div>

            <!-- Total Weekly Hours (calculated) -->
            <div class="space-y-2">
                <Label>Total Weekly Hours</Label>
                <div
                    class="flex h-9 items-center rounded-md border border-slate-200 bg-slate-50 px-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                >
                    {{ totalWeeklyHours }} hours/week
                </div>
            </div>
        </div>

        <!-- Half-day Configuration (for 4.5-day pattern) -->
        <div
            v-if="pattern === '4.5-day'"
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <div class="mb-4 flex items-center gap-3">
                <Checkbox
                    id="half_day_enabled"
                    :checked="halfDayEnabled"
                    @update:checked="halfDayEnabled = $event"
                />
                <Label
                    for="half_day_enabled"
                    class="cursor-pointer text-sm font-medium"
                >
                    Enable Half-Day
                </Label>
            </div>

            <div v-if="halfDayEnabled" class="grid gap-4 sm:grid-cols-2">
                <!-- Half Day Selection -->
                <div class="space-y-2">
                    <Label for="half_day_day">Half Day</Label>
                    <EnumSelect
                        id="half_day_day"
                        :model-value="model?.half_day?.day || 'friday'"
                        @update:model-value="updateHalfDayField('day', $event)"
                        :options="dayOptions"
                        placeholder="Select day"
                    />
                </div>

                <!-- Half Day Hours -->
                <div class="space-y-2">
                    <Label for="half_day_hours">Hours on Half Day</Label>
                    <Input
                        id="half_day_hours"
                        type="number"
                        min="1"
                        max="12"
                        :model-value="model?.half_day?.hours || 4"
                        @update:model-value="
                            updateHalfDayField(
                                'hours',
                                parseInt($event as string) || 4,
                            )
                        "
                    />
                </div>
            </div>
        </div>
    </div>
</template>
