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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type { BreadcrumbItem, PlanData } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    plans: PlanData[];
    currentPlanSlug: string | null;
    hasActiveSubscription: boolean;
}>();

const { primaryColor, tenantName } = useTenant();
const tenant = useTenant();
const isAdmin = computed(() => tenant.userRole.value === 'admin');

const billingInterval = ref<'monthly' | 'yearly'>('monthly');
const processing = ref<number | null>(null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Billing', href: '/billing' },
    { title: 'Plans', href: '/billing/plans' },
];

function getPriceForInterval(plan: PlanData) {
    return plan.prices.find(
        (p) => p.billing_interval === billingInterval.value,
    );
}

function formatPrice(amount: number): string {
    const pesos = amount / 100;
    return pesos.toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: pesos % 1 === 0 ? 0 : 2,
        maximumFractionDigits: 2,
    });
}

function planAction(plan: PlanData): 'current' | 'subscribe' | 'upgrade' | 'downgrade' {
    if (plan.slug === props.currentPlanSlug) return 'current';
    if (!props.hasActiveSubscription) return 'subscribe';
    const currentPlan = props.plans.find(
        (p) => p.slug === props.currentPlanSlug,
    );
    if (!currentPlan) return 'subscribe';
    return plan.sort_order > currentPlan.sort_order ? 'upgrade' : 'downgrade';
}

function actionLabel(action: string): string {
    switch (action) {
        case 'current':
            return 'Current Plan';
        case 'subscribe':
            return 'Subscribe';
        case 'upgrade':
            return 'Upgrade';
        case 'downgrade':
            return 'Downgrade';
        default:
            return 'Select';
    }
}

function handlePlanAction(plan: PlanData) {
    const price = getPriceForInterval(plan);
    if (!price) return;

    const action = planAction(plan);
    if (action === 'current') return;

    processing.value = plan.id;

    const url =
        action === 'subscribe'
            ? `/billing/subscribe/${price.id}`
            : `/billing/change-plan/${price.id}`;

    router.post(url, {}, {
        onFinish: () => {
            processing.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Plans - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Plans & Pricing
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Choose the plan that best fits your organization's needs.
                </p>
            </div>

            <!-- Billing Interval Toggle -->
            <div class="flex justify-center">
                <Tabs
                    v-model="billingInterval"
                    default-value="monthly"
                >
                    <TabsList>
                        <TabsTrigger value="monthly">Monthly</TabsTrigger>
                        <TabsTrigger value="yearly">Yearly</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <!-- Plan Cards -->
            <div class="grid gap-6 lg:grid-cols-3">
                <Card
                    v-for="plan in plans"
                    :key="plan.id"
                    :class="[
                        'relative flex flex-col',
                        plan.slug === currentPlanSlug
                            ? 'ring-2 ring-offset-2 dark:ring-offset-slate-950'
                            : '',
                    ]"
                    :style="
                        plan.slug === currentPlanSlug
                            ? { ringColor: primaryColor }
                            : {}
                    "
                >
                    <Badge
                        v-if="plan.slug === currentPlanSlug"
                        class="absolute -top-2.5 left-4"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        Current
                    </Badge>
                    <CardHeader>
                        <CardTitle>{{ plan.name }}</CardTitle>
                        <CardDescription>{{
                            plan.description
                        }}</CardDescription>
                        <div class="mt-4">
                            <template v-if="getPriceForInterval(plan)">
                                <span
                                    class="text-3xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        formatPrice(
                                            getPriceForInterval(plan)!
                                                .price_per_unit,
                                        )
                                    }}
                                </span>
                                <span
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    / employee /
                                    {{
                                        billingInterval === 'monthly'
                                            ? 'month'
                                            : 'year'
                                    }}
                                </span>
                            </template>
                            <span
                                v-else
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                Contact us for pricing
                            </span>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-1 flex-col gap-4">
                        <!-- Limits -->
                        <div
                            v-if="plan.limits"
                            class="space-y-1 text-sm text-slate-600 dark:text-slate-300"
                        >
                            <p v-if="plan.limits.max_employees === -1">
                                Unlimited employees
                            </p>
                            <p v-else-if="plan.limits.max_employees">
                                Up to
                                {{ plan.limits.max_employees }} employees
                            </p>
                            <p v-if="plan.limits.max_biometric_devices === -1">
                                Unlimited biometric devices
                            </p>
                            <p v-else-if="plan.limits.max_biometric_devices">
                                Up to
                                {{
                                    plan.limits.max_biometric_devices
                                }}
                                biometric devices
                            </p>
                        </div>

                        <!-- Modules -->
                        <div class="flex-1">
                            <p
                                class="mb-2 text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400"
                            >
                                Modules Included
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="mod in plan.modules"
                                    :key="mod.module"
                                    variant="secondary"
                                    class="text-xs"
                                >
                                    {{ mod.label }}
                                </Badge>
                            </div>
                        </div>

                        <!-- CTA Button -->
                        <div class="pt-4">
                            <Button
                                class="w-full"
                                :variant="
                                    planAction(plan) === 'current'
                                        ? 'outline'
                                        : 'default'
                                "
                                :disabled="
                                    planAction(plan) === 'current' ||
                                    !isAdmin ||
                                    processing === plan.id
                                "
                                :style="
                                    planAction(plan) !== 'current' && isAdmin
                                        ? { backgroundColor: primaryColor }
                                        : {}
                                "
                                @click="handlePlanAction(plan)"
                            >
                                {{
                                    processing === plan.id
                                        ? 'Processing...'
                                        : actionLabel(planAction(plan))
                                }}
                            </Button>
                            <p
                                v-if="!isAdmin && planAction(plan) !== 'current'"
                                class="mt-2 text-center text-xs text-slate-500"
                            >
                                Contact your admin to change plans
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </TenantLayout>
</template>
