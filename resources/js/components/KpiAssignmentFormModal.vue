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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface KpiTemplate {
    id: number;
    name: string;
    code: string;
    metric_unit: string;
    default_target: number | null;
    default_weight: number;
    is_active: boolean;
}

interface KpiAssignment {
    id: number;
    kpi_template_id: number;
    performance_cycle_participant_id: number;
    target_value: number;
    weight: number;
    notes: string | null;
}

interface PerformanceInstance {
    id: number;
    name: string;
    cycle_name: string | null;
    year: number;
}

interface Participant {
    id: number;
    employee_id: number;
    employee_name: string;
    employee_code: string | null;
}

const props = defineProps<{
    assignment: KpiAssignment | null;
    templates: KpiTemplate[];
    instances: PerformanceInstance[];
    participants: Participant[];
    selectedInstanceId: number | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    kpi_template_id: '',
    instance_id: '',
    performance_cycle_participant_id: '',
    target_value: '',
    weight: '1.00',
    notes: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);
const loadingParticipants = ref(false);
const localParticipants = ref<Participant[]>([]);

const isEditing = computed(() => !!props.assignment);

const selectedTemplate = computed(() => {
    const templateId = parseInt(form.value.kpi_template_id);
    return props.templates.find((t) => t.id === templateId) || null;
});

