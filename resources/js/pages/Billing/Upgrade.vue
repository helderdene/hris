<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type { BreadcrumbItem, PlanData } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    plans: PlanData[];
    lockedModule: string | null;
    lockedModuleLabel: string | null;
    currentPlanSlug: string | null;
}>();

const { primaryColor, tenantName } = useTenant();
const tenant = useTenant();
const isAdmin = computed(() => tenant.userRole.value === 'admin');
const processing = ref<number | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Billing', href: '/billing' },
    { title: 'Upgrade', href: '/billing/upgrade' },
];

const eligiblePlans = computed(() => {
    if (!props.lockedModule) return props.plans;
    return props.plans.filter((plan) =>
        plan.modules.some((m) => m.module === props.lockedModule),
    );
});

function handleSubscribe(plan: PlanData) {
    const price = plan.prices.find((p) => p.billing_interval === 'monthly');
    if (!price) return;

    processing.value = plan.id;

    const currentPlan = props.plans.find(
        (p) => p.slug === props.currentPlanSlug,
    );
    const url =
        currentPlan && plan.sort_order > currentPlan.sort_order
            ? `/billing/change-plan/${price.id}`
            : `/billing/subscribe/${price.id}`;

    router.post(url, {}, {
        onFinish: () => {
            processing.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Upgrade - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Warning Alert -->
            <div
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950"
            >
                <div class="flex items-start gap-3">
                    <svg
                        class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"
                        />
                    </svg>
                    <div>
                        <h3
                            class="font-medium text-amber-800 dark:text-amber-200"
                        >
                            Feature Not Available
                        </h3>
                        <p
                            class="mt-1 text-sm text-amber-600 dark:text-amber-400"
                        >
                            <template v-if="lockedModuleLabel">
                                The
                                <strong>{{ lockedModuleLabel }}</strong> module
                                is not included in your current plan. Upgrade to
                                access this feature.
                            </template>
                            <template v-else>
                                This feature requires a plan upgrade.
                            </template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Upgrade Your Plan
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    <template v-if="lockedModuleLabel">
                        The following plans include
                        <strong>{{ lockedModuleLabel }}</strong
                        >.
                    </template>
                    <template v-else>
                        Choose a plan to unlock more features.
                    </template>
                </p>
            </div>

            <!-- Eligible Plans -->
            <div class="grid gap-6 lg:grid-cols-3">
                <Card
                    v-for="plan in eligiblePlans"
                    :key="plan.id"
                    class="flex flex-col"
                >
                    <CardHeader>
                        <CardTitle>{{ plan.name }}</CardTitle>
                        <CardDescription>{{
                            plan.description
                        }}</CardDescription>
                        <div v-if="plan.prices.length > 0" class="mt-4">
                            <span
                                class="text-3xl font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{
                                    (plan.prices[0].price_per_unit / 100).toLocaleString(
                                        'en-PH',
                                        {
                                            style: 'currency',
                                            currency: 'PHP',
                                        },
                                    )
                                }}
                            </span>
                            <span
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                / employee / month
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-4">
                        <div class="flex-1">
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="mod in plan.modules"
                                    :key="mod.module"
                                    :variant="
                                        mod.module === lockedModule
                                            ? 'default'
                                            : 'secondary'
                                    "
                                    :style="
                                        mod.module === lockedModule
                                            ? {
                                                  backgroundColor:
                                                      primaryColor,
                                              }
                                            : {}
                                    "
                                    class="text-xs"
                                >
                                    {{ mod.label }}
                                </Badge>
                            </div>
                        </div>
                        <div class="pt-4">
                            <Button
                                v-if="isAdmin"
                                class="w-full"
                                :disabled="processing === plan.id"
                                :style="{ backgroundColor: primaryColor }"
                                @click="handleSubscribe(plan)"
                            >
                                {{
                                    processing === plan.id
                                        ? 'Processing...'
                                        : plan.slug === currentPlanSlug
                                          ? 'Current Plan'
                                          : 'Upgrade'
                                }}
                            </Button>
                            <p
                                v-else
                                class="text-center text-sm text-slate-500"
                            >
                                Contact your admin to upgrade
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- View All Plans Link -->
            <div class="text-center">
                <Link
                    href="/billing/plans"
                    class="text-sm font-medium hover:underline"
                    :style="{ color: primaryColor }"
                >
                    View all plans
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
