<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Assignment {
    id: number;
    compliance_course: {
        id: number;
        course: {
            id: number;
            title: string;
        };
    };
}

interface Certificate {
    id: number;
    compliance_assignment_id: number;
    certificate_number: string;
    issued_date: string;
    valid_until: string | null;
    final_score: number | null;
    is_valid: boolean;
    is_expired: boolean;
    is_expiring_soon: boolean;
    days_until_expiration: number | null;
    has_file: boolean;
    compliance_assignment?: Assignment;
}

const props = defineProps<{
    certificates: Certificate[];
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'My Compliance Training', href: '/my/compliance' },
    { title: 'Certificates', href: '/my/compliance/certificates' },
];

const certificatesData = computed(() => props.certificates ?? []);

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString();
}

function handleDownload(certificate: Certificate) {
    window.open(`/api/my/compliance/certificates/${certificate.id}/download`, '_blank');
}
</script>

<template>
    <Head :title="`My Certificates - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        My Certificates
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        View and download your compliance training certificates.
                    </p>
                </div>
                <Link href="/my/compliance">
                    <Button variant="outline">
                        Back to Training
                    </Button>
                </Link>
            </div>

            <!-- Certificates Grid -->
            <div v-if="certificatesData.length > 0" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="certificate in certificatesData"
                    :key="certificate.id"
                    class="relative overflow-hidden"
                >
                    <div
                        class="absolute inset-x-0 top-0 h-1"
                        :class="{
                            'bg-green-500': certificate.is_valid && !certificate.is_expiring_soon,
                            'bg-amber-500': certificate.is_expiring_soon,
                            'bg-red-500': certificate.is_expired,
                        }"
                    />
                    <CardContent class="pt-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                                    {{ certificate.compliance_assignment?.compliance_course?.course?.title ?? 'Unknown Course' }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Certificate #{{ certificate.certificate_number }}
                                </p>
                            </div>
                            <Badge
                                :variant="certificate.is_expired ? 'destructive' : certificate.is_expiring_soon ? 'secondary' : 'default'"
                            >
                                {{ certificate.is_expired ? 'Expired' : certificate.is_expiring_soon ? 'Expiring Soon' : 'Valid' }}
                            </Badge>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">Issued</dt>
                                <dd class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ formatDate(certificate.issued_date) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">Valid Until</dt>
                                <dd
                                    class="font-medium"
                                    :class="{
                                        'text-red-600 dark:text-red-400': certificate.is_expired,
                                        'text-amber-600 dark:text-amber-400': certificate.is_expiring_soon,
                                        'text-slate-900 dark:text-slate-100': !certificate.is_expired && !certificate.is_expiring_soon,
                                    }"
                                >
                                    {{ formatDate(certificate.valid_until) }}
                                    <span v-if="certificate.days_until_expiration !== null && !certificate.is_expired" class="text-xs">
                                        ({{ certificate.days_until_expiration }}d)
                                    </span>
                                </dd>
                            </div>
                            <div v-if="certificate.final_score !== null">
                                <dt class="text-slate-500 dark:text-slate-400">Score</dt>
                                <dd class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ certificate.final_score }}%
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-4 flex gap-2">
                            <Button
                                v-if="certificate.has_file"
                                class="flex-1"
                                :style="{ backgroundColor: primaryColor }"
                                @click="handleDownload(certificate)"
                            >
                                Download PDF
                            </Button>
                            <Button
                                v-else
                                variant="outline"
                                class="flex-1"
                                disabled
                            >
                                PDF Not Available
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card v-else>
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <svg
                        class="h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                        />
                    </svg>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                        No certificates yet
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Complete your compliance training to earn certificates.
                    </p>
                    <Link href="/my/compliance" class="mt-4">
                        <Button :style="{ backgroundColor: primaryColor }">
                            View Training
                        </Button>
                    </Link>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
