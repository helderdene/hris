<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import LeaveApplicationFormModal from '@/components/LeaveApplicationFormModal.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Calendar, FileText, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface LeaveType {
    id: number;
    name: string;
    code: string;
    requires_attachment: boolean;
    min_days_advance_notice: number;
}

interface Balance {
    leave_type_id: number;
    leave_type_name: string;
    available: number;
    used: number;
    pending: number;
}

interface LeaveApplication {
    id: number;
    reference_number: string;
    leave_type: {
        id: number;
        name: string;
        code: string;
    };
    start_date: string;
    end_date: string;
    date_range: string;
    total_days: number;
    reason: string;
    status: string;
    status_label: string;
    status_color: string;
    submitted_at: string | null;
    created_at: string;
    can_be_edited: boolean;
    can_be_cancelled: boolean;
    is_half_day_start: boolean;
    is_half_day_end: boolean;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    employee: { id: number; full_name: string; employee_number: string } | null;
    leaveTypes: LeaveType[];
    balances: Balance[];
    applications: LeaveApplication[];
    statuses: StatusOption[];
    filters: {
        status: string | null;
        year: number;
    };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Leave', href: '/my/leave' },
];

const showFormModal = ref(false);
const editingApplication = ref<LeaveApplication | null>(null);
const activeStatusFilter = ref<string | null>(props.filters.status);

const filteredApplications = computed(() => {
    if (!activeStatusFilter.value) {
        return props.applications;
    }
    return props.applications.filter(
        (app) => app.status === activeStatusFilter.value,
    );
});

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}

function openNewLeaveModal(): void {
    editingApplication.value = null;
    showFormModal.value = true;
}

function openEditModal(app: LeaveApplication): void {
    editingApplication.value = app;
    showFormModal.value = true;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function submitApplication(applicationId: number): Promise<void> {
    await fetch(`/api/leave-applications/${applicationId}/submit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });
    window.location.reload();
}

async function cancelApplication(applicationId: number): Promise<void> {
    if (!confirm('Are you sure you want to cancel this leave application?')) {
        return;
    }
    await fetch(`/api/leave-applications/${applicationId}/cancel`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
    });
    window.location.reload();
}

function handleFormSuccess(): void {
    showFormModal.value = false;
    window.location.reload();
}
</script>

<template>
    <Head :title="`My Leave - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        My Leave
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your leave applications and balances.
                    </p>
                </div>
                <Button
                    v-if="employee"
                    @click="openNewLeaveModal"
                    class="gap-2"
                >
                    <Plus class="h-4 w-4" />
                    File New Leave
                </Button>
            </div>

            <!-- No Employee Profile -->
            <div
                v-if="!employee"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <Calendar
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                />
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No employee profile
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    No employee profile is linked to your account.
                </p>
            </div>

            <template v-else>
                <!-- Leave Balance Cards -->
                <div
                    v-if="balances.length > 0"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <Card
                        v-for="balance in balances"
                        :key="balance.leave_type_id"
                        class="dark:border-slate-700 dark:bg-slate-900"
                    >
                        <CardHeader class="pb-2">
                            <CardTitle
                                class="text-sm font-medium text-slate-500 dark:text-slate-400"
                            >
                                {{ balance.leave_type_name }}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-baseline gap-2">
                                <span
                                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ balance.available }}
                                </span>
                                <span
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                    >available</span
                                >
                            </div>
                            <div
                                class="mt-1 flex gap-3 text-xs text-slate-500 dark:text-slate-400"
                            >
                                <span>{{ balance.used }} used</span>
                                <span>{{ balance.pending }} pending</span>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Status Filter Tabs -->
                <div
                    class="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2 dark:border-slate-700"
                >
                    <button
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            activeStatusFilter === null
                                ? 'bg-blue-500 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                        "
                        @click="activeStatusFilter = null"
                    >
                        All
                    </button>
                    <button
                        v-for="status in statuses"
                        :key="status.value"
                        class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            activeStatusFilter === status.value
                                ? 'bg-blue-500 text-white'
                                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                        "
                        @click="activeStatusFilter = status.value"
                    >
                        {{ status.label }}
                    </button>
                </div>

                <!-- Applications List -->
                <div
                    v-if="filteredApplications.length === 0"
                    class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
                >
                    <FileText
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                    />
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No applications
                    </h3>
                    <p
                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                    >
                        No leave applications found for the selected filter.
                    </p>
                </div>

                <div
                    v-else
                    class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
                >
                    <table class="w-full text-left text-sm">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800"
                        >
                            <tr>
                                <th
                                    class="px-6 py-3 font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Leave Type
                                </th>
                                <th
                                    class="hidden px-6 py-3 font-medium text-slate-500 md:table-cell dark:text-slate-400"
                                >
                                    Dates
                                </th>
                                <th
                                    class="px-6 py-3 text-center font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Days
                                </th>
                                <th
                                    class="px-6 py-3 text-center font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-6 py-3 text-right font-medium text-slate-500 dark:text-slate-400"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="app in filteredApplications"
                                :key="app.id"
                                class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ app.leave_type.name }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ app.reference_number }}
                                    </div>
                                </td>
                                <td
                                    class="hidden px-6 py-4 text-slate-700 md:table-cell dark:text-slate-300"
                                >
                                    {{ app.date_range }}
                                </td>
                                <td
                                    class="px-6 py-4 text-center text-slate-700 dark:text-slate-300"
                                >
                                    {{ app.total_days }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            badgeClasses(app.status_color)
                                        "
                                    >
                                        {{ app.status_label }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button
                                            v-if="app.can_be_edited"
                                            variant="outline"
                                            size="sm"
                                            @click="openEditModal(app)"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            v-if="app.status === 'draft'"
                                            size="sm"
                                            @click="
                                                submitApplication(app.id)
                                            "
                                        >
                                            Submit
                                        </Button>
                                        <Button
                                            v-if="app.can_be_cancelled"
                                            variant="outline"
                                            size="sm"
                                            class="text-red-600 hover:text-red-700"
                                            @click="
                                                cancelApplication(app.id)
                                            "
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
        </div>

        <!-- Leave Application Form Modal -->
        <LeaveApplicationFormModal
            v-model:open="showFormModal"
            :application="
                editingApplication
                    ? {
                          id: editingApplication.id,
                          leave_type_id: editingApplication.leave_type.id,
                          start_date: editingApplication.start_date,
                          end_date: editingApplication.end_date,
                          is_half_day_start:
                              editingApplication.is_half_day_start,
                          is_half_day_end: editingApplication.is_half_day_end,
                          reason: editingApplication.reason,
                      }
                    : null
            "
            :employee="employee"
            :leave-types="leaveTypes"
            :balances="balances"
            @success="handleFormSuccess"
        />
    </TenantLayout>
</template>
