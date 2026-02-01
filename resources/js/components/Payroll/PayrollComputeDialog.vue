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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { AlertCircle, Calculator, Loader2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface PayrollPeriod {
    id: number;
    name: string;
    date_range: string;
    status: string;
}

const props = defineProps<{
    period: PayrollPeriod;
}>();

const open = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{
    success: [];
}>();

const isComputing = ref(false);
const forceRecompute = ref(false);
const error = ref<string | null>(null);
const progress = ref<{ current: number; total: number } | null>(null);

// Reset state when dialog opens
watch(open, (newValue) => {
    if (newValue) {
        forceRecompute.value = false;
        error.value = null;
        progress.value = null;
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleCompute() {
    isComputing.value = true;
    error.value = null;
    progress.value = null;

    try {
        const response = await fetch(`/api/payroll/periods/${props.period.id}/compute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                force_recompute: forceRecompute.value,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else {
            error.value = data.message || 'Failed to compute payroll';
        }
    } catch (e) {
        error.value = 'An error occurred while computing payroll';
    } finally {
        isComputing.value = false;
    }
}

function handleCancel() {
    if (!isComputing.value) {
        open.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <Calculator class="h-5 w-5" />
                    Compute Payroll
                </DialogTitle>
                <DialogDescription>
                    Run payroll computation for {{ period.name }}
                    <span class="text-slate-500">({{ period.date_range }})</span>
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-4">
                <!-- Force Recompute Option -->
                <div class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                    <Checkbox
                        id="force-recompute"
                        v-model:checked="forceRecompute"
                        :disabled="isComputing"
                        class="mt-1"
                    />
                    <div class="space-y-0.5">
                        <Label for="force-recompute" class="cursor-pointer text-base">Force Recompute</Label>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Recalculate all entries, even those already computed
                        </p>
                    </div>
                </div>

                <!-- Warning for force recompute -->
                <div
                    v-if="forceRecompute"
                    class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
                >
                    <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500" />
                    <div class="text-sm text-amber-700 dark:text-amber-400">
                        <p class="font-medium">Warning</p>
                        <p class="mt-1">
                            This will overwrite all existing computed values. Approved entries will not be affected.
                        </p>
                    </div>
                </div>

                <!-- Error Message -->
                <div
                    v-if="error"
                    class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                >
                    <AlertCircle class="mt-0.5 h-5 w-5 flex-shrink-0 text-red-500" />
                    <div class="text-sm text-red-700 dark:text-red-400">
                        {{ error }}
                    </div>
                </div>

                <!-- Progress -->
                <div
                    v-if="progress"
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-slate-600 dark:text-slate-400">Processing...</span>
                        <span class="font-medium text-slate-900 dark:text-slate-100">
                            {{ progress.current }} / {{ progress.total }}
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                        <div
                            class="h-full bg-blue-500 transition-all duration-300"
                            :style="{ width: `${(progress.current / progress.total) * 100}%` }"
                        ></div>
                    </div>
                </div>

                <!-- Info -->
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    <p>This will:</p>
                    <ul class="mt-2 list-inside list-disc space-y-1">
                        <li>Calculate basic pay based on employee pay type</li>
                        <li>Add overtime, night differential, and holiday pay</li>
                        <li>Compute SSS, PhilHealth, Pag-IBIG deductions</li>
                        <li>Calculate withholding tax</li>
                        <li>Generate payslip line items</li>
                    </ul>
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleCancel" :disabled="isComputing">
                    Cancel
                </Button>
                <Button @click="handleCompute" :disabled="isComputing">
                    <Loader2 v-if="isComputing" class="mr-2 h-4 w-4 animate-spin" />
                    <Calculator v-else class="mr-2 h-4 w-4" />
                    {{ isComputing ? 'Computing...' : 'Compute Payroll' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
