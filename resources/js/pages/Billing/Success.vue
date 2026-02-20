<script setup lang="ts">
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
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    planName: string;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Billing', href: '/billing' },
    { title: 'Success', href: '/billing/success' },
];
</script>

<template>
    <Head :title="`Subscription Successful - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex items-center justify-center py-12">
            <Card class="w-full max-w-md text-center">
                <CardHeader>
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                        <svg
                            class="h-8 w-8 text-green-600 dark:text-green-400"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m4.5 12.75 6 6 9-13.5"
                            />
                        </svg>
                    </div>
                    <CardTitle class="text-xl">Subscription Successful</CardTitle>
                    <CardDescription>
                        You've subscribed to the <strong>{{ planName }}</strong> plan.
                        Your subscription is being processed and will be active shortly.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Link href="/dashboard">
                        <Button
                            class="w-full"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            Go to Dashboard
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
