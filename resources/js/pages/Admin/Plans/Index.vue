<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import type { PlanWithCounts } from '@/types';
import { ref } from 'vue';

defineProps<{
    plans: PlanWithCounts[];
}>();

const confirmToggleId = ref<number | null>(null);

function togglePlan(plan: PlanWithCounts) {
    confirmToggleId.value = null;
    router.post(`/admin/plans/${plan.id}/toggle`, {}, {
        preserveScroll: true,
    });
}

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
    }).format(amount);
}

function monthlyPrice(plan: PlanWithCounts): string {
    const monthly = plan.prices.find((p) => p.billing_interval === 'monthly');
    if (monthly) return formatCurrency(monthly.price_per_unit);
    const yearly = plan.prices.find((p) => p.billing_interval === 'yearly');
    if (yearly) return `${formatCurrency(yearly.price_per_unit)}/yr`;
    return '-';
}
</script>

<template>
    <AdminLayout
        :breadcrumbs="[
            { title: 'Admin', href: '/admin' },
            { title: 'Plans', href: '/admin/plans' },
        ]"
    >
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Plans
                </h1>
                <Link
                    href="/admin/plans/custom/create"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    Create Custom Plan
                </Link>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800/50">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                    <thead class="bg-slate-50 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Active</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Tenants</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Monthly Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr v-for="plan in plans" :key="plan.id" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900 dark:text-slate-100">
                                {{ plan.name }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        plan.is_custom
                                            ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300'
                                            : 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    ]"
                                >
                                    {{ plan.is_custom ? 'Custom' : 'Standard' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <button
                                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                                    :class="plan.is_active ? 'bg-indigo-600' : 'bg-slate-300 dark:bg-slate-600'"
                                    @click="confirmToggleId = plan.id"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition-transform"
                                        :class="plan.is_active ? 'translate-x-4' : 'translate-x-0'"
                                    />
                                </button>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ plan.tenant_count }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600 dark:text-slate-400">
                                {{ monthlyPrice(plan) }}
                            </td>
                            <td class="px-4 py-3">
                                <Link
                                    v-if="plan.is_custom"
                                    :href="`/admin/plans/custom/${plan.id}/edit`"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    Edit
                                </Link>
                                <span v-else class="text-sm text-slate-400">-</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Toggle Confirmation Dialog -->
            <div
                v-if="confirmToggleId !== null"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="confirmToggleId = null"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Toggle Plan</h3>
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                        Are you sure you want to toggle this plan's active status?
                    </p>
                    <div class="flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-600 dark:text-slate-300"
                            @click="confirmToggleId = null"
                        >
                            Cancel
                        </button>
                        <button
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            @click="togglePlan(plans.find((p) => p.id === confirmToggleId)!)"
                        >
                            Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
