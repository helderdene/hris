<script setup lang="ts">
import { Check, Clock, X, SkipForward } from 'lucide-vue-next';
import { computed } from 'vue';

interface Approval {
    id: number;
    approval_level: number;
    approver_type: string;
    approver_name: string;
    approver_position: string | null;
    decision: string;
    decision_label: string;
    decision_color: string;
    remarks: string | null;
    decided_at: string | null;
    is_pending: boolean;
    is_decided: boolean;
}

const props = defineProps<{
    approvals: Approval[];
}>();

const sortedApprovals = computed(() => {
    return [...props.approvals].sort((a, b) => a.approval_level - b.approval_level);
});

function getDecisionIcon(decision: string) {
    switch (decision) {
        case 'approved':
            return Check;
        case 'rejected':
            return X;
        case 'skipped':
            return SkipForward;
        default:
            return Clock;
    }
}

function getDecisionColor(decision: string): string {
    switch (decision) {
        case 'approved':
            return 'bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400';
        case 'rejected':
            return 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400';
        case 'skipped':
            return 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400';
        default:
            return 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400';
    }
}

function getLineColor(decision: string): string {
    switch (decision) {
        case 'approved':
            return 'bg-green-300 dark:bg-green-700';
        case 'rejected':
            return 'bg-red-300 dark:bg-red-700';
        default:
            return 'bg-slate-200 dark:bg-slate-700';
    }
}

function formatDateTime(dateString: string | null): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
</script>

<template>
    <div v-if="sortedApprovals.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
        No approval steps yet.
    </div>
    <div v-else class="space-y-0">
        <div
            v-for="(approval, index) in sortedApprovals"
            :key="approval.id"
            class="relative flex gap-3"
        >
            <!-- Vertical line -->
            <div class="flex flex-col items-center">
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="getDecisionColor(approval.decision)"
                >
                    <component :is="getDecisionIcon(approval.decision)" class="h-4 w-4" />
                </div>
                <div
                    v-if="index < sortedApprovals.length - 1"
                    class="w-0.5 grow"
                    :class="getLineColor(approval.decision)"
                />
            </div>

            <!-- Content -->
            <div class="pb-6">
                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">
                    Level {{ approval.approval_level }} - {{ approval.approver_name }}
                </p>
                <p v-if="approval.approver_position" class="text-xs text-slate-500 dark:text-slate-400">
                    {{ approval.approver_position }}
                </p>
                <p class="mt-1 text-xs font-medium" :class="{
                    'text-green-600 dark:text-green-400': approval.decision === 'approved',
                    'text-red-600 dark:text-red-400': approval.decision === 'rejected',
                    'text-amber-600 dark:text-amber-400': approval.decision === 'pending',
                    'text-slate-500 dark:text-slate-400': approval.decision === 'skipped',
                }">
                    {{ approval.decision_label }}
                </p>
                <p v-if="approval.decided_at" class="text-xs text-slate-500 dark:text-slate-400">
                    {{ formatDateTime(approval.decided_at) }}
                </p>
                <p v-if="approval.remarks" class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                    "{{ approval.remarks }}"
                </p>
            </div>
        </div>
    </div>
</template>
