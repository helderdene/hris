<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import type {
    AdminTenantDetail,
    AdminSubscriptionHistory,
    AdminUser,
    UsageStats,
} from '@/types';
import { ref } from 'vue';

const props = defineProps<{
    tenant: AdminTenantDetail;
    subscription: {
        id: number;
        paymongo_status: string;
        billing_interval: string | null;
        price_per_unit: number | null;
        quantity: number;
        current_period_end: string | null;
        ends_at: string | null;
    } | null;
    usage: UsageStats;
    adminUsers: AdminUser[];
    subscriptionHistory: AdminSubscriptionHistory[];
    plans: Array<{ id: number; name: string }>;
}>();

// Dialog states
const showExtendTrialDialog = ref(false);
const showAssignPlanDialog = ref(false);
const showCancelDialog = ref(false);

const trialDays = ref(14);
const selectedPlanId = ref('');

function extendTrial() {
    router.post(`/admin/tenants/${props.tenant.id}/extend-trial`, {
        days: trialDays.value,
    }, {
        onSuccess: () => {
            showExtendTrialDialog.value = false;
        },
    });
}

function assignPlan() {
    router.post(`/admin/tenants/${props.tenant.id}/assign-plan`, {
        plan_id: Number(selectedPlanId.value),
    }, {
        onSuccess: () => {
            showAssignPlanDialog.value = false;
        },
    });
}

