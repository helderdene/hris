<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import GoalForm from '@/components/Goals/GoalForm.vue';
import { computed } from 'vue';

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface ParentGoal {
    id: number;
    title: string;
    goal_type: string;
    owner_name: string;
}

interface Goal {
    id?: number;
    goal_type: string;
    title: string;
    description: string;
    category: string;
    visibility: string;
    priority: string;
    parent_goal_id: number | null;
    start_date: string;
    due_date: string;
}

const props = withDefaults(
    defineProps<{
        open: boolean;
        goal?: Goal;
        availableParentGoals?: ParentGoal[];
        goalTypes: EnumOption[];
        priorities?: EnumOption[];
        visibilityOptions?: EnumOption[];
        metricTypes?: EnumOption[];
    }>(),
    {
        availableParentGoals: () => [],
        priorities: () => [
            { value: 'low', label: 'Low' },
            { value: 'medium', label: 'Medium' },
            { value: 'high', label: 'High' },
            { value: 'critical', label: 'Critical' },
        ],
        visibilityOptions: () => [
            { value: 'private', label: 'Private' },
            { value: 'team', label: 'Team' },
            { value: 'organization', label: 'Organization' },
        ],
        metricTypes: () => [
            { value: 'number', label: 'Number' },
            { value: 'percentage', label: 'Percentage' },
            { value: 'currency', label: 'Currency' },
            { value: 'boolean', label: 'Yes/No' },
        ],
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
    success: [];
}>();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const isEditing = computed(() => !!props.goal?.id);

function handleSuccess() {
    isOpen.value = false;
    emit('success');
}

function handleCancel() {
    isOpen.value = false;
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Goal' : 'Create New Goal' }}
                </DialogTitle>
            </DialogHeader>

            <GoalForm
                :goal="goal"
                :available-parent-goals="availableParentGoals"
                :goal-types="goalTypes"
                :priorities="priorities"
                :visibility-options="visibilityOptions"
                :metric-types="metricTypes"
                :is-editing="isEditing"
                @success="handleSuccess"
                @cancel="handleCancel"
            />
        </DialogContent>
    </Dialog>
</template>
