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

interface LeaveType {
    id: number;
    name: string;
    code: string;
    requires_attachment: boolean;
    min_days_advance_notice: number;
}

interface Balance {
    leave_type_id: number;
    leave_type_name: string;
    available: number;
    used: number;
    pending: number;
}

interface LeaveApplication {
    id: number;
    leave_type_id: number;
    start_date: string;
    end_date: string;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
    reason: string;
}

const props = defineProps<{
    application: LeaveApplication | null;
    employee: Employee | null;
    leaveTypes: LeaveType[];
    balances: Balance[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

// Form data
const leaveTypeId = ref<string>('');
const startDate = ref('');
const endDate = ref('');
const isHalfDayStart = ref(false);
const isHalfDayEnd = ref(false);
const reason = ref('');

const isEditing = computed(() => props.application !== null);

const selectedLeaveType = computed(() => {
    if (!leaveTypeId.value) return null;
    return props.leaveTypes.find((t) => t.id === Number(leaveTypeId.value));
});

const selectedBalance = computed(() => {
    if (!leaveTypeId.value) return null;
    return props.balances.find((b) => b.leave_type_id === Number(leaveTypeId.value));
});

const calculatedDays = computed(() => {
    if (!startDate.value || !endDate.value) return 0;

    const start = new Date(startDate.value);
    const end = new Date(endDate.value);
    let days = Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) + 1;

    if (isHalfDayStart.value) days -= 0.5;
    if (isHalfDayEnd.value) days -= 0.5;

    return Math.max(0.5, days);
});

const minDate = computed(() => {
    return new Date().toISOString().split('T')[0];
});

// Reset form when modal opens/closes
watch(open, (newValue) => {
    if (newValue) {
        if (props.application) {
            // Editing mode
            leaveTypeId.value = String(props.application.leave_type_id);
            startDate.value = props.application.start_date;
            endDate.value = props.application.end_date;
            isHalfDayStart.value = props.application.is_half_day_start;
            isHalfDayEnd.value = props.application.is_half_day_end;
            reason.value = props.application.reason;
        } else {
            // Create mode - reset form
            leaveTypeId.value = '';
            startDate.value = '';
            endDate.value = '';
            isHalfDayStart.value = false;
            isHalfDayEnd.value = false;
            reason.value = '';
        }
        errors.value = {};
    }
});

// Auto-set end date when start date changes
watch(startDate, (newValue) => {
    if (newValue && !endDate.value) {
        endDate.value = newValue;
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
            ? `/api/leave-applications/${props.application!.id}`
            : '/api/leave-applications';

        const method = isEditing.value ? 'PUT' : 'POST';

        const body = {
            employee_id: props.employee.id,
            leave_type_id: Number(leaveTypeId.value),
            start_date: startDate.value,
            end_date: endDate.value,
            is_half_day_start: isHalfDayStart.value,
            is_half_day_end: isHalfDayEnd.value,
            reason: reason.value,
        };

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
                    {{ isEditing ? 'Edit Leave Application' : 'New Leave Application' }}
                </DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update your leave request details.' : 'Submit a new leave request for approval.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div v-if="errors.general" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                    {{ errors.general[0] }}
                </div>

                <!-- Leave Type -->
                <div class="space-y-2">
                    <Label for="leave_type">Leave Type</Label>
                    <Select v-model="leaveTypeId">
                        <SelectTrigger id="leave_type">
                            <SelectValue placeholder="Select leave type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="type in leaveTypes"
                                :key="type.id"
                                :value="String(type.id)"
                            >
                                {{ type.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.leave_type_id" class="text-sm text-red-500">
                        {{ errors.leave_type_id[0] }}
                    </p>
                    <p v-if="selectedBalance" class="text-sm text-slate-500 dark:text-slate-400">
                        Available balance: {{ selectedBalance.available }} days
                    </p>
                </div>

                <!-- Date Range -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="start_date">Start Date</Label>
                        <Input
                            id="start_date"
                            type="date"
                            v-model="startDate"
                            :min="minDate"
                        />
                        <p v-if="errors.start_date" class="text-sm text-red-500">
                            {{ errors.start_date[0] }}
                        </p>
                        <div class="flex items-center gap-2">
                            <input
                                id="half_day_start"
                                type="checkbox"
                                v-model="isHalfDayStart"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <Label for="half_day_start" class="text-sm font-normal">
                                Half day
                            </Label>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="end_date">End Date</Label>
                        <Input
                            id="end_date"
                            type="date"
                            v-model="endDate"
                            :min="startDate || minDate"
                        />
                        <p v-if="errors.end_date" class="text-sm text-red-500">
                            {{ errors.end_date[0] }}
                        </p>
                        <div class="flex items-center gap-2">
                            <input
                                id="half_day_end"
                                type="checkbox"
                                v-model="isHalfDayEnd"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <Label for="half_day_end" class="text-sm font-normal">
                                Half day
                            </Label>
                        </div>
                    </div>
                </div>

                <!-- Duration Summary -->
                <div v-if="startDate && endDate" class="rounded-md bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        <span class="font-medium">Total duration:</span>
                        {{ calculatedDays }} day(s)
                    </p>
                    <p v-if="errors.total_days" class="mt-1 text-sm text-red-500">
                        {{ errors.total_days[0] }}
                    </p>
                </div>

                <!-- Reason -->
                <div class="space-y-2">
                    <Label for="reason">Reason</Label>
                    <Textarea
                        id="reason"
                        v-model="reason"
                        placeholder="Please provide a reason for your leave request..."
                        rows="3"
                    />
                    <p v-if="errors.reason" class="text-sm text-red-500">
                        {{ errors.reason[0] }}
                    </p>
                </div>

                <!-- Advance Notice Warning -->
                <div
                    v-if="selectedLeaveType?.min_days_advance_notice && selectedLeaveType.min_days_advance_notice > 0"
                    class="rounded-md bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                >
                    This leave type requires {{ selectedLeaveType.min_days_advance_notice }} days advance notice.
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
