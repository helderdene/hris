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
import { computed, ref, watch } from 'vue';

interface PhilhealthTable {
    id: number;
    effective_from: string;
    description: string | null;
    contribution_rate: number;
    employee_share_rate: number;
    employer_share_rate: number;
    salary_floor: number;
    salary_ceiling: number;
    min_contribution: number;
    max_contribution: number;
    is_active: boolean;
}

const props = defineProps<{
    philhealthTable: PhilhealthTable | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);
const errors = ref<Record<string, string[]>>({});

const form = ref({
    effective_from: '',
    description: '',
    contribution_rate: 0.05,
    employee_share_rate: 0.5,
    employer_share_rate: 0.5,
    salary_floor: 10000,
    salary_ceiling: 100000,
    min_contribution: 500,
    max_contribution: 5000,
    is_active: true,
});

const isEditing = computed(() => props.philhealthTable !== null);

watch(
    () => props.philhealthTable,
    (newValue) => {
        if (newValue) {
            form.value = {
                effective_from: newValue.effective_from,
                description: newValue.description || '',
                contribution_rate: newValue.contribution_rate,
                employee_share_rate: newValue.employee_share_rate,
                employer_share_rate: newValue.employer_share_rate,
                salary_floor: newValue.salary_floor,
                salary_ceiling: newValue.salary_ceiling,
                min_contribution: newValue.min_contribution,
                max_contribution: newValue.max_contribution,
                is_active: newValue.is_active,
            };
        } else {
            resetForm();
        }
    },
    { immediate: true },
);

function resetForm() {
    form.value = {
        effective_from: new Date().toISOString().split('T')[0],
        description: '',
        contribution_rate: 0.05,
        employee_share_rate: 0.5,
        employer_share_rate: 0.5,
        salary_floor: 10000,
        salary_ceiling: 100000,
        min_contribution: 500,
        max_contribution: 5000,
        is_active: true,
    };
}

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};

    const url = isEditing.value
        ? `/api/organization/contributions/philhealth/${props.philhealthTable!.id}`
        : '/api/organization/contributions/philhealth';

    const method = isEditing.value ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        });

        if (response.ok) {
            open.value = false;
            emit('success');
        } else {
            const data = await response.json();
            if (data.errors) {
                errors.value = data.errors;
            } else {
                alert(data.message || 'An error occurred');
            }
        }
    } catch (error) {
        alert('An error occurred while saving');
    } finally {
        isSubmitting.value = false;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit' : 'Add' }} PhilHealth Table</DialogTitle>
                <DialogDescription>
                    Configure the PhilHealth contribution rates and limits.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="effective_from">Effective Date</Label>
                        <Input id="effective_from" v-model="form.effective_from" type="date" required />
                    </div>
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Input id="description" v-model="form.description" type="text" />
                    </div>
                    <div class="space-y-2">
                        <Label for="contribution_rate">Contribution Rate</Label>
                        <Input id="contribution_rate" v-model.number="form.contribution_rate" type="number" step="0.0001" min="0" max="1" />
                    </div>
                    <div class="space-y-2">
                        <Label for="salary_floor">Salary Floor</Label>
                        <Input id="salary_floor" v-model.number="form.salary_floor" type="number" step="0.01" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label for="salary_ceiling">Salary Ceiling</Label>
                        <Input id="salary_ceiling" v-model.number="form.salary_ceiling" type="number" step="0.01" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label for="min_contribution">Min Contribution</Label>
                        <Input id="min_contribution" v-model.number="form.min_contribution" type="number" step="0.01" min="0" />
                    </div>
                    <div class="space-y-2">
                        <Label for="max_contribution">Max Contribution</Label>
                        <Input id="max_contribution" v-model.number="form.max_contribution" type="number" step="0.01" min="0" />
                    </div>
                    <div class="flex items-center space-x-2">
                        <input
                            id="is_active"
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <Label for="is_active">Active</Label>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false" :disabled="isSubmitting">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : isEditing ? 'Update' : 'Create' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
