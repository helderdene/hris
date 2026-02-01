<script setup lang="ts">
/**
 * QuickActionsSection Component
 *
 * Displays a grid of quick action cards for common employee management tasks.
 * Contains 4 action cards: View All Employees, Add New Employee, Manage Departments, and Generate Reports.
 */
import QuickActionCard from './QuickActionCard.vue';
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
        data-test="quick-actions-section"
    >
        <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Quick Actions
        </h3>
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- View All Employees -->
            <QuickActionCard
                title="View All Employees"
                description="Browse employee directory"
                href="/employees"
                color-variant="blue"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6 text-blue-600 dark:text-blue-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"
                        />
                    </svg>
                </template>
            </QuickActionCard>

            <!-- Add New Employee -->
            <QuickActionCard
                title="Add New Employee"
                description="Create employee record"
                href="/employees/create"
                color-variant="green"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                        />
                    </svg>
                </template>
            </QuickActionCard>

            <!-- Manage Departments -->
            <QuickActionCard
                title="Manage Departments"
                description="Edit organization structure"
                href="/organization/departments"
                color-variant="amber"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6 text-amber-600 dark:text-amber-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                        />
                    </svg>
                </template>
            </QuickActionCard>

            <!-- Generate Reports (Disabled) -->
            <QuickActionCard
                title="Generate Reports"
                description="Export employee data"
                :disabled="true"
                color-variant="gray"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                </template>
            </QuickActionCard>
        </div>
    </div>
</template>
