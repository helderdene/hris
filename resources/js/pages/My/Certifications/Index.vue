<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import EnumSelect from '@/Components/EnumSelect.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface CertificationFile {
    id: number;
    original_filename: string;
    mime_type: string;
    file_size_formatted: string;
}

interface CertificationType {
    id: number;
    name: string;
    validity_period_months: number | null;
}

interface Certification {
    id: number;
    certification_type_id: number;
    certification_type: CertificationType;
    certificate_number: string | null;
    issuing_body: string | null;
    issued_date: string | null;
    expiry_date: string | null;
    description: string | null;
    status: string;
    status_label: string;
    status_color: string;
    can_be_edited: boolean;
    can_be_submitted: boolean;
    is_expiring_soon: boolean;
    days_until_expiry: number | null;
    expiry_status: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    files: CertificationFile[];
    files_count?: number;
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface Statistics {
    total: number;
    active: number;
    draft: number;
    pending_approval: number;
    expiring_soon: number;
}

interface Filters {
    status: string | null;
}

const props = defineProps<{
    certifications: Certification[];
    certificationTypes: CertificationType[];
    statuses: StatusOption[];
    statistics: Statistics;
    filters: Filters;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Certifications', href: '/my/certifications' },
];

const activeStatusFilter = ref<string | null>(props.filters.status);

// Form Modal State
const isFormModalOpen = ref(false);
const editingCertification = ref<Certification | null>(null);
const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

// Files Modal State
const isFilesModalOpen = ref(false);
const selectedCertification = ref<Certification | null>(null);
const uploadingFile = ref(false);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Form data
const form = ref({
    certification_type_id: '',
    certificate_number: '',
    issuing_body: '',
    issued_date: '',
    expiry_date: '',
    description: '',
});

const isEditing = computed(() => !!editingCertification.value);

const filteredCertifications = computed(() => {
    if (!activeStatusFilter.value) {
        return props.certifications;
    }
    return props.certifications.filter(
        (cert) => cert.status === activeStatusFilter.value,
    );
});

const certificationTypeOptions = computed(() =>
    props.certificationTypes.map((t) => ({
        value: String(t.id),
        label: t.name,
    })),
);

watch(
    () => editingCertification.value,
    (newCert) => {
        if (newCert) {
            form.value = {
                certification_type_id: String(newCert.certification_type_id),
                certificate_number: newCert.certificate_number || '',
                issuing_body: newCert.issuing_body || '',
                issued_date: newCert.issued_date || '',
                expiry_date: newCert.expiry_date || '',
                description: newCert.description || '',
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

function resetForm(): void {
    form.value = {
        certification_type_id: '',
        certificate_number: '',
        issuing_body: '',
        issued_date: '',
        expiry_date: '',
        description: '',
    };
    errors.value = {};
}

function getStatusBadgeClasses(color: string): string {
    switch (color) {
        case 'slate':
            return 'bg-slate-100 text-slate-800 dark:bg-slate-700/50 dark:text-slate-300';
        case 'amber':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300';
        case 'green':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'red':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function openAddModal(): void {
    editingCertification.value = null;
    isFormModalOpen.value = true;
}

function openEditModal(certification: Certification): void {
    editingCertification.value = certification;
    isFormModalOpen.value = true;
}

function closeFormModal(): void {
    isFormModalOpen.value = false;
    editingCertification.value = null;
    errors.value = {};
}

async function handleSubmitForm(): Promise<void> {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/my/certifications/${editingCertification.value!.id}`
        : '/api/my/certifications';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        certification_type_id: Number(form.value.certification_type_id),
        certificate_number: form.value.certificate_number || null,
        issuing_body: form.value.issuing_body || null,
        issued_date: form.value.issued_date || null,
        expiry_date: form.value.expiry_date || null,
        description: form.value.description || null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            closeFormModal();
            router.reload({ only: ['certifications', 'statistics'] });
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch {
        errors.value = {
            general: 'An error occurred while saving the certification',
        };
    } finally {
        isSubmitting.value = false;
    }
}

async function handleSubmitCertification(
    certification: Certification,
): Promise<void> {
    if (
        !certification.files ||
        (certification.files_count ?? certification.files.length) === 0
    ) {
        alert('Please upload at least one certificate file before submitting.');
        return;
    }

    if (
        !confirm(
            'Are you sure you want to submit this certification for approval?',
        )
    ) {
        return;
    }

    try {
        const response = await fetch(
            `/api/my/certifications/${certification.id}/submit`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        const data = await response.json();

        if (response.ok) {
            router.reload({ only: ['certifications', 'statistics'] });
        } else {
            alert(data.message || 'Failed to submit certification');
        }
    } catch {
        alert('An error occurred while submitting the certification');
    }
}

async function handleDeleteCertification(
    certification: Certification,
): Promise<void> {
    if (
        !confirm(
            'Are you sure you want to delete this certification? This action cannot be undone.',
        )
    ) {
        return;
    }

    try {
        const response = await fetch(
            `/api/my/certifications/${certification.id}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            router.reload({ only: ['certifications', 'statistics'] });
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete certification');
        }
    } catch {
        alert('An error occurred while deleting the certification');
    }
}

function openFilesModal(certification: Certification): void {
    selectedCertification.value = certification;
    isFilesModalOpen.value = true;
}

function triggerFileUpload(): void {
    fileInputRef.value?.click();
}

async function handleFileUpload(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file || !selectedCertification.value) return;

    uploadingFile.value = true;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await fetch(
            `/api/certifications/${selectedCertification.value.id}/files`,
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
                body: formData,
            },
        );

        const data = await response.json();

        if (response.ok) {
            router.reload({ only: ['certifications'] });
            // Update the selected certification's files
            if (selectedCertification.value) {
                selectedCertification.value.files = [
                    ...(selectedCertification.value.files || []),
                    data.data,
                ];
            }
        } else {
            alert(data.message || 'Failed to upload file');
        }
    } catch {
        alert('An error occurred while uploading the file');
    } finally {
        uploadingFile.value = false;
        input.value = '';
    }
}

async function handleDeleteFile(
    certificationId: number,
    fileId: number,
): Promise<void> {
    if (!confirm('Are you sure you want to delete this file?')) {
        return;
    }

    try {
        const response = await fetch(
            `/api/certifications/${certificationId}/files/${fileId}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            router.reload({ only: ['certifications'] });
            // Update the selected certification's files
            if (selectedCertification.value) {
                selectedCertification.value.files =
                    selectedCertification.value.files.filter(
                        (f) => f.id !== fileId,
                    );
            }
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete file');
        }
    } catch {
        alert('An error occurred while deleting the file');
    }
}

function downloadFile(certificationId: number, fileId: number): void {
    window.open(
        `/api/certifications/${certificationId}/files/${fileId}/download`,
        '_blank',
    );
}
</script>

<template>
    <Head :title="`My Certifications - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        My Certifications
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your professional certifications and credentials.
                    </p>
                </div>
                <Button
                    @click="openAddModal"
                    class="gap-2"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-certification-button"
                >
                    <svg
                        class="h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Add Certification
                </Button>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Total
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ statistics.total }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Active
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400"
                    >
                        {{ statistics.active }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Pending
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400"
                    >
                        {{ statistics.pending_approval }}
                    </div>
                </div>
                <div
                    class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div
                        class="text-sm font-medium text-slate-500 dark:text-slate-400"
                    >
                        Expiring Soon
                    </div>
                    <div
                        class="mt-1 text-2xl font-semibold text-orange-600 dark:text-orange-400"
                    >
                        {{ statistics.expiring_soon }}
                    </div>
                </div>
            </div>

            <!-- Status Filter Tabs -->
            <div
                class="flex gap-2 overflow-x-auto border-b border-slate-200 pb-2 dark:border-slate-700"
            >
                <button
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeStatusFilter === null
                            ? 'bg-blue-500 text-white'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                    "
                    @click="activeStatusFilter = null"
                >
                    All
                </button>
                <button
                    v-for="status in statuses"
                    :key="status.value"
                    class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeStatusFilter === status.value
                            ? 'bg-blue-500 text-white'
                            : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                    "
                    @click="activeStatusFilter = status.value"
                >
                    {{ status.label }}
                </button>
            </div>

            <!-- Certifications Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Certification
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Certificate #
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Issuing Body
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Issue Date
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Expiry Date
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Files
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="certification in filteredCertifications"
                                :key="certification.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                            >
                                <td class="px-6 py-4">
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{
                                            certification.certification_type
                                                .name
                                        }}
                                    </div>
                                    <div
                                        v-if="certification.description"
                                        class="mt-0.5 max-w-xs truncate text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ certification.description }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        certification.certificate_number || '-'
                                    }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-slate-700 dark:text-slate-300"
                                >
                                    {{ certification.issuing_body || '-' }}
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-slate-700 whitespace-nowrap dark:text-slate-300"
                                >
                                    {{ formatDate(certification.issued_date) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div
                                        class="text-sm text-slate-700 dark:text-slate-300"
                                    >
                                        {{
                                            formatDate(certification.expiry_date)
                                        }}
                                    </div>
                                    <div
                                        v-if="certification.is_expiring_soon"
                                        class="text-xs text-orange-600 dark:text-orange-400"
                                    >
                                        Expires in
                                        {{ certification.days_until_expiry }}
                                        days
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <button
                                        class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                        @click="openFilesModal(certification)"
                                    >
                                        {{
                                            certification.files?.length ||
                                            certification.files_count ||
                                            0
                                        }}
                                        file(s)
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getStatusBadgeClasses(
                                                certification.status_color,
                                            )
                                        "
                                    >
                                        {{ certification.status_label }}
                                    </span>
                                    <div
                                        v-if="certification.rejection_reason"
                                        class="mt-1 max-w-xs truncate text-xs text-red-600 dark:text-red-400"
                                        :title="certification.rejection_reason"
                                    >
                                        Reason:
                                        {{ certification.rejection_reason }}
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0"
                                            >
                                                <span class="sr-only"
                                                    >Open menu</span
                                                >
                                                <svg
                                                    class="h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                    />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel
                                                >Actions</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                @click="
                                                    openFilesModal(
                                                        certification,
                                                    )
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                                    />
                                                </svg>
                                                Manage Files
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    certification.can_be_edited
                                                "
                                                @click="
                                                    openEditModal(certification)
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                                    />
                                                </svg>
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    certification.can_be_submitted
                                                "
                                                @click="
                                                    handleSubmitCertification(
                                                        certification,
                                                    )
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4 text-blue-600"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"
                                                    />
                                                </svg>
                                                Submit for Approval
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                v-if="
                                                    certification.can_be_edited
                                                "
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                @click="
                                                    handleDeleteCertification(
                                                        certification,
                                                    )
                                                "
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                                    />
                                                </svg>
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card Layout -->
                <div class="md:hidden">
                    <div
                        v-for="certification in filteredCertifications"
                        :key="certification.id"
                        class="border-b border-slate-200 p-4 last:border-b-0 dark:border-slate-700"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div
                                    class="font-medium text-slate-900 dark:text-slate-100"
                                >
                                    {{
                                        certification.certification_type.name
                                    }}
                                </div>
                                <div
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        certification.certificate_number ||
                                        'No certificate #'
                                    }}
                                </div>
                            </div>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    getStatusBadgeClasses(
                                        certification.status_color,
                                    )
                                "
                            >
                                {{ certification.status_label }}
                            </span>
                        </div>
                        <div
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <div v-if="certification.expiry_date">
                                Expires:
                                {{ formatDate(certification.expiry_date) }}
                                <span
                                    v-if="certification.is_expiring_soon"
                                    class="text-orange-600 dark:text-orange-400"
                                >
                                    ({{ certification.days_until_expiry }}
                                    days)
                                </span>
                            </div>
                            <div>
                                {{
                                    certification.files?.length ||
                                    certification.files_count ||
                                    0
                                }}
                                file(s) attached
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openFilesModal(certification)"
                            >
                                Files
                            </Button>
                            <Button
                                v-if="certification.can_be_edited"
                                variant="outline"
                                size="sm"
                                @click="openEditModal(certification)"
                            >
                                Edit
                            </Button>
                            <Button
                                v-if="certification.can_be_submitted"
                                size="sm"
                                @click="
                                    handleSubmitCertification(certification)
                                "
                            >
                                Submit
                            </Button>
                            <Button
                                v-if="certification.can_be_edited"
                                variant="outline"
                                size="sm"
                                class="text-red-600"
                                @click="
                                    handleDeleteCertification(certification)
                                "
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="filteredCertifications.length === 0"
                    class="px-6 py-12 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.746 3.746 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No certifications found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{
                            activeStatusFilter
                                ? 'No certifications match the selected filter.'
                                : 'Get started by adding your first certification.'
                        }}
                    </p>
                    <div v-if="!activeStatusFilter" class="mt-6">
                        <Button
                            @click="openAddModal"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Certification
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Certification Modal -->
        <Dialog v-model:open="isFormModalOpen" @update:open="(v) => !v && closeFormModal()">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            isEditing
                                ? 'Edit Certification'
                                : 'Add Certification'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            isEditing
                                ? 'Update your certification details.'
                                : 'Add a new professional certification or credential.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSubmitForm" class="space-y-4">
                    <!-- General Error -->
                    <div
                        v-if="errors.general"
                        class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                    >
                        {{ errors.general }}
                    </div>

