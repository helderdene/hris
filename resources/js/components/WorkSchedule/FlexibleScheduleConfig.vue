<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface BreakConfig {
    start_time: string | null;
    duration_minutes: number;
}

interface CoreHours {
    start_time: string;
    end_time: string;
}

interface FlexibleStartWindow {
    earliest: string;
    latest: string;
}

interface TimeConfiguration {
    required_hours_per_day: number;
    required_hours_per_week: number;
    core_hours: CoreHours;
    flexible_start_window: FlexibleStartWindow;
    break: BreakConfig;
}

const model = defineModel<TimeConfiguration>({ required: true });

function updateField(field: keyof TimeConfiguration, value: any) {
    if (model.value) {
        model.value = { ...model.value, [field]: value };
    }
}

function updateCoreHoursField(field: keyof CoreHours, value: string) {
    if (model.value) {
        model.value = {
            ...model.value,
            core_hours: { ...model.value.core_hours, [field]: value },
        };
    }
}

function updateFlexibleWindowField(
    field: keyof FlexibleStartWindow,
    value: string,
) {
    if (model.value) {
        model.value = {
            ...model.value,
            flexible_start_window: {
                ...model.value.flexible_start_window,
                [field]: value,
            },
        };
    }
}

function updateBreakField(field: keyof BreakConfig, value: any) {
    if (model.value) {
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
                Required Hours
            </h4>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Required Hours Per Day -->
                <div class="space-y-2">
                    <Label for="required_hours_per_day">Hours Per Day</Label>
                    <Input
                        id="required_hours_per_day"
                        type="number"
                        min="1"
                        max="24"
                        :model-value="model?.required_hours_per_day || 8"
                        @update:model-value="
                            updateField(
                                'required_hours_per_day',
                                parseInt($event as string) || 8,
                            )
                        "
                    />
                </div>

                <!-- Required Hours Per Week -->
                <div class="space-y-2">
                    <Label for="required_hours_per_week">Hours Per Week</Label>
                    <Input
                        id="required_hours_per_week"
                        type="number"
                        min="1"
                        max="168"
                        :model-value="model?.required_hours_per_week || 40"
                        @update:model-value="
                            updateField(
                                'required_hours_per_week',
                                parseInt($event as string) || 40,
                            )
                        "
                    />
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <h4
                class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Core Hours
            </h4>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                Time window when employees must be present
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Core Hours Start Time -->
                <div class="space-y-2">
                    <Label for="core_start_time">Start Time</Label>
                    <Input
                        id="core_start_time"
                        type="time"
                        :model-value="model?.core_hours?.start_time || '10:00'"
                        @update:model-value="
                            updateCoreHoursField('start_time', $event as string)
                        "
                    />
                </div>

                <!-- Core Hours End Time -->
                <div class="space-y-2">
                    <Label for="core_end_time">End Time</Label>
                    <Input
                        id="core_end_time"
                        type="time"
                        :model-value="model?.core_hours?.end_time || '15:00'"
                        @update:model-value="
                            updateCoreHoursField('end_time', $event as string)
                        "
                    />
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <h4
                class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Flexible Start Window
            </h4>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                Allowed time range for employees to start their workday
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Earliest Start Time -->
                <div class="space-y-2">
                    <Label for="earliest_start">Earliest Start</Label>
                    <Input
                        id="earliest_start"
                        type="time"
                        :model-value="
                            model?.flexible_start_window?.earliest || '06:00'
                        "
                        @update:model-value="
                            updateFlexibleWindowField(
                                'earliest',
                                $event as string,
                            )
                        "
                    />
                </div>

                <!-- Latest Start Time -->
                <div class="space-y-2">
                    <Label for="latest_start">Latest Start</Label>
                    <Input
                        id="latest_start"
                        type="time"
                        :model-value="
                            model?.flexible_start_window?.latest || '10:00'
                        "
                        @update:model-value="
                            updateFlexibleWindowField(
                                'latest',
                                $event as string,
                            )
                        "
                    />
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <h4
                class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Break Period
            </h4>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Break Start Time (optional for flexible) -->
                <div class="space-y-2">
                    <Label for="break_start_time"
                        >Break Start Time (optional)</Label
                    >
                    <Input
                        id="break_start_time"
                        type="time"
                        :model-value="model?.break?.start_time || ''"
                        @update:model-value="
                            updateBreakField('start_time', $event || null)
                        "
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Leave empty for flexible break time
                    </p>
                </div>

                <!-- Break Duration -->
                <div class="space-y-2">
                    <Label for="break_duration">Break Duration (minutes)</Label>
                    <Input
                        id="break_duration"
                        type="number"
                        min="0"
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
        </div>
    </div>
</template>