watch(
    () => props.assignment,
    (newAssignment) => {
        if (newAssignment) {
            form.value = {
                kpi_template_id: String(newAssignment.kpi_template_id),
                instance_id: '',
                performance_cycle_participant_id: String(newAssignment.performance_cycle_participant_id),
                target_value: String(newAssignment.target_value),
                weight: String(newAssignment.weight),
                notes: newAssignment.notes || '',
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

watch(
    () => props.selectedInstanceId,
    (newInstanceId) => {
        if (!isEditing.value && newInstanceId) {
            form.value.instance_id = String(newInstanceId);
            loadParticipantsForInstance(newInstanceId);
        }
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
    }
});

// When template changes, update default values
watch(
    () => form.value.kpi_template_id,
    (newTemplateId) => {
        if (!isEditing.value && newTemplateId) {
            const template = props.templates.find((t) => t.id === parseInt(newTemplateId));
            if (template) {
                if (template.default_target) {
                    form.value.target_value = String(template.default_target);
                }
                form.value.weight = String(template.default_weight || 1.0);
            }
        }
    },
);

// When instance changes, load participants
watch(
    () => form.value.instance_id,
    async (newInstanceId) => {
        if (newInstanceId && !isEditing.value) {
            await loadParticipantsForInstance(parseInt(newInstanceId));
        }
    },
);

async function loadParticipantsForInstance(instanceId: number) {
    loadingParticipants.value = true;
    try {
        // Reload the page with the instance filter to get participants
        router.get(
            '/performance/kpis',
            { instance_id: instanceId },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['participants'],
                onSuccess: (page) => {
                    localParticipants.value = (page.props as { participants?: Participant[] }).participants || [];
                },
            },
        );
    } finally {
        loadingParticipants.value = false;
    }
}

const availableParticipants = computed(() => {
    if (props.participants.length > 0) {
        return props.participants;
    }
    return localParticipants.value;
});

function resetForm() {
    form.value = {
        kpi_template_id: '',
        instance_id: props.selectedInstanceId ? String(props.selectedInstanceId) : '',
        performance_cycle_participant_id: '',
        target_value: '',
        weight: '1.00',
        notes: '',
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
        ? `/api/performance/kpi-assignments/${props.assignment!.id}`
        : '/api/performance/kpi-assignments';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = isEditing.value
        ? {
              target_value: parseFloat(form.value.target_value),
              weight: parseFloat(form.value.weight) || 1.0,
              notes: form.value.notes || null,
          }
        : {
              kpi_template_id: parseInt(form.value.kpi_template_id),
              performance_cycle_participant_id: parseInt(form.value.performance_cycle_participant_id),
              target_value: parseFloat(form.value.target_value),
              weight: parseFloat(form.value.weight) || 1.0,
              notes: form.value.notes || null,
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
    } catch {
        errors.value = {
            general: 'An error occurred while saving the KPI assignment',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit KPI Assignment' : 'Assign KPI' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the KPI assignment details below.'
                            : 'Assign a KPI template to an employee in a performance cycle.'
                    }}
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

                <!-- KPI Template (only for new assignments) -->
                <div v-if="!isEditing" class="space-y-2">
                    <Label for="kpi_template_id">KPI Template *</Label>
                    <Select v-model="form.kpi_template_id">
                        <SelectTrigger :class="{ 'border-red-500': errors.kpi_template_id }">
                            <SelectValue placeholder="Select KPI template" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="template in templates"
                                :key="template.id"
                                :value="String(template.id)"
                            >
                                {{ template.name }} ({{ template.metric_unit }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.kpi_template_id" class="text-sm text-red-500">
                        {{ errors.kpi_template_id }}
                    </p>
                </div>

                <!-- Performance Cycle Instance (only for new assignments) -->
                <div v-if="!isEditing" class="space-y-2">
                    <Label for="instance_id">Performance Cycle Instance *</Label>
                    <Select v-model="form.instance_id">
                        <SelectTrigger>
                            <SelectValue placeholder="Select cycle instance" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="instance in instances"
                                :key="instance.id"
                                :value="String(instance.id)"
                            >
                                {{ instance.name }} ({{ instance.year }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <!-- Participant (only for new assignments) -->
                <div v-if="!isEditing" class="space-y-2">
                    <Label for="performance_cycle_participant_id">Employee *</Label>
                    <Select
                        v-model="form.performance_cycle_participant_id"
                        :disabled="!form.instance_id || loadingParticipants"
                    >
                        <SelectTrigger :class="{ 'border-red-500': errors.performance_cycle_participant_id }">
                            <SelectValue :placeholder="loadingParticipants ? 'Loading...' : 'Select employee'" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="participant in availableParticipants"
                                :key="participant.id"
                                :value="String(participant.id)"
                            >
                                {{ participant.employee_name }}
                                <span v-if="participant.employee_code" class="text-slate-500">
                                    ({{ participant.employee_code }})
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="!form.instance_id" class="text-xs text-slate-500 dark:text-slate-400">
                        Select a cycle instance first to load participants
                    </p>
                    <p v-if="errors.performance_cycle_participant_id" class="text-sm text-red-500">
                        {{ errors.performance_cycle_participant_id }}
                    </p>
                </div>

                <!-- Target Value -->
                <div class="space-y-2">
                    <Label for="target_value">
                        Target Value *
                        <span v-if="selectedTemplate" class="font-normal text-slate-500">
                            ({{ selectedTemplate.metric_unit }})
                        </span>
                    </Label>
                    <Input
                        id="target_value"
                        v-model="form.target_value"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="e.g., 100000"
                        :class="{ 'border-red-500': errors.target_value }"
                    />
                    <p v-if="errors.target_value" class="text-sm text-red-500">
                        {{ errors.target_value }}
                    </p>
                </div>

                <!-- Weight -->
                <div class="space-y-2">
                    <Label for="weight">Weight</Label>
                    <Input
                        id="weight"
                        v-model="form.weight"
                        type="number"
                        step="0.01"
                        min="0"
                        max="10"
                        placeholder="e.g., 1.0"
                        :class="{ 'border-red-500': errors.weight }"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Weight for calculating weighted average achievement (default: 1.0)
                    </p>
                    <p v-if="errors.weight" class="text-sm text-red-500">
                        {{ errors.weight }}
                    </p>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Optional notes about this assignment"
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
                        {{ isEditing ? 'Update Assignment' : 'Assign KPI' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
