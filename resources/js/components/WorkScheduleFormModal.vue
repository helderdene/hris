<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import CompressedScheduleConfig from '@/Components/WorkSchedule/CompressedScheduleConfig.vue';
import FixedScheduleConfig from '@/Components/WorkSchedule/FixedScheduleConfig.vue';
import FlexibleScheduleConfig from '@/Components/WorkSchedule/FlexibleScheduleConfig.vue';
import NightDifferentialConfig from '@/Components/WorkSchedule/NightDifferentialConfig.vue';
import OvertimeRulesConfig from '@/Components/WorkSchedule/OvertimeRulesConfig.vue';
import ShiftingScheduleConfig from '@/Components/WorkSchedule/ShiftingScheduleConfig.vue';
import { computed, ref, watch } from 'vue';

interface TimeConfiguration {
    work_days?: string[];
    half_day_saturday?: boolean;
    start_time?: string;
    end_time?: string;
    saturday_end_time?: string | null;
    break?: { start_time: string | null; duration_minutes: number };
    required_hours_per_day?: number;
    required_hours_per_week?: number;
    core_hours?: { start_time: string; end_time: string };
    flexible_start_window?: { earliest: string; latest: string };
    shifts?: Array<{
        name: string;
        start_time: string;
        end_time: string;
        break?: { start_time: string; duration_minutes: number };
    }>;
    pattern?: string;
    daily_hours?: number;
    half_day?: { enabled: boolean; day: string | null; hours: number | null };
}

interface OvertimeRules {
    daily_threshold_hours: number;
    weekly_threshold_hours: number;
    regular_multiplier: number;
    rest_day_multiplier: number;
    holiday_multiplier: number;
}

interface NightDifferential {
    enabled: boolean;
    start_time: string;
    end_time: string;
    rate_multiplier: number;
}

interface WorkSchedule {
    id: number;
    name: string;
    code: string;
    schedule_type: string;
    schedule_type_label: string;
    description: string | null;
    status: string;
    time_configuration: TimeConfiguration;
    overtime_rules: OvertimeRules | null;
    night_differential: NightDifferential | null;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    schedule: WorkSchedule | null;
    scheduleTypes: EnumOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const activeTab = ref<'basic' | 'time' | 'overtime' | 'night'>('basic');

const form = ref({
    name: '',
    code: '',
    description: '',
    schedule_type: 'fixed',
    status: 'active',
    time_configuration: {} as TimeConfiguration,
    overtime_rules: {
        daily_threshold_hours: 8,
        weekly_threshold_hours: 40,
        regular_multiplier: 1.25,
        rest_day_multiplier: 1.3,
        holiday_multiplier: 2.0,
    } as OvertimeRules,
    night_differential: {
        enabled: false,
        start_time: '22:00',
        end_time: '06:00',
        rate_multiplier: 1.1,
    } as NightDifferential,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.schedule);

const statusOptions: EnumOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const tabs = [
    { key: 'basic', label: 'Basic Info' },
    { key: 'time', label: 'Time Configuration' },
    { key: 'overtime', label: 'Overtime Rules' },
    { key: 'night', label: 'Night Differential' },
];

// Initialize time configuration based on schedule type
function getDefaultTimeConfiguration(scheduleType: string): TimeConfiguration {
    switch (scheduleType) {
        case 'fixed':
            return {
                work_days: [
                    'monday',
                    'tuesday',
                    'wednesday',
                    'thursday',
                    'friday',
                ],
                half_day_saturday: false,
                start_time: '08:00',
                end_time: '17:00',
                saturday_end_time: null,
                break: { start_time: '12:00', duration_minutes: 60 },
            };
        case 'flexible':
            return {
                required_hours_per_day: 8,
                required_hours_per_week: 40,
                core_hours: { start_time: '10:00', end_time: '15:00' },
                flexible_start_window: { earliest: '06:00', latest: '10:00' },
                break: { start_time: null, duration_minutes: 60 },
            };
        case 'shifting':
            return {
                shifts: [
                    {
                        name: 'Morning Shift',
                        start_time: '06:00',
                        end_time: '14:00',
                        break: { start_time: '10:00', duration_minutes: 30 },
                    },
                ],
            };
        case 'compressed':
            return {
                pattern: '4x10',
                work_days: ['monday', 'tuesday', 'wednesday', 'thursday'],
                daily_hours: 10,
                half_day: { enabled: false, day: null, hours: null },
            };
        default:
            return {};
    }
}

watch(
    () => props.schedule,
    (newSchedule) => {
        if (newSchedule) {
            form.value = {
                name: newSchedule.name,
                code: newSchedule.code,
                description: newSchedule.description || '',
                schedule_type: newSchedule.schedule_type,
                status: newSchedule.status,
                time_configuration: { ...newSchedule.time_configuration },
                overtime_rules: newSchedule.overtime_rules || {
                    daily_threshold_hours: 8,
                    weekly_threshold_hours: 40,
                    regular_multiplier: 1.25,
                    rest_day_multiplier: 1.3,
                    holiday_multiplier: 2.0,
                },
                night_differential: newSchedule.night_differential || {
                    enabled: false,
                    start_time: '22:00',
                    end_time: '06:00',
                    rate_multiplier: 1.1,
                },
            };
        } else {
            resetForm();
        }
        errors.value = {};
        activeTab.value = 'basic';
    },
    { immediate: true },
);

watch(
    () => form.value.schedule_type,
    (newType, oldType) => {
        if (newType !== oldType && !props.schedule) {
            form.value.time_configuration =
                getDefaultTimeConfiguration(newType);
        }
    },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
        activeTab.value = 'basic';
    }
});

