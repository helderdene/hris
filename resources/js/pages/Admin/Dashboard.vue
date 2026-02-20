<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';
import type {
    AdminDashboardStats,
    SubscriptionByPlan,
    RecentRegistration,
} from '@/types';
import {
    Building2,
    CreditCard,
    Clock,
    AlertTriangle,
    DollarSign,
    TrendingUp,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';

const props = defineProps<{
    stats: AdminDashboardStats;
    subscriptionsByPlan: SubscriptionByPlan[];
    recentRegistrations: RecentRegistration[];
}>();

interface StatCard {
    label: string;
    value: string | number;
    icon: Component;
    color: string;
}

const statCards = computed<StatCard[]>(() => [
    {
        label: 'Total Tenants',
        value: props.stats.total_tenants,
        icon: Building2,
        color: 'text-blue-600 bg-blue-100 dark:text-blue-400 dark:bg-blue-900/50',
    },
    {
        label: 'Active Subscriptions',
        value: props.stats.active_subscriptions,
        icon: CreditCard,
        color: 'text-green-600 bg-green-100 dark:text-green-400 dark:bg-green-900/50',
    },
    {
        label: 'Active Trials',
        value: props.stats.active_trials,
        icon: Clock,
        color: 'text-amber-600 bg-amber-100 dark:text-amber-400 dark:bg-amber-900/50',
    },
    {
        label: 'Expired Trials',
        value: props.stats.expired_trials,
        icon: AlertTriangle,
        color: 'text-red-600 bg-red-100 dark:text-red-400 dark:bg-red-900/50',
    },
    {
        label: 'MRR',
        value: formatCurrency(props.stats.mrr),
        icon: DollarSign,
        color: 'text-emerald-600 bg-emerald-100 dark:text-emerald-400 dark:bg-emerald-900/50',
    },
    {
        label: 'Trial Conversion',
        value: `${props.stats.trial_conversion_rate}%`,
        icon: TrendingUp,
        color: 'text-indigo-600 bg-indigo-100 dark:text-indigo-400 dark:bg-indigo-900/50',
    },
]);

function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 0,
    }).format(amount);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

const statusClasses: Record<string, string> = {
    trial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    active: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
    expired: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    cancelled: 'border border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-400',
    none: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};
</script>

<template>
    <AdminLayout :breadcrumbs="[{ title: 'Admin Dashboard', href: '/admin' }]">
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                Platform Dashboard
            </h1>

            <!-- Stat Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="flex items-center gap-4">
                        <div
                            :class="[card.color, 'flex h-10 w-10 items-center justify-center rounded-lg']"
                        >
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                        <div>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ card.label }}
                            </p>
                            <p class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                {{ card.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Subscriptions by Plan -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Subscriptions by Plan
                    </h2>
                    <div v-if="subscriptionsByPlan.length > 0" class="space-y-3">
                        <div
                            v-for="item in subscriptionsByPlan"
                            :key="item.name"
                            class="flex items-center justify-between"
                        >
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                {{ item.name }}
                            </span>
                            <div class="flex items-center gap-3">
                                <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                    <div
                                        class="h-full rounded-full bg-indigo-500"
                                        :style="{
                                            width: `${Math.min((item.count / Math.max(stats.active_subscriptions, 1)) * 100, 100)}%`,
                                        }"
                                    />
                                </div>
                                <span class="min-w-[2rem] text-right text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ item.count }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                        No active subscriptions yet.
                    </p>
                </div>

                <!-- Recent Registrations -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Recent Registrations
                    </h2>
                    <div v-if="recentRegistrations.length > 0" class="space-y-2">
                        <Link
                            v-for="reg in recentRegistrations"
                            :key="reg.id"
                            :href="`/admin/tenants/${reg.id}`"
                            class="flex items-center justify-between rounded-lg p-2 transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ reg.name }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ reg.plan_name ?? 'No plan' }} &middot;
                                    {{ formatDate(reg.created_at) }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    statusClasses[reg.status] || statusClasses.none,
                                    'ml-3 inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                ]"
                            >
                                {{ reg.status }}
                            </span>
                        </Link>
                    </div>
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">
                        No registrations yet.
                    </p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
