<script setup lang="ts">
import GoalForm from '@/components/Goals/GoalForm.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

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

const props = defineProps<{
    availableParentGoals: ParentGoal[];
    goalTypes: EnumOption[];
    priorities: EnumOption[];
    visibilityOptions: EnumOption[];
    metricTypes: EnumOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'My Goals', href: '/my/goals' },
    { title: 'Create Goal', href: '/my/goals/create' },
];

function handleSuccess() {
    router.visit('/my/goals');
}

function handleCancel() {
    router.visit('/my/goals');
}
</script>

<template>
    <Head :title="`Create Goal - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Create New Goal
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Set up a new OKR objective or SMART goal to track your progress.
                </p>
            </div>

            <GoalForm
                :available-parent-goals="availableParentGoals"
                :goal-types="goalTypes"
                :priorities="priorities"
                :visibility-options="visibilityOptions"
                :metric-types="metricTypes"
                @success="handleSuccess"
                @cancel="handleCancel"
            />
        </div>
    </TenantLayout>
</template>
