<script setup lang="ts">
import CompensationEditModal from '@/Components/CompensationEditModal.vue';
import CompensationHistoryTimeline from '@/Components/CompensationHistoryTimeline.vue';
import LabelValueList from '@/Components/LabelValueList.vue';
import { Button } from '@/components/ui/button';
import {
    type CompensationApiResponse,
    type CompensationHistory,
    type EmployeeCompensation,
} from '@/types/compensation';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface Props {
    employeeId: number;
}

const props = defineProps<Props>();

const page = usePage();

/**
 * Check if current user can manage employees.
 */
const canManageEmployees = computed(
    () => page.props.tenant?.can_manage_employees ?? false,
);

/**
 * Compensation data state.
 */
const compensation = ref<EmployeeCompensation | null>(null);
const history = ref<CompensationHistory[]>([]);
const loading = ref(true);
const error = ref<string | null>(null);

/**
 * Modal state.
 */
const isEditModalOpen = ref(false);

/**
 * Format a date string for display.
 */
function formatDate(dateStr: string | null): string | null {
    if (!dateStr) return null;
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

/**
 * Format salary for display using PHP currency.
 */
function formatSalary(salary: string | null): string | null {
    if (!salary) return null;
    const num = parseFloat(salary);
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num);
}

/**
 * Compensation details section items.
 */
const compensationItems = computed(() => [
    {
        label: 'Basic Pay',
        value: formatSalary(compensation.value?.basic_pay ?? null),
    },
    { label: 'Pay Type', value: compensation.value?.pay_type_label },
    {
        label: 'Effective Date',
        value: formatDate(compensation.value?.effective_date ?? null),
    },
    { label: 'Currency', value: compensation.value?.currency ?? 'PHP' },
]);

/**
 * Bank account section items.
 */
const bankAccountItems = computed(() => [
    { label: 'Bank Name', value: compensation.value?.bank_name },
    { label: 'Account Name', value: compensation.value?.account_name },
    { label: 'Account Number', value: compensation.value?.account_number },
    { label: 'Account Type', value: compensation.value?.account_type_label },
]);

/**
 * Check if employee has bank account details.
 */
const hasBankAccount = computed(() => {
    return compensation.value?.bank_name || compensation.value?.account_number;
});

/**
 * Get CSRF token from cookies.
 */
function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Fetch compensation data from API.
 */
async function fetchCompensation() {
    loading.value = true;
    error.value = null;

    try {
        const url = `/api/employees/${props.employeeId}/compensation`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to fetch compensation data');
        }

        const data: CompensationApiResponse = await response.json();
        compensation.value = data.data.compensation;
        history.value = data.data.history;
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'An error occurred';
    } finally {
        loading.value = false;
    }
}

/**
 * Handle edit button click.
 */
function handleEditClick() {
    isEditModalOpen.value = true;
}

/**
 * Handle modal close.
 */
function handleModalClose() {
    isEditModalOpen.value = false;
}

/**
 * Handle successful compensation update.
 */
function handleSuccess() {
    fetchCompensation();
}

// Fetch data on mount
onMounted(() => {
    fetchCompensation();
});
</script>

<template>
    <div class="space-y-6" data-test="compensation-tab">
        <!-- Loading State -->
        <div v-if="loading" class="space-y-6">
            <!-- Compensation Details Skeleton -->
            <div>
                <div class="mb-3 flex items-center justify-between">
                    <div
                        class="h-5 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                    />
                    <div
                        class="h-8 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                    />
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="space-y-3">
                        <div
                            v-for="i in 4"
                            :key="i"
                            class="flex items-center justify-between"
                        >
                            <div
                                class="h-4 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                            />
                            <div
                                class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bank Account Skeleton -->
            <div>
                <div
                    class="mb-3 h-5 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                />
                <div
                    class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="space-y-3">
                        <div
                            v-for="i in 4"
                            :key="i"
                            class="flex items-center justify-between"
                        >
                            <div
                                class="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                            />
                            <div
                                class="h-4 w-36 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-else-if="error"
            class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
        >
            <div class="flex items-center gap-2">
                <svg
                    class="h-5 w-5 text-red-500 dark:text-red-400"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"
                    />
                </svg>
                <p class="text-sm font-medium text-red-700 dark:text-red-400">
                    {{ error }}
                </p>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="mt-3"
                @click="fetchCompensation"
            >
                Try Again
            </Button>
        </div>

        <!-- Content State -->
        <template v-else>
            <!-- Empty State (No Compensation Record) -->
            <div
                v-if="!compensation"
                class="flex flex-col items-center justify-center py-12 text-center"
                data-test="no-compensation-state"
            >
                <div class="rounded-full bg-slate-100 p-3 dark:bg-slate-800">
                    <svg
                        class="h-6 w-6 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z"
                        />
                    </svg>
                </div>
                <h3
                    class="mt-4 text-sm font-medium text-slate-900 dark:text-slate-100"
                >
                    No compensation record
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Compensation details have not been set up for this employee.
                </p>
                <Button
                    v-if="canManageEmployees"
                    class="mt-4"
                    @click="handleEditClick"
                    data-test="add-compensation-button"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Add Compensation
                </Button>
            </div>

            <!-- Compensation Details -->
            <template v-else>
                <!-- Compensation Details Section -->
                <div data-test="compensation-details-section">
                    <div class="mb-3 flex items-center justify-between">
                        <h3
                            class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                        >
                            Compensation Details
                        </h3>
                        <Button
                            v-if="canManageEmployees"
                            variant="outline"
                            size="sm"
                            @click="handleEditClick"
                            data-test="edit-compensation-button"
                        >
                            <svg
                                class="mr-1.5 h-3.5 w-3.5"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                />
                            </svg>
                            Edit Compensation
                        </Button>
                    </div>
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <LabelValueList :items="compensationItems" />
                    </div>
                </div>

                <!-- Bank Account Section -->
                <div data-test="bank-account-section">
                    <h3
                        class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Bank Account
                    </h3>
                    <div
                        v-if="hasBankAccount"
                        class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <LabelValueList :items="bankAccountItems" />
                    </div>
                    <div
                        v-else
                        class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-center dark:border-slate-600 dark:bg-slate-800/30"
                    >
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            No bank account details on file
                        </p>
                    </div>
                </div>

                <!-- Compensation History Section -->
                <div data-test="compensation-history-section">
                    <h3
                        class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        Compensation History
                    </h3>
                    <CompensationHistoryTimeline
                        :history="history"
                        :loading="false"
                    />
                </div>
            </template>
        </template>

        <!-- Edit Modal -->
        <CompensationEditModal
            v-model:open="isEditModalOpen"
            :employee-id="employeeId"
            :current-compensation="compensation"
            @close="handleModalClose"
            @success="handleSuccess"
        />
    </div>
</template>
