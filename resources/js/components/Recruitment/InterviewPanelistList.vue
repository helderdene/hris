<script setup lang="ts">
interface Panelist {
    id: number;
    employee: { id: number; full_name: string };
    is_lead: boolean;
    invitation_sent_at: string | null;
    feedback: string | null;
    rating: number | null;
    feedback_submitted_at: string | null;
}

defineProps<{
    panelists: Panelist[];
}>();
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Interview Panel</h2>

        <div v-if="panelists.length === 0" class="text-sm text-slate-500 dark:text-slate-400">
            No panelists assigned.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="panelist in panelists"
                :key="panelist.id"
                class="flex items-start justify-between rounded-lg border border-slate-100 p-3 dark:border-slate-800"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ panelist.employee.full_name }}
                        </span>
                        <span v-if="panelist.is_lead" class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                            Lead
                        </span>
                    </div>
                    <div class="mt-1 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span v-if="panelist.invitation_sent_at" class="text-green-600 dark:text-green-400">Invited</span>
                        <span v-else class="text-slate-400">Not invited</span>
                        <span v-if="panelist.feedback_submitted_at" class="text-green-600 dark:text-green-400">Feedback submitted</span>
                        <span v-else class="text-slate-400">Awaiting feedback</span>
                    </div>
                    <div v-if="panelist.feedback" class="mt-2">
                        <div class="flex items-center gap-1">
                            <span v-for="star in 5" :key="star" class="text-sm" :class="star <= (panelist.rating ?? 0) ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600'">&#9733;</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ panelist.feedback }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
