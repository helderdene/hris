<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed } from 'vue';

interface BreakConfig {
    start_time: string;
    duration_minutes: number;
}

interface Shift {
    name: string;
    start_time: string;
    end_time: string;
    break?: BreakConfig;
}

interface TimeConfiguration {
    shifts: Shift[];
}

const model = defineModel<TimeConfiguration>({ required: true });

const shifts = computed(() => model.value?.shifts || []);

function addShift() {
    const newShift: Shift = {
        name: `Shift ${shifts.value.length + 1}`,
        start_time: '08:00',
        end_time: '16:00',
        break: {
            start_time: '12:00',
            duration_minutes: 30,
        },
    };

    if (model.value) {
        model.value = {
            ...model.value,
            shifts: [...shifts.value, newShift],
        };
    }
}

function removeShift(index: number) {
    if (model.value && shifts.value.length > 1) {
        const updatedShifts = [...shifts.value];
        updatedShifts.splice(index, 1);
        model.value = {
            ...model.value,
            shifts: updatedShifts,
        };
    }
}

function updateShiftField(index: number, field: keyof Shift, value: any) {
    if (model.value) {
        const updatedShifts = [...shifts.value];
        updatedShifts[index] = { ...updatedShifts[index], [field]: value };
        model.value = {
            ...model.value,
            shifts: updatedShifts,
        };
    }
}

function updateShiftBreakField(
    index: number,
    field: keyof BreakConfig,
    value: any,
) {
    if (model.value) {
        const updatedShifts = [...shifts.value];
        updatedShifts[index] = {
            ...updatedShifts[index],
            break: {
                ...updatedShifts[index].break,
                [field]: value,
            } as BreakConfig,
        };
        model.value = {
            ...model.value,
            shifts: updatedShifts,
        };
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h4
                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                >
                    Shifts
                </h4>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Define multiple shifts that employees can be assigned to
                </p>
            </div>
            <Button type="button" variant="outline" size="sm" @click="addShift">
                <svg
                    class="mr-1.5 h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 4.5v15m7.5-7.5h-15"
                    />
                </svg>
                Add Shift
            </Button>
        </div>

        <div class="space-y-4">
            <div
                v-for="(shift, index) in shifts"
                :key="index"
                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h5
                        class="text-sm font-medium text-slate-900 dark:text-slate-100"
                    >
                        Shift {{ index + 1 }}
                    </h5>
                    <Button
                        v-if="shifts.length > 1"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 w-8 p-0 text-red-500 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                        @click="removeShift(index)"
                    >
                        <svg
                            class="h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                            />
                        </svg>
                    </Button>
                </div>

                <!-- Shift Name -->
                <div class="mb-4 space-y-2">
                    <Label :for="`shift_name_${index}`">Shift Name</Label>
                    <Input
                        :id="`shift_name_${index}`"
                        type="text"
                        :model-value="shift.name"
                        @update:model-value="
                            updateShiftField(index, 'name', $event)
                        "
                        placeholder="e.g., Morning Shift"
                    />
                </div>

                <div class="mb-4 grid gap-4 sm:grid-cols-2">
                    <!-- Start Time -->
                    <div class="space-y-2">
                        <Label :for="`shift_start_${index}`">Start Time</Label>
                        <Input
                            :id="`shift_start_${index}`"
                            type="time"
                            :model-value="shift.start_time"
                            @update:model-value="
                                updateShiftField(index, 'start_time', $event)
                            "
                        />
                    </div>

                    <!-- End Time -->
                    <div class="space-y-2">
                        <Label :for="`shift_end_${index}`">End Time</Label>
                        <Input
                            :id="`shift_end_${index}`"
                            type="time"
                            :model-value="shift.end_time"
                            @update:model-value="
                                updateShiftField(index, 'end_time', $event)
                            "
                        />
                    </div>
                </div>

                <!-- Break Configuration -->
                <div class="rounded-md bg-slate-50 p-3 dark:bg-slate-800/50">
                    <Label class="mb-2 block text-xs font-medium"
                        >Break Period</Label
                    >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label
                                :for="`shift_break_start_${index}`"
                                class="text-xs"
                                >Break Start</Label
                            >
                            <Input
                                :id="`shift_break_start_${index}`"
                                type="time"
                                :model-value="
                                    shift.break?.start_time || '12:00'
                                "
                                @update:model-value="
                                    updateShiftBreakField(
                                        index,
                                        'start_time',
                                        $event,
                                    )
                                "
                            />
                        </div>
                        <div class="space-y-2">
                            <Label
                                :for="`shift_break_duration_${index}`"
                                class="text-xs"
                                >Duration (min)</Label
                            >
                            <Input
                                :id="`shift_break_duration_${index}`"
                                type="number"
                                min="0"
                                max="120"
                                :model-value="
                                    shift.break?.duration_minutes || 30
                                "
                                @update:model-value="
                                    updateShiftBreakField(
                                        index,
                                        'duration_minutes',
                                        parseInt($event as string) || 30,
                                    )
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="shifts.length === 0"
            class="rounded-lg border-2 border-dashed border-slate-200 p-8 text-center dark:border-slate-700"
        >
            <svg
                class="mx-auto h-12 w-12 text-slate-400"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                />
            </svg>
            <h3
                class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
            >
                No shifts defined
            </h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Add at least one shift for this schedule.
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                class="mt-4"
                @click="addShift"
            >
                <svg
                    class="mr-1.5 h-4 w-4"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 4.5v15m7.5-7.5h-15"
                    />
                </svg>
                Add First Shift
            </Button>
        </div>
    </div>
</template>
