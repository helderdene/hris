<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

interface Employee {
    id: number;
    full_name: string;
    employee_number: string | null;
    department?: string | null;
    position?: string | null;
}

interface Enrollment {
    id: number;
    employee_id: number;
    status: string;
    status_label: string;
    enrolled_at: string;
    attended_at: string | null;
    can_cancel: boolean;
    can_mark_attendance: boolean;
    employee?: Employee;
}

const props = defineProps<{
    enrollments: Enrollment[];
    sessionStatus: string;
}>();

const emit = defineEmits<{
    cancel: [enrollment: Enrollment];
    markAttended: [enrollment: Enrollment];
    markNoShow: [enrollment: Enrollment];
}>();

const showAttendanceActions = computed(() =>
    ['scheduled', 'in_progress', 'completed'].includes(props.sessionStatus)
);

function getStatusClass(status: string): string {
    const classMap: Record<string, string> = {
        pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        attended: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        no_show: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        cancelled: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        rejected: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };

    return classMap[status] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300';
}

function formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-slate-200 dark:border-slate-700">
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Employee</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Department</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Enrolled</th>
                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-slate-500">Status</th>
                    <th class="px-4 py-2 text-right text-xs font-medium uppercase text-slate-500">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <tr v-for="enrollment in enrollments" :key="enrollment.id">
                    <td class="px-4 py-3">
                        <div class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ enrollment.employee?.full_name }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ enrollment.employee?.employee_number }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                        <div>{{ enrollment.employee?.department || '-' }}</div>
                        <div class="text-xs text-slate-400">{{ enrollment.employee?.position }}</div>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                        {{ formatDate(enrollment.enrolled_at) }}
                    </td>
                    <td class="px-4 py-3">
                        <span
                            :class="[
                                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                getStatusClass(enrollment.status),
                            ]"
                        >
                            {{ enrollment.status_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-1">
                            <template v-if="showAttendanceActions && enrollment.can_mark_attendance">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-green-600 hover:text-green-700"
                                    @click="emit('markAttended', enrollment)"
                                >
                                    Attended
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                    @click="emit('markNoShow', enrollment)"
                                >
                                    No-Show
                                </Button>
                            </template>
                            <Button
                                v-if="enrollment.can_cancel"
                                variant="ghost"
                                size="sm"
                                class="text-slate-600 hover:text-slate-700"
                                @click="emit('cancel', enrollment)"
                            >
                                Cancel
                            </Button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
