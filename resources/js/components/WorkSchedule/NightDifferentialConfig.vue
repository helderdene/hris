<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface NightDifferential {
    enabled: boolean;
    start_time: string;
    end_time: string;
    rate_multiplier: number;
}

const model = defineModel<NightDifferential>({ required: true });

const isEnabled = computed({
    get: () => model.value?.enabled || false,
    set: (value) => {
        if (model.value) {
            model.value = { ...model.value, enabled: value };
        }
    },
});

function updateField(field: keyof NightDifferential, value: any) {
    if (model.value) {
        model.value = { ...model.value, [field]: value };
    }
}
</script>

<template>
    <div class="space-y-6">
        <div
            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        >
            <label class="flex cursor-pointer items-start gap-3">
                <input
                    type="checkbox"
                    :checked="isEnabled"
                    @change="
                        isEnabled = ($event.target as HTMLInputElement).checked
                    "
                    class="mt-1 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                />
                <div>
                    <span
                        class="text-sm font-medium text-slate-900 dark:text-slate-100"
                    >
                        Enable Night Differential
                    </span>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Apply additional pay for work performed during nighttime
                        hours
                    </p>
                </div>
            </label>
        </div>

        <div v-if="isEnabled" class="space-y-6">
            <div
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
            >
                <h4
                    class="mb-4 text-sm font-medium text-slate-900 dark:text-slate-100"
                >
                    Night Hours Window
                </h4>
                <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
                    Define the time range that qualifies for night differential
                    pay
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Start Time -->
                    <div class="space-y-2">
                        <Label for="night_start_time">Start Time</Label>
                        <Input
                            id="night_start_time"
                            type="time"
                            :model-value="model?.start_time || '22:00'"
                            @update:model-value="
                                updateField('start_time', $event)
                            "
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            When night shift begins (e.g., 10:00 PM)
                        </p>
                    </div>

                    <!-- End Time -->
                    <div class="space-y-2">
                        <Label for="night_end_time">End Time</Label>
                        <Input
                            id="night_end_time"
                            type="time"
                            :model-value="model?.end_time || '06:00'"
                            @update:model-value="
                                updateField('end_time', $event)
                            "
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            When night shift ends (e.g., 6:00 AM)
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
                    Rate Multiplier
                </h4>
                <div class="space-y-2">
                    <Label for="night_rate_multiplier"
                        >Night Differential Rate</Label
                    >
                    <div class="relative max-w-xs">
                        <Input
                            id="night_rate_multiplier"
                            type="number"
                            min="1"
                            max="3"
                            step="0.01"
                            :model-value="model?.rate_multiplier || 1.1"
                            @update:model-value="
                                updateField(
                                    'rate_multiplier',
                                    parseFloat($event as string) || 1.1,
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
                        e.g., 1.10 = 110% of base hourly rate for night hours
                    </p>
                </div>
            </div>

            <!-- Summary -->
            <div class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                <div class="flex items-start gap-3">
                    <svg
                        class="h-5 w-5 text-blue-600 dark:text-blue-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"
                        />
                    </svg>
                    <div class="text-sm">
                        <p class="font-medium text-blue-900 dark:text-blue-100">
                            Night Differential Summary
                        </p>
                        <p class="mt-1 text-blue-700 dark:text-blue-300">
                            Work hours between
                            {{ model?.start_time || '22:00' }} and
                            {{ model?.end_time || '06:00' }} will receive
                            {{
                                ((model?.rate_multiplier || 1.1) * 100).toFixed(
                                    0,
                                )
                            }}% of base pay ({{
                                (
                                    ((model?.rate_multiplier || 1.1) - 1) *
                                    100
                                ).toFixed(0)
                            }}% additional).
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-else
            class="rounded-lg bg-slate-50 p-6 text-center dark:bg-slate-800/50"
        >
            <svg
                class="mx-auto h-10 w-10 text-slate-400"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z"
                />
            </svg>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                Night differential is disabled for this schedule.
            </p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Enable to add extra pay for nighttime work hours.
            </p>
        </div>
    </div>
</template>
