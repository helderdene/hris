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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type {
    AddonTypeOption,
    BreadcrumbItem,
    TenantAddonData,
} from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    addons: TenantAddonData[];
    addonTypes: AddonTypeOption[];
    effectiveLimits: Record<string, number | null>;
    isEnterprise: boolean;
}>();

const { primaryColor, tenantName } = useTenant();
const tenant = useTenant();
const isAdmin = computed(() => tenant.userRole.value === 'admin');
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Billing', href: '/billing' },
    { title: 'Add-ons', href: '/billing/addons' },
];

const purchaseType = ref(props.addonTypes[0]?.value ?? '');
const purchaseQuantity = ref(1);
const purchasing = ref(false);
const updatingId = ref<number | null>(null);
const cancellingId = ref<number | null>(null);

function formatCurrency(centavos: number): string {
    return (centavos / 100).toLocaleString('en-PH', {
        style: 'currency',
        currency: 'PHP',
    });
}

function handlePurchase() {
    purchasing.value = true;
    router.post(
        '/billing/addons/purchase',
        {
            type: purchaseType.value,
            quantity: purchaseQuantity.value,
        },
        {
            onFinish: () => {
                purchasing.value = false;
                purchaseQuantity.value = 1;
            },
        },
    );
}

function handleUpdate(addon: TenantAddonData, newQuantity: number) {
    if (newQuantity < 1) return;
    updatingId.value = addon.id;
    router.post(
        `/billing/addons/${addon.id}/update`,
        { quantity: newQuantity },
        {
            onFinish: () => {
                updatingId.value = null;
            },
        },
    );
}

function handleCancel(addon: TenantAddonData) {
    if (!confirm(`Cancel ${addon.type_label}? This will remove the extra capacity.`)) return;
    cancellingId.value = addon.id;
    router.post(
        `/billing/addons/${addon.id}/cancel`,
        {},
        {
            onFinish: () => {
                cancellingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Add-ons - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Add-ons
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Purchase extra capacity for your organization.
                </p>
            </div>

            <!-- Enterprise notice -->
            <div
                v-if="isEnterprise"
                class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-950"
            >
                <p class="text-sm text-green-700 dark:text-green-300">
                    Your Enterprise plan includes unlimited capacity. Add-ons
                    are not needed.
                </p>
            </div>

            <template v-else>
                <!-- Current Limits -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription
                                >Max Employees</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <p
                                class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{
                                    effectiveLimits.max_employees === -1
                                        ? 'Unlimited'
                                        : effectiveLimits.max_employees ?? '-'
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardDescription
                                >Max Biometric Devices</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <p
                                class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                            >
                                {{
                                    effectiveLimits.max_biometric_devices === -1
                                        ? 'Unlimited'
                                        : effectiveLimits
                                              .max_biometric_devices ?? '-'
                                }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Purchase Form (admin only) -->
                <Card v-if="isAdmin">
                    <CardHeader>
                        <CardTitle>Purchase Add-on</CardTitle>
                        <CardDescription
                            >Add extra capacity to your
                            plan</CardDescription
                        >
                    </CardHeader>
                    <CardContent>
                        <form
                            class="flex flex-wrap items-end gap-4"
                            @submit.prevent="handlePurchase"
                        >
                            <div class="w-full sm:w-56">
                                <Label for="addon-type">Type</Label>
                                <Select v-model="purchaseType">
                                    <SelectTrigger id="addon-type">
                                        <SelectValue
                                            placeholder="Select type"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="type in addonTypes"
                                            :key="type.value"
                                            :value="type.value"
                                        >
                                            {{ type.label }} ({{
                                                formatCurrency(
                                                    type.default_price,
                                                )
                                            }}/mo)
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="w-24">
                                <Label for="addon-quantity">Qty</Label>
                                <Input
                                    id="addon-quantity"
                                    v-model.number="purchaseQuantity"
                                    type="number"
                                    min="1"
                                />
                            </div>
                            <Button
                                type="submit"
                                :disabled="purchasing"
                                :style="{ backgroundColor: primaryColor }"
                            >
                                {{
                                    purchasing ? 'Purchasing...' : 'Purchase'
                                }}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <!-- Active Add-ons -->
                <Card v-if="addons.length > 0">
                    <CardHeader>
                        <CardTitle>Active Add-ons</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="addon in addons"
                                :key="addon.id"
                                class="flex items-center justify-between rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div>
                                    <p
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ addon.type_label }}
                                    </p>
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ addon.quantity }} x
                                        {{
                                            formatCurrency(
                                                addon.price_per_unit,
                                            )
                                        }}/mo = +{{ addon.extra_units }} units
                                    </p>
                                </div>
                                <div
                                    v-if="isAdmin"
                                    class="flex items-center gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            addon.quantity <= 1 ||
                                            updatingId === addon.id
                                        "
                                        @click="
                                            handleUpdate(
                                                addon,
                                                addon.quantity - 1,
                                            )
                                        "
                                    >
                                        -
                                    </Button>
                                    <span
                                        class="w-8 text-center text-sm font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ addon.quantity }}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="updatingId === addon.id"
                                        @click="
                                            handleUpdate(
                                                addon,
                                                addon.quantity + 1,
                                            )
                                        "
                                    >
                                        +
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700 dark:text-red-400"
                                        :disabled="cancellingId === addon.id"
                                        @click="handleCancel(addon)"
                                    >
                                        {{
                                            cancellingId === addon.id
                                                ? 'Cancelling...'
                                                : 'Cancel'
                                        }}
                                    </Button>
                                </div>
                                <Badge v-else variant="outline">
                                    {{ formatCurrency(addon.monthly_cost) }}/mo
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Empty state -->
                <div
                    v-if="addons.length === 0 && !isAdmin"
                    class="py-12 text-center"
                >
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        No active add-ons. Contact your admin to purchase
                        add-ons.
                    </p>
                </div>
            </template>
        </div>
    </TenantLayout>
</template>
