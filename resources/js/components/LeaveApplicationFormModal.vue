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
import { Paperclip, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
    department?: string | null;
    position?: string | null;
    employment_type?: string | null;
    employment_type_label?: string | null;
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

interface Attachment {
    name: string;
    mime: string;
    size: number;
    url: string;
}

interface LeaveApplication {
    id: number;
    leave_type_id: number;
    start_date: string;
    end_date: string;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
    reason: string;
    attachment?: Attachment | null;
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

const leaveTypeId = ref<string>('');
const startDate = ref('');
const endDate = ref('');
const isHalfDayStart = ref(false);
const isHalfDayEnd = ref(false);
const reason = ref('');
const attachmentFile = ref<File | null>(null);
const removeAttachment = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const isEditing = computed(() => props.application !== null);

const dateFiled = computed(() =>
    new Date().toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }),
);

const selectedLeaveType = computed(() => {
    if (!leaveTypeId.value) {
        return null;
    }
    return props.leaveTypes.find((t) => t.id === Number(leaveTypeId.value));
});

const selectedBalance = computed(() => {
    if (!leaveTypeId.value) {
        return null;
    }
    return props.balances.find(
        (b) => b.leave_type_id === Number(leaveTypeId.value),
    );
});

const calculatedDays = computed(() => {
    if (!startDate.value || !endDate.value) {
        return 0;
    }

    const start = new Date(startDate.value);
    const end = new Date(endDate.value);
    let days =
        Math.ceil((end.getTime() - start.getTime()) / (1000 * 60 * 60 * 24)) +
        1;

    if (isHalfDayStart.value) {
        days -= 0.5;
    }
    if (isHalfDayEnd.value) {
        days -= 0.5;
    }

    return Math.max(0.5, days);
});

const minDate = computed(() => {
    return new Date().toISOString().split('T')[0];
});

const existingAttachment = computed(() => props.application?.attachment ?? null);

const showExistingAttachment = computed(
    () =>
        existingAttachment.value !== null &&
        !removeAttachment.value &&
        attachmentFile.value === null,
);

watch(open, (newValue) => {
    if (!newValue) {
        return;
    }

    if (props.application) {
        leaveTypeId.value = String(props.application.leave_type_id);
        startDate.value = props.application.start_date;
        endDate.value = props.application.end_date;
        isHalfDayStart.value = props.application.is_half_day_start;
        isHalfDayEnd.value = props.application.is_half_day_end;
        reason.value = props.application.reason;
    } else {
        leaveTypeId.value = '';
        startDate.value = '';
        endDate.value = '';
        isHalfDayStart.value = false;
        isHalfDayEnd.value = false;
        reason.value = '';
    }
    attachmentFile.value = null;
    removeAttachment.value = false;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    errors.value = {};
});

