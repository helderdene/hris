<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface PerformanceCycle {
    id: number;
    name: string;
    code: string;
    cycle_type: string;
    is_recurring: boolean;
    instances_per_year: number | null;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    cycles: PerformanceCycle[];
    availableYears: number[];
    defaultYear: number;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    performance_cycle_id: '',
    year: '',
    overwrite: false,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);
const result = ref<{ message: string } | null>(null);

const recurringCycles = computed(() => {
    return (props.cycles ?? []).filter((c) => c.is_recurring);
});

const cycleOptions = computed<EnumOption[]>(() => {
    return recurringCycles.value.map((cycle) => ({
        value: String(cycle.id),
        label: `${cycle.name} (${cycle.instances_per_year} instance${cycle.instances_per_year === 1 ? '' : 's'}/year)`,
    }));
});

const yearOptions = computed<EnumOption[]>(() => {
    return (props.availableYears ?? []).map((year) => ({
        value: String(year),
        label: String(year),
    }));
});

const selectedCycle = computed(() => {
    if (!form.value.performance_cycle_id) return null;
    return (props.cycles ?? []).find(
        (c) => c.id === Number(form.value.performance_cycle_id),
    );
});

watch(open, (isOpen) => {
    if (isOpen) {
        // Set defaults when opening
        form.value = {
            performance_cycle_id:
                recurringCycles.value.length > 0
                    ? String(recurringCycles.value[0].id)
                    : '',
            year: String(props.defaultYear),
            overwrite: false,
        };
        result.value = null;
        errors.value = {};
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleGenerate() {
    errors.value = {};
    isSubmitting.value = true;
    result.value = null;

    const payload = {
        performance_cycle_id: Number(form.value.performance_cycle_id),
        year: Number(form.value.year),
        overwrite: form.value.overwrite,
    };

    try {
        const response = await fetch(
            '/api/organization/performance-cycle-instances/generate',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            },
        );

        const data = await response.json();

        if (response.ok) {
            result.value = {
                message: data.message,
            };
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
            general: 'An error occurred while generating instances',
        };
    } finally {
        isSubmitting.value = false;
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
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{
                        result
                            ? 'Instances Generated'
                            : 'Generate Cycle Instances'
                    }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="result">
                        {{ result.message }}
                    </template>
                    <template v-else>
                        Automatically generate performance cycle instances for a
                        cycle and year.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <template v-if="!result">
                <div class="space-y-4">
                    <!-- General Error -->
                    <div
                        v-if="errors.general"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                    >
                        {{ errors.general }}
                    </div>

                    <!-- No recurring cycles warning -->
                    <div
                        v-if="recurringCycles.length === 0"
                        class="rounded-md bg-amber-50 p-3 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                    >
                        No recurring performance cycles available. Only Annual
                        and Mid-Year cycles support automatic instance
                        generation.
                    </div>

                    <template v-else>
                        <!-- Cycle Selection -->
                        <div class="space-y-2">
                            <Label for="performance_cycle_id"
                                >Performance Cycle *</Label
                            >
                            <EnumSelect
                                id="performance_cycle_id"
                                v-model="form.performance_cycle_id"
                                :options="cycleOptions"
                                placeholder="Select cycle"
                            />
                            <p
                                v-if="errors.performance_cycle_id"
                                class="text-sm text-red-500"
                            >
                                {{ errors.performance_cycle_id }}
                            </p>
                        </div>

                        <!-- Year Selection -->
                        <div class="space-y-2">
                            <Label for="year">Year *</Label>
                            <EnumSelect
                                id="year"
                                v-model="form.year"
                                :options="yearOptions"
                                placeholder="Select year"
                            />
                            <p v-if="errors.year" class="text-sm text-red-500">
                                {{ errors.year }}
                            </p>
                        </div>

                        <!-- Info about what will be generated -->
                        <div
                            v-if="selectedCycle"
                            class="rounded-md bg-blue-50 p-3 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                        >
                            This will generate
                            <strong
                                >{{ selectedCycle.instances_per_year }}
                                instance{{
                                    selectedCycle.instances_per_year === 1
                                        ? ''
                                        : 's'
                                }}</strong
                            >
                            for {{ form.year }} based on the
                            {{ selectedCycle.name }} cycle.
                        </div>

                        <!-- Overwrite Checkbox -->
                        <div class="flex items-start gap-3">
                            <input
                                id="overwrite"
                                type="checkbox"
                                v-model="form.overwrite"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800"
                            />
                            <div class="grid gap-1.5 leading-none">
                                <Label
                                    for="overwrite"
                                    class="cursor-pointer text-sm font-medium leading-none"
                                >
                                    Overwrite existing draft instances
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    Delete draft instances before generating new
                                    ones.
                                </p>
                            </div>
                        </div>
                    </template>
                </div>
            </template>

            <DialogFooter class="gap-2 sm:gap-0">
                <template v-if="result">
                    <Button @click="handleClose">Done</Button>
                </template>
                <template v-else>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        @click="handleGenerate"
                        :disabled="isSubmitting || recurringCycles.length === 0"
                    >
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
                        {{
                            isSubmitting
                                ? 'Generating...'
                                : 'Generate Instances'
                        }}
                    </Button>
                </template>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
