<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface PagibigTier {
    id?: number;
    min_salary: number;
    max_salary: number | null;
    employee_rate: number;
    employer_rate: number;
}

interface PagibigTable {
    id: number;
    effective_from: string;
    description: string | null;
    max_monthly_compensation: number;
    is_active: boolean;
    tiers: PagibigTier[];
}

const props = defineProps<{
    pagibigTable: PagibigTable | null;
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const isSubmitting = ref(false);

const form = ref({
    effective_from: '',
    description: '',
    max_monthly_compensation: 5000,
    is_active: true,
    tiers: [] as PagibigTier[],
});

const isEditing = computed(() => props.pagibigTable !== null);

watch(() => props.pagibigTable, (newValue) => {
    if (newValue) {
        form.value = {
            effective_from: newValue.effective_from,
            description: newValue.description || '',
            max_monthly_compensation: newValue.max_monthly_compensation,
            is_active: newValue.is_active,
            tiers: newValue.tiers.map((t) => ({ ...t })),
        };
    } else {
        resetForm();
    }
}, { immediate: true });

function resetForm() {
    form.value = {
        effective_from: new Date().toISOString().split('T')[0],
        description: '',
        max_monthly_compensation: 5000,
        is_active: true,
        tiers: [
            { min_salary: 0, max_salary: 1500, employee_rate: 0.01, employer_rate: 0.02 },
            { min_salary: 1500.01, max_salary: null, employee_rate: 0.02, employer_rate: 0.02 },
        ],
    };
}

function addTier() {
    const lastTier = form.value.tiers[form.value.tiers.length - 1];
    form.value.tiers.push({
        min_salary: lastTier ? (lastTier.max_salary || 0) + 0.01 : 0,
        max_salary: null,
        employee_rate: 0.02,
        employer_rate: 0.02,
    });
}

function removeTier(index: number) {
    form.value.tiers.splice(index, 1);
}

async function handleSubmit() {
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/contributions/pagibig/${props.pagibigTable!.id}`
        : '/api/organization/contributions/pagibig';

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
            alert(data.message || 'An error occurred');
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
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit' : 'Add' }} Pag-IBIG Table</DialogTitle>
                <DialogDescription>Configure the Pag-IBIG contribution tiers.</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-6">
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
                        <Label for="max_compensation">Max Monthly Compensation</Label>
                        <Input id="max_compensation" v-model.number="form.max_monthly_compensation" type="number" step="0.01" min="0" />
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

                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium">Contribution Tiers</h3>
                        <Button type="button" variant="outline" size="sm" @click="addTier">Add Tier</Button>
                    </div>

                    <div v-for="(tier, index) in form.tiers" :key="index" class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                        <div class="mb-3 flex items-center justify-between">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Tier {{ index + 1 }}</span>
                            <Button type="button" variant="ghost" size="sm" class="text-red-500" @click="removeTier(index)">Remove</Button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-4">
                            <div class="space-y-1">
                                <Label class="text-xs">Min Salary</Label>
                                <Input v-model.number="tier.min_salary" type="number" step="0.01" min="0" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Max Salary</Label>
                                <Input v-model.number="tier.max_salary" type="number" step="0.01" min="0" placeholder="No max" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Employee Rate</Label>
                                <Input v-model.number="tier.employee_rate" type="number" step="0.0001" min="0" max="1" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Employer Rate</Label>
                                <Input v-model.number="tier.employer_rate" type="number" step="0.0001" min="0" max="1" />
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false" :disabled="isSubmitting">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting">{{ isSubmitting ? 'Saving...' : isEditing ? 'Update' : 'Create' }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
