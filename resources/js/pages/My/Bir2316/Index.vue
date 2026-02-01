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
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    Download,
    FileText,
    Loader2,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Certificate {
    id: number;
    tax_year: number;
    generated_at: string | null;
    has_data: boolean;
}

const props = defineProps<{
    hasEmployeeProfile: boolean;
    certificates: Certificate[];
    availableYears: number[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Documents', href: '/my/bir-2316' },
    { title: 'BIR 2316 Certificates', href: '/my/bir-2316' },
];

const birPrimaryColor = '#8B0000';
const downloadingYear = ref<number | null>(null);
const errorMessage = ref<string | null>(null);

async function downloadCertificate(year: number) {
    downloadingYear.value = year;
    errorMessage.value = null;

    try {
        const response = await fetch(`/api/my/bir-2316/${year}/download`, {
            method: 'GET',
            headers: {
                Accept: 'application/pdf',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            const contentType = response.headers.get('content-type');
            if (contentType?.includes('application/json')) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to download certificate');
            }
            throw new Error('Failed to download certificate');
        }

        // Get filename
        let filename = response.headers.get('X-Filename');
        if (!filename) {
            filename = `bir_2316_${year}.pdf`;
        }

        // Download the file
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        errorMessage.value =
            error instanceof Error ? error.message : 'Failed to download certificate';
    } finally {
        downloadingYear.value = null;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function getCertificateForYear(year: number): Certificate | undefined {
    return props.certificates.find((c) => c.tax_year === year);
}
</script>

<template>
    <Head :title="`BIR 2316 Certificates - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    My BIR 2316 Certificates
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Download your Certificate of Compensation Payment/Tax Withheld.
                </p>
            </div>

            <!-- No Employee Profile -->
            <Card v-if="!hasEmployeeProfile">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <AlertCircle class="h-12 w-12 text-amber-500" />
                    <h3
                        class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No Employee Profile Found
                    </h3>
                    <p
                        class="mt-2 max-w-md text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        Your account is not linked to an employee profile. Please contact
                        your HR department to link your account.
                    </p>
                </CardContent>
            </Card>

            <!-- Error Message -->
            <div
                v-if="errorMessage"
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
            >
                {{ errorMessage }}
            </div>

            <!-- Certificates List -->
            <div v-if="hasEmployeeProfile" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="year in availableYears"
                    :key="year"
                    class="relative overflow-hidden"
                >
                    <!-- Year Badge -->
                    <div
                        class="absolute right-0 top-0 px-3 py-1 text-xs font-bold text-white"
                        :style="{ backgroundColor: birPrimaryColor }"
                    >
                        {{ year }}
                    </div>

                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-base">
                            <FileText class="h-5 w-5 text-slate-500" />
                            BIR Form 2316
                        </CardTitle>
                        <CardDescription>
                            Certificate of Compensation Payment/Tax Withheld
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        <div
                            v-if="getCertificateForYear(year)"
                            class="space-y-3"
                        >
                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                <span v-if="getCertificateForYear(year)?.generated_at">
                                    Generated:
                                    {{ getCertificateForYear(year)?.generated_at }}
                                </span>
                                <span v-else class="text-amber-600">
                                    Certificate available
                                </span>
                            </p>
                            <Button
                                class="w-full"
                                :style="{ backgroundColor: birPrimaryColor }"
                                :disabled="downloadingYear === year"
                                @click="downloadCertificate(year)"
                            >
                                <Loader2
                                    v-if="downloadingYear === year"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <Download v-else class="mr-2 h-4 w-4" />
                                Download PDF
                            </Button>
                        </div>

                        <div v-else class="space-y-3">
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                No certificate generated yet for this year.
                            </p>
                            <Button
                                variant="outline"
                                class="w-full"
                                disabled
                            >
                                <FileText class="mr-2 h-4 w-4" />
                                Not Available
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Empty State -->
            <Card
                v-if="hasEmployeeProfile && availableYears.length === 0"
            >
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <FileText class="h-12 w-12 text-slate-300 dark:text-slate-600" />
                    <h3
                        class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No Certificates Available
                    </h3>
                    <p
                        class="mt-2 max-w-md text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        Your BIR 2316 certificates will appear here once they are
                        generated by HR. Check back after year-end processing.
                    </p>
                </CardContent>
            </Card>

            <!-- Info Card -->
            <Card v-if="hasEmployeeProfile" class="border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20">
                <CardHeader>
                    <CardTitle class="text-sm text-blue-800 dark:text-blue-200">
                        About BIR Form 2316
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        The BIR Form 2316 is a Certificate of Compensation Payment/Tax
                        Withheld. It shows your total compensation income and the income
                        tax withheld by your employer during the calendar year. You may
                        need this certificate when filing your Annual Income Tax Return
                        (BIR Form 1700/1701).
                    </p>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
