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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    availableYears: number[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const selectedYear = ref('');
const processing = ref(false);
const result = ref<{
    carried_over: number;
    forfeited: number;
    initialized: number;
} | null>(null);
const error = ref('');

const yearsForProcessing = computed(() => {
    const currentYear = new Date().getFullYear();
    // Can process current year and previous years
    return props.availableYears.filter((y) => y <= currentYear);
});

watch(open, (newValue) => {
    if (!newValue) {
        resetForm();
    }
});

function resetForm() {
    selectedYear.value = '';
    result.value = null;
    error.value = '';
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleProcess() {
    if (!selectedYear.value) {
        error.value = 'Please select a year to process.';
        return;
    }

    const confirmed = confirm(
        `Are you sure you want to process year-end for ${selectedYear.value}?\n\n` +
            `This will:\n` +
            `- Calculate unused balances from ${selectedYear.value}\n` +
            `- Apply carry-over rules\n` +
            `- Forfeit balances where carry-over is not allowed\n` +
            `- Initialize balances for ${parseInt(selectedYear.value) + 1}`,
    );

    if (!confirmed) {
        return;
    }

    processing.value = true;
    error.value = '';
    result.value = null;

    try {
        const response = await fetch(
            '/api/organization/leave-balances/process-year-end',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({ year: parseInt(selectedYear.value) }),
            },
        );

        const data = await response.json();

        if (response.ok) {
            result.value = {
                carried_over: data.carried_over,
                forfeited: data.forfeited,
                initialized: data.initialized,
            };
        } else {
            error.value = data.message || 'Failed to process year-end.';
        }
    } catch {
        error.value = 'An error occurred. Please try again.';
    } finally {
        processing.value = false;
    }
}

function handleClose() {
    if (result.value) {
        emit('success');
    }
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-[500px]">
            <DialogHeader>
                <DialogTitle>Year-End Processing</DialogTitle>
                <DialogDescription>
                    Process year-end carry-over and forfeiture of leave
                    balances.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Error alert -->
                <div
                    v-if="error"
                    class="rounded-lg bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ error }}
                </div>

                <!-- Success result -->
                <div
                    v-if="result"
                    class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20"
                >
                    <div
                        class="font-medium text-green-700 dark:text-green-300"
                    >
                        Year-End Processing Complete
                    </div>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-green-600 dark:text-green-400"
                                >Balances carried over:</span
                            >
                            <span
                                class="font-semibold text-green-700 dark:text-green-300"
                            >
                                {{ result.carried_over }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-600 dark:text-green-400"
                                >Balances forfeited:</span
                            >
                            <span
                                class="font-semibold text-green-700 dark:text-green-300"
                            >
                                {{ result.forfeited }}
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-green-600 dark:text-green-400"
                                >New year balances initialized:</span
                            >
                            <span
                                class="font-semibold text-green-700 dark:text-green-300"
                            >
                                {{ result.initialized }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Year selection -->
                <div v-if="!result" class="space-y-2">
                    <label
                        class="text-sm font-medium text-slate-900 dark:text-slate-100"
                    >
                        Year to Process
                    </label>
                    <Select v-model="selectedYear">
                        <SelectTrigger>
                            <SelectValue placeholder="Select year" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="year in yearsForProcessing"
                                :key="year"
                                :value="String(year)"
                            >
                                {{ year }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        This will process balances from the selected year and
                        initialize the following year.
                    </p>
                </div>

                <!-- Warning -->
                <div
                    v-if="!result"
                    class="rounded-lg bg-amber-50 p-3 dark:bg-amber-900/20"
                >
                    <div class="flex items-start gap-2">
                        <svg
                            class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-500"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                            />
                        </svg>
                        <div class="text-sm text-amber-700 dark:text-amber-300">
                            <p class="font-medium">Important</p>
                            <p class="mt-1">
                                This operation will mark processed balances and
                                create new year records. Make sure you have
                                reviewed all leave requests before processing.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <DialogFooter>
                <Button
                    v-if="result"
                    type="button"
                    @click="handleClose"
                >
                    Done
                </Button>
                <template v-else>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="processing"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        @click="handleProcess"
                        :disabled="processing || !selectedYear"
                    >
                        {{
                            processing
                                ? 'Processing...'
                                : 'Process Year-End'
                        }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
