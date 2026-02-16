<script setup lang="ts">
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

interface OvertimeTypeOption {
    value: string;
    label: string;
    color: string;
}

interface OvertimeRequest {
    id: number;
    overtime_date: string;
    expected_start_time: string | null;
    expected_end_time: string | null;
    expected_minutes: number;
    overtime_type: string;
    reason: string;
}

const props = defineProps<{
    request: OvertimeRequest | null;
    employee: Employee | null;
    overtimeTypes: OvertimeTypeOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

// Form data
const overtimeDate = ref('');
const expectedStartTime = ref('');
const expectedEndTime = ref('');
const expectedMinutes = ref<number | ''>('');
const overtimeType = ref<string>('');
const reason = ref('');

const isEditing = computed(() => props.request !== null);

const calculatedHours = computed(() => {
    if (!expectedMinutes.value) return '';
    const mins = Number(expectedMinutes.value);
    const hours = Math.floor(mins / 60);
    const remaining = mins % 60;
    if (hours === 0) return `${remaining}m`;
    if (remaining === 0) return `${hours}h`;
    return `${hours}h ${remaining}m`;
});

// Auto-calculate minutes from start/end time
watch([expectedStartTime, expectedEndTime], ([start, end]) => {
    if (start && end) {
        const [startH, startM] = start.split(':').map(Number);
        const [endH, endM] = end.split(':').map(Number);
        const totalStart = startH * 60 + startM;
        const totalEnd = endH * 60 + endM;
        let diff = totalEnd - totalStart;
        if (diff < 0) diff += 24 * 60; // handle past midnight
        if (diff > 0 && diff <= 720) {
            expectedMinutes.value = diff;
        }
    }
});

// Reset form when modal opens/closes
watch(open, (newValue) => {
    if (newValue) {
        if (props.request) {
            overtimeDate.value = props.request.overtime_date;
            expectedStartTime.value = props.request.expected_start_time || '';
            expectedEndTime.value = props.request.expected_end_time || '';
            expectedMinutes.value = props.request.expected_minutes;
            overtimeType.value = props.request.overtime_type;
            reason.value = props.request.reason;
        } else {
            overtimeDate.value = '';
            expectedStartTime.value = '';
            expectedEndTime.value = '';
            expectedMinutes.value = '';
            overtimeType.value = '';
            reason.value = '';
        }
        errors.value = {};
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    if (!props.employee) return;

    isSubmitting.value = true;
    errors.value = {};

    try {
        const url = isEditing.value
            ? `/api/overtime-requests/${props.request!.id}`
            : '/api/overtime-requests';

        const method = isEditing.value ? 'PUT' : 'POST';

        const body: Record<string, unknown> = {
            employee_id: props.employee.id,
            overtime_date: overtimeDate.value,
            expected_minutes: Number(expectedMinutes.value),
            overtime_type: overtimeType.value,
            reason: reason.value,
        };

        if (expectedStartTime.value) {
            body.expected_start_time = expectedStartTime.value;
        }
        if (expectedEndTime.value) {
            body.expected_end_time = expectedEndTime.value;
        }

        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422) {
            errors.value = data.errors || {};
        } else {
            errors.value = { general: [data.message || 'An error occurred'] };
        }
    } catch {
        errors.value = { general: ['An unexpected error occurred. Please try again.'] };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit OT Request' : 'New OT Request' }}
                </DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update your overtime request details.' : 'File a new overtime request for approval.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div v-if="errors.general" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                    {{ errors.general[0] }}
                </div>

                <!-- OT Date -->
                <div class="space-y-2">
                    <Label for="overtime_date">Overtime Date</Label>
                    <Input
                        id="overtime_date"
                        type="date"
                        v-model="overtimeDate"
                    />
                    <p v-if="errors.overtime_date" class="text-sm text-red-500">
                        {{ errors.overtime_date[0] }}
                    </p>
                </div>

                <!-- OT Type -->
                <div class="space-y-2">
                    <Label for="overtime_type">Overtime Type</Label>
                    <Select v-model="overtimeType">
                        <SelectTrigger id="overtime_type">
                            <SelectValue placeholder="Select OT type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in overtimeTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.overtime_type" class="text-sm text-red-500">
                        {{ errors.overtime_type[0] }}
                    </p>
                </div>

                <!-- Time Range -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="expected_start_time">Start Time (optional)</Label>
                        <Input
                            id="expected_start_time"
                            type="time"
                            v-model="expectedStartTime"
                        />
                        <p v-if="errors.expected_start_time" class="text-sm text-red-500">
                            {{ errors.expected_start_time[0] }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="expected_end_time">End Time (optional)</Label>
                        <Input
                            id="expected_end_time"
                            type="time"
                            v-model="expectedEndTime"
                        />
                        <p v-if="errors.expected_end_time" class="text-sm text-red-500">
                            {{ errors.expected_end_time[0] }}
                        </p>
                    </div>
                </div>

                <!-- Expected Minutes -->
                <div class="space-y-2">
                    <Label for="expected_minutes">Expected Duration (minutes)</Label>
                    <Input
                        id="expected_minutes"
                        type="number"
                        v-model.number="expectedMinutes"
                        :min="30"
                        :max="720"
                        placeholder="e.g. 120"
                    />
                    <p v-if="errors.expected_minutes" class="text-sm text-red-500">
                        {{ errors.expected_minutes[0] }}
                    </p>
                    <p v-if="calculatedHours" class="text-sm text-slate-500 dark:text-slate-400">
                        Duration: {{ calculatedHours }}
                    </p>
                </div>

                <!-- Reason -->
                <div class="space-y-2">
                    <Label for="reason">Reason</Label>
                    <Textarea
                        id="reason"
                        v-model="reason"
                        placeholder="Please provide a reason for the overtime request..."
                        rows="3"
                    />
                    <p v-if="errors.reason" class="text-sm text-red-500">
                        {{ errors.reason[0] }}
                    </p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false" :disabled="isSubmitting">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : (isEditing ? 'Update' : 'Create Draft') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
