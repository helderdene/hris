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
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
}

interface ManualAttendanceRequest {
    id: number;
    attendance_date: string;
    time_in: string | null;
    time_out: string | null;
    reason: string;
}

const props = defineProps<{
    request: ManualAttendanceRequest | null;
    employee: Employee | null;
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);
const submitMode = ref<'draft' | 'submit'>('draft');
const errors = ref<Record<string, string[]>>({});

const attendanceDate = ref('');
const timeIn = ref('');
const timeOut = ref('');
const reason = ref('');

const isEditing = computed(() => props.request !== null);

const today = computed(() => {
    const d = new Date();
    return d.toISOString().slice(0, 10);
});

const sixtyDaysAgo = computed(() => {
    const d = new Date();
    d.setDate(d.getDate() - 60);
    return d.toISOString().slice(0, 10);
});

watch(open, (isOpen) => {
    if (isOpen) {
        if (props.request) {
            attendanceDate.value = props.request.attendance_date;
            timeIn.value = props.request.time_in ?? '';
            timeOut.value = props.request.time_out ?? '';
            reason.value = props.request.reason;
        } else {
            attendanceDate.value = '';
            timeIn.value = '';
            timeOut.value = '';
            reason.value = '';
        }
        errors.value = {};
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function validateLocal(): string | null {
    if (!attendanceDate.value) {
        return 'Please pick an attendance date.';
    }
    if (!timeIn.value && !timeOut.value) {
        return 'Please provide a time in, a time out, or both.';
    }
    if (timeIn.value && timeOut.value && timeOut.value <= timeIn.value) {
        return 'Time out must be later than time in.';
    }
    if (!reason.value.trim()) {
        return 'Please describe why this manual entry is needed.';
    }
    return null;
}

function handleSaveDraft() {
    submitMode.value = 'draft';
    void persist(false);
}

function handleSaveAndSubmit() {
    submitMode.value = 'submit';
    void persist(true);
}

async function persist(andSubmit: boolean) {
    if (!props.employee) return;

    const localError = validateLocal();
    if (localError) {
        errors.value = { general: [localError] };
        return;
    }

    isSubmitting.value = true;
    errors.value = {};

    try {
        const url = isEditing.value
            ? `/api/manual-attendance-requests/${props.request!.id}`
            : '/api/manual-attendance-requests';
        const method = isEditing.value ? 'PUT' : 'POST';

        const body: Record<string, unknown> = {
            employee_id: props.employee.id,
            attendance_date: attendanceDate.value,
            reason: reason.value,
        };

        if (timeIn.value) {
            body.time_in = timeIn.value;
        }
        if (timeOut.value) {
            body.time_out = timeOut.value;
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

        if (!response.ok) {
            if (response.status === 422) {
                errors.value = data.errors || {
                    general: [data.message || 'Validation failed.'],
                };
            } else {
                errors.value = {
                    general: [data.message || 'An error occurred.'],
                };
            }
            return;
        }

        if (!andSubmit) {
            emit('success');
            return;
        }

        const savedId: number | undefined =
            data?.id ?? data?.data?.id ?? props.request?.id;
        if (!savedId) {
            errors.value = {
                general: [
                    'Saved as draft, but could not submit for approval. Please use the Submit button on the request row.',
                ],
            };
            return;
        }

        const submitResponse = await fetch(
            `/api/manual-attendance-requests/${savedId}/submit`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (submitResponse.ok) {
            emit('success');
            return;
        }

        let submitMessage =
            'Saved as draft, but could not submit for approval.';
        try {
            const submitData = await submitResponse.json();
            if (submitResponse.status === 422 && submitData.errors) {
                const firstError = Object.values(
                    submitData.errors as Record<string, string[]>,
                )[0]?.[0];
                if (firstError) {
                    submitMessage = `Saved as draft, but submission failed: ${firstError}`;
                }
            } else if (submitData.message) {
                submitMessage = `Saved as draft, but submission failed: ${submitData.message}`;
            }
        } catch {
            // ignore JSON parse errors; use default message
        }
        errors.value = { general: [submitMessage] };
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
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        isEditing
                            ? 'Edit Manual Attendance Request'
                            : 'New Manual Attendance Request'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the details of your draft request.'
                            : 'Submit a missing clock-in and/or clock-out for approval.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSaveAndSubmit" class="space-y-4">
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors.general[0] }}
                </div>

                <div class="space-y-2">
                    <Label for="attendance_date">Attendance Date</Label>
                    <Input
                        id="attendance_date"
                        type="date"
                        v-model="attendanceDate"
                        :max="today"
                        :min="sixtyDaysAgo"
                    />
                    <p
                        v-if="errors.attendance_date"
                        class="text-sm text-red-500"
                    >
                        {{ errors.attendance_date[0] }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="time_in">Time In (optional)</Label>
                        <Input id="time_in" type="time" v-model="timeIn" />
                        <p v-if="errors.time_in" class="text-sm text-red-500">
                            {{ errors.time_in[0] }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="time_out">Time Out (optional)</Label>
                        <Input id="time_out" type="time" v-model="timeOut" />
                        <p v-if="errors.time_out" class="text-sm text-red-500">
                            {{ errors.time_out[0] }}
                        </p>
                    </div>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Provide at least one of time in or time out.
                </p>

                <div class="space-y-2">
                    <Label for="reason">Reason</Label>
                    <Textarea
                        id="reason"
                        v-model="reason"
                        placeholder="e.g. Biometric device was offline; my supervisor witnessed me clock in."
                        rows="3"
                    />
                    <p v-if="errors.reason" class="text-sm text-red-500">
                        {{ errors.reason[0] }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isSubmitting"
                        @click="handleSaveDraft"
                    >
                        {{
                            isSubmitting && submitMode === 'draft'
                                ? 'Saving...'
                                : 'Save as Draft'
                        }}
                    </Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{
                            isSubmitting && submitMode === 'submit'
                                ? 'Submitting...'
                                : 'Save &amp; Submit'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
