<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { useForm } from '@inertiajs/vue3';
import type { ModuleOption } from '@/types';

const props = defineProps<{
    modules: ModuleOption[];
    tenants: Array<{ id: number; name: string }>;
}>();

const form = useForm({
    name: '',
    description: '',
    tenant_id: '' as string | number,
    modules: [] as string[],
    limits: {
        max_employees: 50,
        max_admin_users: 5,
        max_departments: 10,
        max_biometric_devices: 5,
        storage_gb: 10,
        api_access: false,
    },
    prices: [{ billing_interval: 'monthly', price_per_unit: 0 }] as Array<{
        billing_interval: string;
        price_per_unit: number;
    }>,
});

function addPrice() {
    form.prices.push({ billing_interval: 'yearly', price_per_unit: 0 });
}

function removePrice(index: number) {
    form.prices.splice(index, 1);
}

function submit() {
    form.post('/admin/plans/custom', {
        preserveScroll: true,
    });
}

// Module tier groupings for UI
const starterModules = [
    'hr_management', 'organization_management', 'time_attendance',
    'biometric_integration', 'leave_management', 'payroll',
    'hr_compliance', 'employee_self_service', 'user_access_management',
];
const professionalModules = [
    'recruitment', 'onboarding_preboarding', 'training_development',
    'performance_management', 'probationary_management', 'manager_supervisor',
    'help_center', 'hr_analytics',
];
const enterpriseModules = [
    'compliance_training', 'background_check_reference',
    'audit_security', 'careers_portal',
];

function moduleTier(value: string): string {
    if (starterModules.includes(value)) return 'Starter';
    if (professionalModules.includes(value)) return 'Professional';
    if (enterpriseModules.includes(value)) return 'Enterprise';
    return 'Other';
}

function groupedModules(): Record<string, ModuleOption[]> {
    const groups: Record<string, ModuleOption[]> = {};
    for (const mod of props.modules) {
        const tier = moduleTier(mod.value);
        if (!groups[tier]) groups[tier] = [];
        groups[tier].push(mod);
    }
    return groups;
}
</script>

<template>
    <AdminLayout
        :breadcrumbs="[
            { title: 'Admin', href: '/admin' },
            { title: 'Plans', href: '/admin/plans' },
            { title: 'Create Custom Plan', href: '/admin/plans/custom/create' },
        ]"
    >
        <div class="mx-auto max-w-3xl space-y-6">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                Create Custom Plan
            </h1>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Name & Description -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Plan Details</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Assign to Tenant (optional)</label>
                            <select
                                v-model="form.tenant_id"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                            >
                                <option value="">None</option>
                                <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">
                                    {{ tenant.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Modules -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Modules</h2>
                    <p v-if="form.errors.modules" class="mb-2 text-sm text-red-600">{{ form.errors.modules }}</p>
                    <div v-for="(mods, tier) in groupedModules()" :key="tier" class="mb-4 last:mb-0">
                        <h3 class="mb-2 text-sm font-semibold text-slate-500 uppercase dark:text-slate-400">{{ tier }}</h3>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <label
                                v-for="mod in mods"
                                :key="mod.value"
                                class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            >
                                <input
                                    v-model="form.modules"
                                    type="checkbox"
                                    :value="mod.value"
                                    class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600"
                                />
                                <span class="text-sm text-slate-700 dark:text-slate-300">{{ mod.label }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Limits -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Limits</h2>
                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Use -1 for unlimited.</p>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Max Employees</label>
                            <input v-model.number="form.limits.max_employees" type="number" min="-1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Max Admin Users</label>
                            <input v-model.number="form.limits.max_admin_users" type="number" min="-1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Max Departments</label>
                            <input v-model.number="form.limits.max_departments" type="number" min="-1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Max Biometric Devices</label>
                            <input v-model.number="form.limits.max_biometric_devices" type="number" min="-1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Storage (GB)</label>
                            <input v-model.number="form.limits.storage_gb" type="number" min="-1" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                        </div>
                        <div class="flex items-center gap-2 pt-6">
                            <input
                                v-model="form.limits.api_access"
                                type="checkbox"
                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600"
                            />
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300">API Access</label>
                        </div>
                    </div>
                </div>

                <!-- Prices -->
                <div class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800/50">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Pricing</h2>
                        <button
                            type="button"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                            @click="addPrice"
                        >
                            + Add Price
                        </button>
                    </div>
                    <p v-if="form.errors.prices" class="mb-2 text-sm text-red-600">{{ form.errors.prices }}</p>
                    <div class="space-y-3">
                        <div
                            v-for="(price, index) in form.prices"
                            :key="index"
                            class="flex items-end gap-3"
                        >
                            <div class="flex-1">
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Interval</label>
                                <select
                                    v-model="price.billing_interval"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                >
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Price per Unit (PHP)</label>
                                <input
                                    v-model.number="price.price_per_unit"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"
                                />
                            </div>
                            <button
                                v-if="form.prices.length > 1"
                                type="button"
                                class="mb-0.5 rounded-lg p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/50"
                                @click="removePrice(index)"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-indigo-600 px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating...' : 'Create Plan' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
