<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import ProficiencyLevelBadge from '@/components/ProficiencyLevelBadge.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type {
    Competency,
    Position,
    PositionCompetency,
    ProficiencyLevel,
    JobLevelOption,
} from '@/types/competency';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    assignment: PositionCompetency | null;
    positions: Position[];
    competencies: Competency[];
    proficiencyLevels: ProficiencyLevel[];
    jobLevels: JobLevelOption[];
    initialPositionId?: number | null;
    initialJobLevel?: string | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    position_id: '',
    competency_id: '',
    job_level: '',
    required_proficiency_level: '3',
    is_mandatory: true,
    weight: '1.00',
    notes: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.assignment);

const positionOptions = computed(() => {
    return [
        { value: '', label: 'Select position' },
        ...props.positions.map((pos) => ({
            value: pos.id.toString(),
            label: `${pos.title} (${pos.code})`,
        })),
    ];
});

const competencyOptions = computed(() => {
    return [
        { value: '', label: 'Select competency' },
        ...props.competencies.map((comp) => ({
            value: comp.id.toString(),
            label: `${comp.name} (${comp.code})`,
        })),
    ];
});

const jobLevelOptions = computed(() => {
    return [
        { value: '', label: 'Select job level' },
        ...props.jobLevels.map((level) => ({
            value: level.value,
            label: level.label,
        })),
    ];
});

const proficiencyLevelOptions = computed(() => {
    return props.proficiencyLevels.map((level) => ({
        value: level.level.toString(),
        label: `${level.level} - ${level.name}`,
    }));
});

const selectedProficiencyLevel = computed(() => {
    return props.proficiencyLevels.find(
        (l) => l.level.toString() === form.value.required_proficiency_level,
    );
});

watch(
    () => props.assignment,
    (newAssignment) => {
        if (newAssignment) {
            form.value = {
                position_id: newAssignment.position_id.toString(),
                competency_id: newAssignment.competency_id.toString(),
                job_level: newAssignment.job_level,
                required_proficiency_level:
                    newAssignment.required_proficiency_level.toString(),
                is_mandatory: newAssignment.is_mandatory,
                weight: newAssignment.weight,
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
    () => open.value,
    (isOpen) => {
        if (isOpen && !props.assignment) {
            // Pre-fill with initial values if provided
            if (props.initialPositionId) {
                form.value.position_id = props.initialPositionId.toString();
            }
            if (props.initialJobLevel) {
                form.value.job_level = props.initialJobLevel;
            }
        }
        if (!isOpen) {
            errors.value = {};
        }
    },
);

function resetForm() {
    form.value = {
        position_id: props.initialPositionId?.toString() || '',
        competency_id: '',
        job_level: props.initialJobLevel || '',
        required_proficiency_level: '3',
        is_mandatory: true,
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
        ? `/api/performance/position-competencies/${props.assignment!.id}`
        : '/api/performance/position-competencies';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        position_id: parseInt(form.value.position_id),
        competency_id: parseInt(form.value.competency_id),
        job_level: form.value.job_level,
        required_proficiency_level: parseInt(form.value.required_proficiency_level),
        is_mandatory: form.value.is_mandatory,
        weight: parseFloat(form.value.weight),
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
    } catch (error) {
        errors.value = {
            general: 'An error occurred while saving the assignment',
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
                    {{ isEditing ? 'Edit Assignment' : 'Add Competency Assignment' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the competency assignment details.'
                            : 'Assign a competency to a position with proficiency requirements.'
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

                <!-- Position -->
                <div class="space-y-2">
                    <Label for="position_id">Position *</Label>
                    <EnumSelect
                        id="position_id"
                        v-model="form.position_id"
                        :options="positionOptions"
                        placeholder="Select position"
                        :disabled="isEditing"
                    />
                    <p v-if="errors.position_id" class="text-sm text-red-500">
                        {{ errors.position_id }}
                    </p>
                </div>

                <!-- Job Level -->
                <div class="space-y-2">
                    <Label for="job_level">Job Level *</Label>
                    <EnumSelect
                        id="job_level"
                        v-model="form.job_level"
                        :options="jobLevelOptions"
                        placeholder="Select job level"
                        :disabled="isEditing"
                    />
                    <p v-if="errors.job_level" class="text-sm text-red-500">
                        {{ errors.job_level }}
                    </p>
                </div>

                <!-- Competency -->
                <div class="space-y-2">
                    <Label for="competency_id">Competency *</Label>
                    <EnumSelect
                        id="competency_id"
                        v-model="form.competency_id"
                        :options="competencyOptions"
                        placeholder="Select competency"
                        :disabled="isEditing"
                    />
                    <p v-if="errors.competency_id" class="text-sm text-red-500">
                        {{ errors.competency_id }}
                    </p>
                </div>

                <!-- Required Proficiency Level -->
                <div class="space-y-2">
                    <Label for="required_proficiency_level">Required Proficiency Level *</Label>
                    <EnumSelect
                        id="required_proficiency_level"
                        v-model="form.required_proficiency_level"
                        :options="proficiencyLevelOptions"
                        placeholder="Select level"
                    />
                    <p
                        v-if="errors.required_proficiency_level"
                        class="text-sm text-red-500"
                    >
                        {{ errors.required_proficiency_level }}
                    </p>
                    <div
                        v-if="selectedProficiencyLevel"
                        class="rounded-md bg-slate-50 p-3 dark:bg-slate-800"
                    >
                        <div class="flex items-center gap-2">
                            <ProficiencyLevelBadge
                                :level="selectedProficiencyLevel.level"
                                :name="selectedProficiencyLevel.name"
                                show-level
                            />
                        </div>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                            {{ selectedProficiencyLevel.description }}
                        </p>
                    </div>
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
                        placeholder="1.00"
                        :class="{ 'border-red-500': errors.weight }"
                    />
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Importance weighting for evaluation calculations (0-10).
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
                        placeholder="Optional notes about this requirement..."
                        rows="2"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">
                        {{ errors.notes }}
                    </p>
                </div>

                <!-- Mandatory Status -->
                <div class="flex items-center justify-between">
                    <div class="space-y-0.5">
                        <Label>Mandatory</Label>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Whether this competency is required for this position/level.
                        </p>
                    </div>
                    <Switch
                        :checked="form.is_mandatory"
                        @update:checked="form.is_mandatory = $event"
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
                        {{ isEditing ? 'Update Assignment' : 'Create Assignment' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
