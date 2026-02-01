<script setup lang="ts">
import GoalCard from '@/components/Goals/GoalCard.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Goal {
    id: number;
    goal_type: string;
    goal_type_label: string;
    title: string;
    description?: string;
    category: string | null;
    priority: string;
    priority_label: string;
    status: string;
    status_label: string;
    approval_status: string;
    approval_status_label: string;
    start_date: string;
    due_date: string;
    progress_percentage: number;
    is_overdue: boolean;
    days_remaining: number;
    key_results_count?: number;
    milestones_count?: number;
    employee: {
        id: number;
        full_name: string;
    };
    submitted_at?: string;
}

const props = defineProps<{
    pendingGoals: Goal[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Team Goals', href: '/manager/team-goals' },
    { title: 'Approvals', href: '/manager/team-goals/approvals' },
];

// Approval modal state
const isApprovalModalOpen = ref(false);
const isRejectionModalOpen = ref(false);
const selectedGoal = ref<Goal | null>(null);

const approvalForm = useForm({
    feedback: '',
});

const rejectionForm = useForm({
    feedback: '',
});

function openApprovalModal(goal: Goal) {
    selectedGoal.value = goal;
    approvalForm.reset();
    isApprovalModalOpen.value = true;
}

function openRejectionModal(goal: Goal) {
    selectedGoal.value = goal;
    rejectionForm.reset();
    isRejectionModalOpen.value = true;
}

function handleApprove() {
    if (!selectedGoal.value) return;

    approvalForm.post(`/api/performance/goals/${selectedGoal.value.id}/approve`, {
        onSuccess: () => {
            isApprovalModalOpen.value = false;
            selectedGoal.value = null;
            router.reload({ only: ['pendingGoals'] });
        },
    });
}

function handleReject() {
    if (!selectedGoal.value) return;

    rejectionForm.post(`/api/performance/goals/${selectedGoal.value.id}/reject`, {
        onSuccess: () => {
            isRejectionModalOpen.value = false;
            selectedGoal.value = null;
            router.reload({ only: ['pendingGoals'] });
        },
    });
}

function viewGoal(goal: Goal) {
    router.visit(`/manager/team-goals/${goal.id}`);
}
</script>

<template>
    <Head :title="`Goal Approvals - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Pending Approvals
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Review and approve or reject goal submissions from your team.
                </p>
            </div>

            <!-- Pending Goals List -->
            <div class="flex flex-col gap-4">
                <div
                    v-for="goal in pendingGoals"
                    :key="goal.id"
                    class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <div>
                            <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ goal.employee.full_name }}
                            </span>
                            <span v-if="goal.submitted_at" class="ml-2 text-xs text-slate-500 dark:text-slate-400">
                                Submitted {{ new Date(goal.submitted_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) }}
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                class="text-red-600 hover:text-red-700 dark:text-red-400"
                                @click="openRejectionModal(goal)"
                            >
                                Reject
                            </Button>
                            <Button
                                size="sm"
                                class="bg-green-600 hover:bg-green-700"
                                @click="openApprovalModal(goal)"
                            >
                                Approve
                            </Button>
                        </div>
                    </div>

                    <GoalCard
                        :goal="goal"
                        @click="viewGoal(goal)"
                    />

                    <div v-if="goal.description" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                        {{ goal.description }}
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="pendingGoals.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-green-500 dark:text-green-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        All caught up!
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        There are no goals pending your approval.
                    </p>
                </div>
            </div>
        </div>

        <!-- Approval Modal -->
        <Dialog v-model:open="isApprovalModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Approve Goal</DialogTitle>
                    <DialogDescription v-if="selectedGoal">
                        Approve "{{ selectedGoal.title }}" by {{ selectedGoal.employee.full_name }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleApprove" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            Feedback (optional)
                        </label>
                        <Textarea
                            v-model="approvalForm.feedback"
                            placeholder="Add any feedback or suggestions..."
                            rows="3"
                            class="mt-1"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isApprovalModalOpen = false">
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="bg-green-600 hover:bg-green-700"
                            :disabled="approvalForm.processing"
                        >
                            {{ approvalForm.processing ? 'Approving...' : 'Approve Goal' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Rejection Modal -->
        <Dialog v-model:open="isRejectionModalOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Reject Goal</DialogTitle>
                    <DialogDescription v-if="selectedGoal">
                        Reject "{{ selectedGoal.title }}" by {{ selectedGoal.employee.full_name }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleReject" class="space-y-4">
                    <div>
                        <label class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            Reason for rejection
                        </label>
                        <Textarea
                            v-model="rejectionForm.feedback"
                            placeholder="Please explain why this goal is being rejected..."
                            rows="3"
                            class="mt-1"
                            :class="{ 'border-red-500': rejectionForm.errors.feedback }"
                        />
                        <p v-if="rejectionForm.errors.feedback" class="mt-1 text-sm text-red-600">
                            {{ rejectionForm.errors.feedback }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="isRejectionModalOpen = false">
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="rejectionForm.processing"
                        >
                            {{ rejectionForm.processing ? 'Rejecting...' : 'Reject Goal' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