function resetForm() {
    form.value = {
        name: '',
        code: '',
        description: '',
        schedule_type: 'fixed',
        status: 'active',
        time_configuration: getDefaultTimeConfiguration('fixed'),
        overtime_rules: {
            daily_threshold_hours: 8,
            weekly_threshold_hours: 40,
            regular_multiplier: 1.25,
            rest_day_multiplier: 1.3,
            holiday_multiplier: 2.0,
        },
        night_differential: {
            enabled: false,
            start_time: '22:00',
            end_time: '06:00',
            rate_multiplier: 1.1,
        },
    };
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/work-schedules/${props.schedule!.id}`
        : '/api/organization/work-schedules';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        ...form.value,
        description: form.value.description || null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch (error) {
        errors.value = {
            general: 'An error occurred while saving the schedule',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing
                            ? 'Edit Work Schedule'
                            : 'Create Work Schedule'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the work schedule configuration.'
                            : 'Define a new work schedule with time and overtime settings.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- Tab Navigation -->
                <div class="border-b border-slate-200 dark:border-slate-700">
                    <nav class="-mb-px flex gap-4">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            @click="activeTab = tab.key as typeof activeTab"
                            class="border-b-2 px-1 py-3 text-sm font-medium transition-colors"
                            :class="
                                activeTab === tab.key
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            "
                        >
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>

                <!-- Basic Info Tab -->
                <div v-show="activeTab === 'basic'" class="space-y-4">
                    <!-- Name -->
                    <div class="space-y-2">
                        <Label for="name">Name *</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="e.g., Regular Office Hours"
                            :class="{ 'border-red-500': errors.name }"
                        />
                        <p v-if="errors.name" class="text-sm text-red-500">
                            {{ errors.name }}
                        </p>
                    </div>

                    <!-- Code -->
                    <div class="space-y-2">
                        <Label for="code">Code *</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="e.g., REG-8AM"
                            :class="{ 'border-red-500': errors.code }"
                        />
                        <p v-if="errors.code" class="text-sm text-red-500">
                            {{ errors.code }}
                        </p>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Schedule description..."
                            rows="2"
                            :class="{ 'border-red-500': errors.description }"
                        />
                        <p
                            v-if="errors.description"
                            class="text-sm text-red-500"
                        >
                            {{ errors.description }}
                        </p>
                    </div>

                    <!-- Schedule Type -->
                    <div class="space-y-2">
                        <Label for="schedule_type">Schedule Type *</Label>
                        <EnumSelect
                            id="schedule_type"
                            v-model="form.schedule_type"
                            :options="scheduleTypes"
                            placeholder="Select schedule type"
                        />
                        <p
                            v-if="errors.schedule_type"
                            class="text-sm text-red-500"
                        >
                            {{ errors.schedule_type }}
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="space-y-2">
                        <Label for="status">Status *</Label>
                        <EnumSelect
                            id="status"
                            v-model="form.status"
                            :options="statusOptions"
                            placeholder="Select status"
                        />
                        <p v-if="errors.status" class="text-sm text-red-500">
                            {{ errors.status }}
                        </p>
                    </div>
                </div>

                <!-- Time Configuration Tab -->
                <div v-show="activeTab === 'time'" class="space-y-4">
                    <FixedScheduleConfig
                        v-if="form.schedule_type === 'fixed'"
                        v-model="form.time_configuration"
                    />
                    <FlexibleScheduleConfig
                        v-else-if="form.schedule_type === 'flexible'"
                        v-model="form.time_configuration"
                    />
                    <ShiftingScheduleConfig
                        v-else-if="form.schedule_type === 'shifting'"
                        v-model="form.time_configuration"
                    />
                    <CompressedScheduleConfig
                        v-else-if="form.schedule_type === 'compressed'"
                        v-model="form.time_configuration"
                    />
                </div>

                <!-- Overtime Rules Tab -->
                <div v-show="activeTab === 'overtime'" class="space-y-4">
                    <OvertimeRulesConfig v-model="form.overtime_rules" />
                </div>

                <!-- Night Differential Tab -->
                <div v-show="activeTab === 'night'" class="space-y-4">
                    <NightDifferentialConfig
                        v-model="form.night_differential"
                    />
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        <svg
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ isEditing ? 'Update Schedule' : 'Create Schedule' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
