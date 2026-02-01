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

interface KpiTemplate {
    id: number;
    name: string;
    metric_unit: string;
}

interface KpiAssignment {
    id: number;
    target_value: number;
    actual_value: number | null;
    achievement_percentage: number | null;
    kpi_template?: KpiTemplate;
}

const props = defineProps<{
    assignment: KpiAssignment | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    value: '',
    notes: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const metricUnit = computed(() => props.assignment?.kpi_template?.metric_unit || 'units');

watch(
    () => props.assignment,
    (newAssignment) => {
        if (newAssignment) {
            // Pre-fill with current actual value if exists
            form.value = {
                value: newAssignment.actual_value?.toString() || '',
                notes: '',
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
        resetForm();
    }
});

function resetForm() {
    form.value = {
        value: props.assignment?.actual_value?.toString() || '',
        notes: '',
    };
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatNumber(value: number | null): string {
    if (value === null || value === undefined) return '-';
    return new Intl.NumberFormat('en-PH', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(value);
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = `/api/performance/kpi-assignments/${props.assignment!.id}/progress`;

    const payload = {
        value: parseFloat(form.value.value),
        notes: form.value.notes || null,
    };

    try {
        const response = await fetch(url, {
            method: 'POST',
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
    } catch {
        errors.value = {
            general: 'An error occurred while recording progress',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Record Progress</DialogTitle>
                <DialogDescription>
                    Update the current progress for this KPI.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- KPI Info -->
                <div v-if="assignment" class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
                    <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ assignment.kpi_template?.name }}
                    </p>
                    <div class="mt-2 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                        <span>Target: {{ formatNumber(assignment.target_value) }} {{ metricUnit }}</span>
                        <span>Current: {{ formatNumber(assignment.actual_value) }} {{ metricUnit }}</span>
                    </div>
                </div>

                <!-- Progress Value -->
                <div class="space-y-2">
                    <Label for="value">
                        New Value *
                        <span class="font-normal text-slate-500">({{ metricUnit }})</span>
                    </Label>
                    <Input
                        id="value"
                        v-model="form.value"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Enter current progress value"
                        :class="{ 'border-red-500': errors.value }"
                        autofocus
                    />
                    <p v-if="errors.value" class="text-sm text-red-500">
                        {{ errors.value }}
                    </p>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Optional notes about this progress update"
                        rows="2"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">
                        {{ errors.notes }}
                    </p>
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
                        Record Progress
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
