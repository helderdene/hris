<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface OvertimeRules {
    daily_threshold_hours: number;
    weekly_threshold_hours: number;
    regular_multiplier: number;
    rest_day_multiplier: number;
    holiday_multiplier: number;
}

const model = defineModel<OvertimeRules>({ required: true });

function updateField(field: keyof OvertimeRules, value: string) {
    if (model.value) {
        model.value = {
            ...model.value,
            [field]: parseFloat(value) || 0,
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
                Overtime Thresholds
            </h4>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                Define when overtime pay begins
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Daily Overtime Threshold -->
                <div class="space-y-2">
                    <Label for="daily_threshold_hours"
                        >Daily Threshold (hours)</Label
                    >
                    <Input
                        id="daily_threshold_hours"
                        type="number"
                        min="0"
                        max="24"
                        step="0.5"
                        :model-value="model?.daily_threshold_hours || 8"
                        @update:model-value="
                            updateField(
                                'daily_threshold_hours',
                                $event as string,
                            )
                        "
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Hours worked beyond this count as daily OT
                    </p>
                </div>

                <!-- Weekly Overtime Threshold -->
                <div class="space-y-2">
                    <Label for="weekly_threshold_hours"
                        >Weekly Threshold (hours)</Label
                    >
                    <Input
                        id="weekly_threshold_hours"
                        type="number"
                        min="0"
                        max="168"
                        step="1"
                        :model-value="model?.weekly_threshold_hours || 40"
                        @update:model-value="
                            updateField(
                                'weekly_threshold_hours',
                                $event as string,
                            )
                        "
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Hours worked beyond this count as weekly OT
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <h4
                class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Overtime Multipliers
            </h4>
            <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                Rate multipliers applied to base pay for overtime hours
            </p>
            <div class="grid gap-4 sm:grid-cols-3">
                <!-- Regular OT Multiplier -->
                <div class="space-y-2">
                    <Label for="regular_multiplier">Regular OT</Label>
                    <div class="relative">
                        <Input
                            id="regular_multiplier"
                            type="number"
                            min="1"
                            max="5"
                            step="0.05"
                            :model-value="model?.regular_multiplier || 1.25"
                            @update:model-value="
                                updateField(
                                    'regular_multiplier',
                                    $event as string,
                                )
                            "
                            class="pr-8"
                        />
                        <span
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-slate-500 dark:text-slate-400"
                            >x</span
                        >
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        e.g., 1.25 = 125% pay
                    </p>
                </div>

                <!-- Rest Day OT Multiplier -->
                <div class="space-y-2">
                    <Label for="rest_day_multiplier">Rest Day OT</Label>
                    <div class="relative">
                        <Input
                            id="rest_day_multiplier"
                            type="number"
                            min="1"
                            max="5"
                            step="0.05"
                            :model-value="model?.rest_day_multiplier || 1.3"
                            @update:model-value="
                                updateField(
                                    'rest_day_multiplier',
                                    $event as string,
                                )
                            "
                            class="pr-8"
                        />
                        <span
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-slate-500 dark:text-slate-400"
                            >x</span
                        >
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Work on scheduled rest day
                    </p>
                </div>

                <!-- Holiday OT Multiplier -->
                <div class="space-y-2">
                    <Label for="holiday_multiplier">Holiday OT</Label>
                    <div class="relative">
                        <Input
                            id="holiday_multiplier"
                            type="number"
                            min="1"
                            max="5"
                            step="0.05"
                            :model-value="model?.holiday_multiplier || 2.0"
                            @update:model-value="
                                updateField(
                                    'holiday_multiplier',
                                    $event as string,
                                )
                            "
                            class="pr-8"
                        />
                        <span
                            class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-slate-500 dark:text-slate-400"
                            >x</span
                        >
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Work on holidays
                    </p>
                </div>
            </div>
        </div>

        <!-- Multiplier Summary -->
        <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800/50">
            <h4
                class="mb-3 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                Pay Rate Summary
            </h4>
            <div class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-400"
                        >Regular overtime:</span
                    >
                    <span
                        class="font-medium text-slate-900 dark:text-slate-100"
                    >
                        {{
                            ((model?.regular_multiplier || 1.25) * 100).toFixed(
                                0,
                            )
                        }}% of base pay
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-400"
                        >Rest day work:</span
                    >
                    <span
                        class="font-medium text-slate-900 dark:text-slate-100"
                    >
                        {{
                            ((model?.rest_day_multiplier || 1.3) * 100).toFixed(
                                0,
                            )
                        }}% of base pay
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-600 dark:text-slate-400"
                        >Holiday work:</span
                    >
                    <span
                        class="font-medium text-slate-900 dark:text-slate-100"
                    >
                        {{
                            ((model?.holiday_multiplier || 2.0) * 100).toFixed(
                                0,
                            )
                        }}% of base pay
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
