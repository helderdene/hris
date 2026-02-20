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
import { Progress } from '@/components/ui/progress';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type {
    BreadcrumbItem,
    PlanModuleData,
    SubscriptionDetail,
    TenantAddonData,
    UsageStats,
} from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    currentPlan: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        limits: Record<string, number> | null;
        modules: PlanModuleData[];
    } | null;
    subscription: SubscriptionDetail | null;
    usage: UsageStats;
    addons: TenantAddonData[];
    isOnTrial: boolean;
    trialEndsAt: string | null;
}>();

const { primaryColor, tenantName } = useTenant();

const tenant = useTenant();
const isAdmin = computed(() => tenant.userRole.value === 'admin');

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Billing', href: '/billing' },
];

const trialDaysLeft = computed(() => {
    if (!props.trialEndsAt) return 0;
    const diff = new Date(props.trialEndsAt).getTime() - Date.now();
    return Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
});

const employeeUsagePercent = computed(() => {
    if (!props.usage.max_employees || props.usage.max_employees === -1) return 0;
    return Math.min(
        100,
        Math.round(
            (props.usage.employee_count / props.usage.max_employees) * 100,
        ),
    );
});

const statusBadgeVariant = computed(() => {
    if (props.isOnTrial) return 'secondary';
    if (!props.subscription) return 'destructive';
    switch (props.subscription.paymongo_status) {
        case 'active':
            return 'default';
        case 'past_due':
            return 'destructive';
        case 'cancelled':
            return 'outline';
        default:
            return 'secondary';
    }
});

const statusLabel = computed(() => {
    if (props.isOnTrial) return 'Trial';
    if (!props.subscription) return 'No Subscription';
    return (
        props.subscription.paymongo_status.charAt(0).toUpperCase() +
        props.subscription.paymongo_status.slice(1).replace('_', ' ')
    );
});

function handleCancel() {
    if (
        confirm(
            'Are you sure you want to cancel your subscription? Access continues until the end of your billing period.',
        )
    ) {
        router.post('/billing/cancel');
    }
}
</script>

<template>
    <Head :title="`Billing - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Billing
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Manage your subscription, plan, and add-ons.
                </p>
            </div>

            <!-- Trial Banner -->
            <div
                v-if="isOnTrial"
                class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900"
                    >
                        <svg
                            class="h-4 w-4 text-amber-600 dark:text-amber-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                            />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p
                            class="text-sm font-medium text-amber-800 dark:text-amber-200"
                        >
                            Trial Period -
                            {{ trialDaysLeft }}
                            {{ trialDaysLeft === 1 ? 'day' : 'days' }}
                            remaining
                        </p>
                        <p class="text-sm text-amber-600 dark:text-amber-400">
                            Subscribe to a plan before your trial ends to
                            continue using all features.
                        </p>
                    </div>
                    <Link
                        v-if="isAdmin"
                        href="/billing/plans"
                        class="shrink-0"
                    >
                        <Button
                            size="sm"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            View Plans
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Non-admin notice -->
            <div
                v-if="!isAdmin"
                class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-950"
            >
                <p class="text-sm text-blue-700 dark:text-blue-300">
                    Contact your organization admin to manage billing and
                    subscriptions.
                </p>
            </div>

            <!-- Stat Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Current Plan</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ currentPlan?.name ?? 'No Plan' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Status</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Badge :variant="statusBadgeVariant">
                            {{ statusLabel }}
                        </Badge>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Employees</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{ usage.employee_count }}
                            <span
                                v-if="
                                    usage.max_employees &&
                                    usage.max_employees !== -1
                                "
                                class="text-sm font-normal text-slate-500"
                            >
                                / {{ usage.max_employees }}
                            </span>
                            <span
                                v-else-if="usage.max_employees === -1"
                                class="text-sm font-normal text-slate-500"
                            >
                                / Unlimited
                            </span>
                        </p>
                        <Progress
                            v-if="
                                usage.max_employees &&
                                usage.max_employees !== -1
                            "
                            :model-value="employeeUsagePercent"
                            class="mt-2 h-1.5"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-2">
                        <CardDescription>Next Billing</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                        >
                            {{
                                subscription?.current_period_end
                                    ? new Date(
                                          subscription.current_period_end,
                                      ).toLocaleDateString()
                                    : '-'
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Plan Details & Modules -->
            <Card v-if="currentPlan">
                <CardHeader>
                    <CardTitle>Plan Details</CardTitle>
                    <CardDescription>{{
                        currentPlan.description
                    }}</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            v-for="mod in currentPlan.modules"
                            :key="mod.module"
                            variant="secondary"
                        >
                            {{ mod.label }}
                        </Badge>
                    </div>
                </CardContent>
            </Card>

            <!-- Active Addons -->
            <Card v-if="addons.length > 0">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle>Active Add-ons</CardTitle>
                            <CardDescription
                                >Extra capacity for your
                                organization</CardDescription
                            >
                        </div>
                        <Link v-if="isAdmin" href="/billing/addons">
                            <Button variant="outline" size="sm"
                                >Manage Add-ons</Button
                            >
                        </Link>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="addon in addons"
                            :key="addon.id"
                            class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div>
                                <p
                                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{ addon.type_label }}
                                </p>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    +{{ addon.extra_units }} units
                                </p>
                            </div>
                            <Badge variant="outline">
                                {{ addon.quantity }} x
                                {{
                                    (addon.price_per_unit / 100).toLocaleString(
                                        'en-PH',
                                        {
                                            style: 'currency',
                                            currency: 'PHP',
                                        },
                                    )
                                }}/mo
                            </Badge>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Actions -->
            <div v-if="isAdmin" class="flex flex-wrap gap-3">
                <Link href="/billing/plans">
                    <Button
                        variant="outline"
                        :style="{
                            borderColor: primaryColor,
                            color: primaryColor,
                        }"
                    >
                        View Plans
                    </Button>
                </Link>
                <Link href="/billing/addons">
                    <Button variant="outline">Manage Add-ons</Button>
                </Link>
                <Button
                    v-if="
                        subscription &&
                        subscription.paymongo_status === 'active'
                    "
                    variant="ghost"
                    class="text-red-600 hover:text-red-700 dark:text-red-400"
                    @click="handleCancel"
                >
                    Cancel Subscription
                </Button>
            </div>
        </div>
    </TenantLayout>
</template>
