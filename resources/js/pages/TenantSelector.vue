<script setup lang="ts">
import TenantCard, { type TenantProps } from '@/components/TenantCard.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { submit as selectTenantRoute } from '@/routes/tenant/select';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Tenant extends TenantProps {
    role: string;
    role_label: string;
    lastAccessed?: string | null;
}

defineProps<{
    tenants: Tenant[];
}>();

const selectingTenantId = ref<number | null>(null);

function handleTenantSelect(tenant: Tenant) {
    selectingTenantId.value = tenant.id;

    router.post(
        selectTenantRoute.url(tenant.id),
        {},
        {
            onError: () => {
                selectingTenantId.value = null;
            },
            onFinish: () => {
                // Keep loading state as we're redirecting to subdomain
                // Only reset on error
            },
        },
    );
}
</script>

<template>
    <Head title="Select Organization" />

    <AuthLayout
        title="Select Organization"
        description="Choose which organization you want to access"
        size="lg"
    >
        <!-- Tenant Cards Grid -->
        <div v-if="tenants.length > 0" class="grid gap-4 sm:grid-cols-2">
            <TenantCard
                v-for="tenant in tenants"
                :key="tenant.id"
                :tenant="tenant"
                :is-loading="selectingTenantId === tenant.id"
                :disabled="
                    selectingTenantId !== null &&
                    selectingTenantId !== tenant.id
                "
                @select="handleTenantSelect"
            />
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50/50 p-8 text-center dark:border-slate-600 dark:bg-slate-800/50"
        >
            <div
                class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700"
            >
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
                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"
                    />
                </svg>
            </div>
            <h3
                class="mb-1 text-sm font-medium text-slate-900 dark:text-slate-100"
            >
                No Organizations
            </h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                You don't have access to any organizations yet.
            </p>
        </div>
    </AuthLayout>
</template>