watch(startDate, (newValue) => {
    if (newValue && !endDate.value) {
        endDate.value = newValue;
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function handleFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    attachmentFile.value = input.files?.[0] ?? null;
    if (attachmentFile.value) {
        removeAttachment.value = false;
    }
}

function clearSelectedFile(): void {
    attachmentFile.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function markAttachmentForRemoval(): void {
    removeAttachment.value = true;
    clearSelectedFile();
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

async function handleSubmit() {
    if (!props.employee) {
        return;
    }

    isSubmitting.value = true;
    errors.value = {};

    try {
        const url = isEditing.value
            ? `/api/leave-applications/${props.application!.id}`
            : '/api/leave-applications';

        const formData = new FormData();
        formData.append('employee_id', String(props.employee.id));
        formData.append('leave_type_id', leaveTypeId.value);
        formData.append('start_date', startDate.value);
        formData.append('end_date', endDate.value);
        formData.append('is_half_day_start', isHalfDayStart.value ? '1' : '0');
        formData.append('is_half_day_end', isHalfDayEnd.value ? '1' : '0');
        formData.append('reason', reason.value);

        if (attachmentFile.value) {
            formData.append('attachment', attachmentFile.value);
        }

        if (isEditing.value) {
            formData.append('_method', 'PUT');
            if (removeAttachment.value && !attachmentFile.value) {
                formData.append('remove_attachment', '1');
            }
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422) {
            errors.value = data.errors || {};
        } else {
            errors.value = {
                general: [data.message || 'An error occurred'],
            };
        }
    } catch {
        errors.value = {
            general: ['An unexpected error occurred. Please try again.'],
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Leave Application' : 'New Leave Application' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update your leave request details.'
                            : 'Submit a new leave request for approval.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors.general[0] }}
                </div>

                <!-- I. Employee Information -->
                <section class="space-y-3">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                            I. Employee Information
                        </h3>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            Inherited from your employee record
                        </span>
                    </header>

                    <dl class="grid gap-x-4 gap-y-3 rounded-md border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800/40">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Date Filed
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ dateFiled }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Employee ID
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee?.employee_number ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Employee Name
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee?.full_name ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Department
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee?.department ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Position
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee?.position ?? '—' }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                                Employment Status
                            </dt>
                            <dd class="mt-0.5 text-slate-900 dark:text-slate-100">
                                {{ employee?.employment_type_label ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- II. Leave Details -->
                <section class="space-y-4">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                        II. Leave Details
                    </h3>

                    <div class="space-y-2">
                        <Label for="leave_type">Type of Leave <span class="text-red-500">*</span></Label>
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
                        <p
                            v-if="selectedBalance"
                            class="text-sm text-slate-500 dark:text-slate-400"
                        >
                            Available balance: {{ selectedBalance.available }} days
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="start_date">Leave Start Date <span class="text-red-500">*</span></Label>
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
                            <Label for="end_date">Leave End Date <span class="text-red-500">*</span></Label>
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

                    <div class="rounded-md bg-slate-50 p-3 dark:bg-slate-800">
                        <p class="text-sm text-slate-600 dark:text-slate-300">
                            <span class="font-medium">Total No. of Days:</span>
                            {{ startDate && endDate ? `${calculatedDays} day(s)` : 'Auto-calculated from leave dates' }}
                        </p>
                        <p v-if="errors.total_days" class="mt-1 text-sm text-red-500">
                            {{ errors.total_days[0] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="reason">Reason <span class="text-red-500">*</span></Label>
                        <Textarea
                            id="reason"
                            v-model="reason"
                            placeholder="Please provide a brief explanation for your leave request..."
                            rows="3"
                        />
                        <p v-if="errors.reason" class="text-sm text-red-500">
                            {{ errors.reason[0] }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="attachment">Upload Supporting Docs</Label>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Optional. PDF, image, or Word document up to 10MB.
                        </p>

                        <div
                            v-if="showExistingAttachment && existingAttachment"
                            class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <a
                                :href="existingAttachment.url"
                                target="_blank"
                                rel="noopener"
                                class="flex items-center gap-2 text-blue-600 hover:underline dark:text-blue-400"
                            >
                                <Paperclip class="h-4 w-4" />
                                <span>{{ existingAttachment.name }}</span>
                                <span class="text-xs text-slate-500">
                                    ({{ formatBytes(existingAttachment.size) }})
                                </span>
                            </a>
                            <button
                                type="button"
                                class="text-xs text-red-600 hover:underline dark:text-red-400"
                                @click="markAttachmentForRemoval"
                            >
                                Remove
                            </button>
                        </div>

                        <div
                            v-if="attachmentFile"
                            class="flex items-center justify-between rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <span class="flex items-center gap-2 text-slate-700 dark:text-slate-200">
                                <Paperclip class="h-4 w-4" />
                                {{ attachmentFile.name }}
                                <span class="text-xs text-slate-500">
                                    ({{ formatBytes(attachmentFile.size) }})
                                </span>
                            </span>
                            <button
                                type="button"
                                class="text-slate-400 hover:text-red-500"
                                @click="clearSelectedFile"
                                :aria-label="'Remove selected file'"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <Input
                            id="attachment"
                            ref="fileInput"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            @change="handleFileChange"
                        />
                        <p v-if="errors.attachment" class="text-sm text-red-500">
                            {{ errors.attachment[0] }}
                        </p>
                    </div>
                </section>

                <div
                    v-if="selectedLeaveType?.requires_attachment"
                    class="rounded-md bg-blue-50 p-3 text-sm text-blue-700 dark:bg-blue-900/20 dark:text-blue-400"
                >
                    This leave type requires a supporting document.
                </div>

                <div
                    v-if="selectedLeaveType?.min_days_advance_notice && selectedLeaveType.min_days_advance_notice > 0"
                    class="rounded-md bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/20 dark:text-amber-400"
                >
                    This leave type requires {{ selectedLeaveType.min_days_advance_notice }} days advance notice.
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{
                            isSubmitting
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update'
                                  : 'Create Draft'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