function cancelSubscription() {
    router.post(`/admin/tenants/${props.tenant.id}/cancel-subscription`, {}, {
        onSuccess: () => {
            showCancelDialog.value = false;
        },
    });
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatCurrency(amount: number | null): string {
    if (amount === null) return '-';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
}

function usagePercent(current: number, max: number | null): number {
    if (max === null || max === -1) return 0;
    if (max === 0) return 100;
    return Math.min(Math.round((current / max) * 100), 100);
}

function usageLabel(max: number | null): string {
    if (max === null) return 'No plan';
    if (max === -1) return 'Unlimited';
    return String(max);
}

const statusClasses: Record<string, string> = {
    trial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    active: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
    expired: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    cancelled: 'border border-slate-300 text-slate-600 dark:border-slate-600 dark:text-slate-400',
    none: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400',
};

const subscriptionStatusClasses: Record<string, string> = {
    active: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
    incomplete: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    past_due: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
};
</script>

<template>
    <AdminLayout
        :breadcrumbs="[
            { title: 'Admin', href: '/admin' },
            { title: 'Tenants', href: '/admin/tenants' },
            { title: tenant.name, href: `/admin/tenants/${tenant.id}` },
        ]"
    >
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {{ tenant.name }}
                </h1>
                <span
                    :class="[
                        statusClasses[tenant.status] || statusClasses.none,
                        'inline-flex rounded-full px-3 py-1 text-sm font-medium capitalize',
                    ]"
                >
                    {{ tenant.status }}
                </span>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Tenant Info -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Tenant Info
                    </h2>
                    <dl class="space-y-3">
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Slug</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ tenant.slug }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Created</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ formatDate(tenant.created_at) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Employees</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ tenant.employee_count }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Plan</dt>
                            <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ tenant.plan?.name ?? 'None' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Subscription Status -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Subscription Status
                    </h2>
                    <dl class="space-y-3">
                        <div v-if="tenant.is_on_trial" class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Trial Ends</dt>
                            <dd class="text-sm font-medium text-amber-600 dark:text-amber-400">{{ formatDate(tenant.trial_ends_at) }}</dd>
                        </div>
                        <div v-if="tenant.trial_expired" class="flex justify-between">
                            <dt class="text-sm text-slate-500 dark:text-slate-400">Trial</dt>
                            <dd class="text-sm font-medium text-red-600 dark:text-red-400">Expired</dd>
                        </div>
                        <template v-if="subscription">
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Status</dt>
                                <dd>
                                    <span
                                        :class="[
                                            subscriptionStatusClasses[subscription.paymongo_status] || 'bg-slate-100 text-slate-600',
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                        ]"
                                    >
                                        {{ subscription.paymongo_status }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Billing</dt>
                                <dd class="text-sm font-medium capitalize text-slate-900 dark:text-slate-100">{{ subscription.billing_interval ?? '-' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Price/Unit</dt>
                                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ formatCurrency(subscription.price_per_unit) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-slate-500 dark:text-slate-400">Period Ends</dt>
                                <dd class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ formatDate(subscription.current_period_end) }}</dd>
                            </div>
                        </template>
                        <p v-if="!subscription && !tenant.is_on_trial" class="text-sm text-slate-500 dark:text-slate-400">
                            No active subscription.
                        </p>
                    </dl>
                </div>

                <!-- Usage vs Limits -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Usage vs Limits
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">Employees</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ usage.employee_count }} / {{ usageLabel(usage.max_employees) }}
                                </span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="usagePercent(usage.employee_count, usage.max_employees) > 90 ? 'bg-red-500' : 'bg-indigo-500'"
                                    :style="{ width: `${usagePercent(usage.employee_count, usage.max_employees)}%` }"
                                />
                            </div>
                        </div>
                        <div>
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-400">Biometric Devices</span>
                                <span class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ usage.biometric_device_count }} / {{ usageLabel(usage.max_biometric_devices) }}
                                </span>
                            </div>
                            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{ width: `${usagePercent(usage.biometric_device_count, usage.max_biometric_devices)}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Users -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Admin Users
                    </h2>
                    <div v-if="adminUsers.length > 0" class="space-y-2">
                        <div
                            v-for="user in adminUsers"
                            :key="user.id"
                            class="flex items-center justify-between rounded-lg p-2"
                        >
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ user.name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ user.email }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500 dark:text-slate-400">No admin users.</p>
                </div>
            </div>

            <!-- Subscription History -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Subscription History
                </h2>
                <div v-if="subscriptionHistory.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Plan</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Status</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Interval</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Price/Unit</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Qty</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase text-slate-500 dark:text-slate-400">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-for="sub in subscriptionHistory" :key="sub.id">
                                <td class="px-3 py-2 text-sm text-slate-900 dark:text-slate-100">{{ sub.plan_name ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span
                                        :class="[
                                            subscriptionStatusClasses[sub.status] || 'bg-slate-100 text-slate-600',
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                        ]"
                                    >
                                        {{ sub.status }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-sm capitalize text-slate-600 dark:text-slate-400">{{ sub.billing_interval ?? '-' }}</td>
                                <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ formatCurrency(sub.price_per_unit) }}</td>
                                <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ sub.quantity }}</td>
                                <td class="px-3 py-2 text-sm text-slate-600 dark:text-slate-400">{{ formatDate(sub.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-slate-500 dark:text-slate-400">No subscription history.</p>
            </div>

            <!-- Actions -->
            <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                    Actions
                </h2>
                <div class="flex flex-wrap gap-3">
                    <button
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700"
                        @click="showExtendTrialDialog = true"
                    >
                        Extend Trial
                    </button>
                    <button
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                        @click="showAssignPlanDialog = true"
                    >
                        Assign Plan
                    </button>
                    <button
                        v-if="subscription && subscription.paymongo_status === 'active'"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700"
                        @click="showCancelDialog = true"
                    >
                        Cancel Subscription
                    </button>
                    <a
                        :href="`/admin/tenants/${tenant.id}/impersonate`"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
                        Impersonate
                    </a>
                </div>
            </div>

            <!-- Extend Trial Dialog -->
            <div
                v-if="showExtendTrialDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showExtendTrialDialog = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Extend Trial</h3>
                    <label class="mb-2 block text-sm text-slate-600 dark:text-slate-400">Days to add</label>
                    <input
                        v-model.number="trialDays"
                        type="number"
                        min="1"
                        max="90"
                        class="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    />
                    <div class="flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-600 dark:text-slate-300"
                            @click="showExtendTrialDialog = false"
                        >
                            Cancel
                        </button>
                        <button
                            class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700"
                            @click="extendTrial"
                        >
                            Extend
                        </button>
                    </div>
                </div>
            </div>

            <!-- Assign Plan Dialog -->
            <div
                v-if="showAssignPlanDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showAssignPlanDialog = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Assign Plan</h3>
                    <label class="mb-2 block text-sm text-slate-600 dark:text-slate-400">Select Plan</label>
                    <select
                        v-model="selectedPlanId"
                        class="mb-4 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                    >
                        <option value="" disabled>Choose a plan</option>
                        <option v-for="plan in plans" :key="plan.id" :value="String(plan.id)">
                            {{ plan.name }}
                        </option>
                    </select>
                    <div class="flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-600 dark:text-slate-300"
                            @click="showAssignPlanDialog = false"
                        >
                            Cancel
                        </button>
                        <button
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            :disabled="!selectedPlanId"
                            @click="assignPlan"
                        >
                            Assign
                        </button>
                    </div>
                </div>
            </div>

            <!-- Cancel Subscription Dialog -->
            <div
                v-if="showCancelDialog"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                @click.self="showCancelDialog = false"
            >
                <div class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <h3 class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Cancel Subscription</h3>
                    <p class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                        Are you sure you want to cancel {{ tenant.name }}'s subscription? This action cannot be undone.
                    </p>
                    <div class="flex justify-end gap-3">
                        <button
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 dark:border-slate-600 dark:text-slate-300"
                            @click="showCancelDialog = false"
                        >
                            Keep Subscription
                        </button>
                        <button
                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
                            @click="cancelSubscription"
                        >
                            Cancel Subscription
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