                    <!-- Certification Type -->
                    <div class="space-y-2">
                        <Label for="certification_type_id"
                            >Certification Type *</Label
                        >
                        <EnumSelect
                            id="certification_type_id"
                            v-model="form.certification_type_id"
                            :options="certificationTypeOptions"
                            placeholder="Select certification type"
                        />
                        <p
                            v-if="errors.certification_type_id"
                            class="text-sm text-red-500"
                        >
                            {{ errors.certification_type_id }}
                        </p>
                    </div>

                    <!-- Certificate Number -->
                    <div class="space-y-2">
                        <Label for="certificate_number">Certificate Number</Label>
                        <Input
                            id="certificate_number"
                            v-model="form.certificate_number"
                            type="text"
                            placeholder="e.g., CERT-12345"
                            :class="{
                                'border-red-500': errors.certificate_number,
                            }"
                        />
                        <p
                            v-if="errors.certificate_number"
                            class="text-sm text-red-500"
                        >
                            {{ errors.certificate_number }}
                        </p>
                    </div>

                    <!-- Issuing Body -->
                    <div class="space-y-2">
                        <Label for="issuing_body">Issuing Body</Label>
                        <Input
                            id="issuing_body"
                            v-model="form.issuing_body"
                            type="text"
                            placeholder="e.g., American Red Cross"
                            :class="{ 'border-red-500': errors.issuing_body }"
                        />
                        <p
                            v-if="errors.issuing_body"
                            class="text-sm text-red-500"
                        >
                            {{ errors.issuing_body }}
                        </p>
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="issued_date">Issue Date</Label>
                            <Input
                                id="issued_date"
                                v-model="form.issued_date"
                                type="date"
                                :class="{ 'border-red-500': errors.issued_date }"
                            />
                            <p
                                v-if="errors.issued_date"
                                class="text-sm text-red-500"
                            >
                                {{ errors.issued_date }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="expiry_date">Expiry Date</Label>
                            <Input
                                id="expiry_date"
                                v-model="form.expiry_date"
                                type="date"
                                :class="{ 'border-red-500': errors.expiry_date }"
                            />
                            <p
                                v-if="errors.expiry_date"
                                class="text-sm text-red-500"
                            >
                                {{ errors.expiry_date }}
                            </p>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="space-y-2">
                        <Label for="description">Description</Label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Additional notes about this certification"
                            rows="2"
                        />
                    </div>

                    <div
                        class="rounded-md bg-blue-50 p-3 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"
                    >
                        <strong>Note:</strong> After saving, you must upload at
                        least one certificate file before submitting for
                        approval.
                    </div>

                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeFormModal"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isSubmitting">
                            <svg
                                v-if="isSubmitting"
                                class="mr-2 h-4 w-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            {{
                                isEditing
                                    ? 'Update Certification'
                                    : 'Save Certification'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Files Modal -->
        <Dialog v-model:open="isFilesModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Certificate Files</DialogTitle>
                    <DialogDescription>
                        {{
                            selectedCertification?.can_be_edited
                                ? 'Upload and manage certificate files.'
                                : 'View attached certificate files.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Upload Button (only for draft status) -->
                    <div
                        v-if="selectedCertification?.can_be_edited"
                        class="flex items-center gap-3"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            class="hidden"
                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            @change="handleFileUpload"
                        />
                        <Button
                            variant="outline"
                            @click="triggerFileUpload"
                            :disabled="uploadingFile"
                        >
                            <svg
                                v-if="uploadingFile"
                                class="mr-2 h-4 w-4 animate-spin"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                            <svg
                                v-else
                                class="mr-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"
                                />
                            </svg>
                            {{ uploadingFile ? 'Uploading...' : 'Upload File' }}
                        </Button>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            PDF, JPG, PNG, DOC, DOCX
                        </span>
                    </div>

                    <!-- File List -->
                    <div class="space-y-2">
                        <div
                            v-for="file in selectedCertification?.files"
                            :key="file.id"
                            class="flex items-center justify-between rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div class="flex items-center gap-3">
                                <svg
                                    class="h-8 w-8 text-slate-400"
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
                                <div>
                                    <div
                                        class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ file.original_filename }}
                                    </div>
                                    <div
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ file.file_size_formatted }}
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="
                                        downloadFile(
                                            selectedCertification!.id,
                                            file.id,
                                        )
                                    "
                                >
                                    Download
                                </Button>
                                <Button
                                    v-if="selectedCertification?.can_be_edited"
                                    variant="outline"
                                    size="sm"
                                    class="text-red-600 hover:text-red-700"
                                    @click="
                                        handleDeleteFile(
                                            selectedCertification!.id,
                                            file.id,
                                        )
                                    "
                                >
                                    Delete
                                </Button>
                            </div>
                        </div>
                        <div
                            v-if="
                                !selectedCertification?.files ||
                                selectedCertification.files.length === 0
                            "
                            class="rounded-lg border-2 border-dashed border-slate-200 py-8 text-center dark:border-slate-700"
                        >
                            <svg
                                class="mx-auto h-10 w-10 text-slate-400"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                                />
                            </svg>
                            <p
                                class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                            >
                                No files attached yet.
                            </p>
                            <p
                                v-if="selectedCertification?.can_be_edited"
                                class="text-xs text-slate-400 dark:text-slate-500"
                            >
                                Upload your certificate file to submit for
                                approval.
                            </p>
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button @click="isFilesModalOpen = false"> Close </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
