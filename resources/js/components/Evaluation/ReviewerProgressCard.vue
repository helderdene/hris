<script setup lang="ts">

interface Reviewer {
    id: number;
    reviewer_type: string;
    reviewer_type_label: string;
    reviewer_type_color_class: string;
    status: string;
    status_label: string;
    status_color_class: string;
    reviewer_employee: {
        id: number;
        full_name: string;
        position: string | null;
        department: string | null;
    } | null;
    invited_at: string | null;
    submitted_at: string | null;
}

const props = defineProps<{
    reviewer: Reviewer;
    showEmployee?: boolean;
}>();

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString();
}

const statusIcons: Record<string, string> = {
    pending: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    in_progress: 'M11.933 12.8a1 1 0 000-1.6L6.6 7.2A1 1 0 005 8v8a1 1 0 001.6.8l5.333-4z',
    submitted: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    declined: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
};
</script>

<template>
    <div class="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-700">
        <div class="flex items-center gap-3">
            <!-- Avatar -->
            <div
                v-if="showEmployee && reviewer.reviewer_employee"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
            >
                {{ getInitials(reviewer.reviewer_employee.full_name) }}
            </div>

            <!-- Info -->
            <div>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                        :class="reviewer.reviewer_type_color_class"
                    >
                        {{ reviewer.reviewer_type_label }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium"
                        :class="reviewer.status_color_class"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                :d="statusIcons[reviewer.status] || statusIcons.pending"
                            />
                        </svg>
                        {{ reviewer.status_label }}
                    </span>
                </div>
                <p v-if="showEmployee && reviewer.reviewer_employee" class="mt-1 font-medium text-slate-900 dark:text-slate-100">
                    {{ reviewer.reviewer_employee.full_name }}
                </p>
                <p v-if="showEmployee && reviewer.reviewer_employee?.position" class="text-sm text-slate-500 dark:text-slate-400">
                    {{ reviewer.reviewer_employee.position }}
                </p>
            </div>
        </div>

        <!-- Dates -->
        <div class="text-right text-xs text-slate-500 dark:text-slate-400">
            <p v-if="reviewer.invited_at">
                Invited: {{ formatDate(reviewer.invited_at) }}
            </p>
            <p v-if="reviewer.submitted_at" class="text-emerald-600 dark:text-emerald-400">
                Submitted: {{ formatDate(reviewer.submitted_at) }}
            </p>
        </div>
    </div>
</template>
