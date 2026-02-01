<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    FileText,
    Loader2,
    Plus,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

interface DocumentRequest {
    id: number;
    employee_id: number;
    document_type: string;
    document_type_label: string;
    status: string;
    status_label: string;
    status_color: string;
    notes: string | null;
    admin_notes: string | null;
    processed_at: string | null;
    collected_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface DocumentType {
    value: string;
    label: string;
}

const props = defineProps<{
    hasEmployeeProfile: boolean;
    documentRequests: DocumentRequest[];
    documentTypes: DocumentType[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'My Services', href: '/my/document-requests' },
    { title: 'Document Requests', href: '/my/document-requests' },
];

const dialogOpen = ref(false);
const submitting = ref(false);
const selectedType = ref('');
const notes = ref('');
const errors = ref<Record<string, string>>({});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function statusColorClass(color: string): string {
    const colorMap: Record<string, string> = {
        amber: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-900/30 dark:text-amber-400 dark:border-amber-800',
        blue: 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800',
        green: 'bg-green-100 text-green-800 border-green-200 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800',
        slate: 'bg-slate-100 text-slate-800 border-slate-200 dark:bg-slate-900/30 dark:text-slate-400 dark:border-slate-700',
    };
    return colorMap[color] || colorMap.slate;
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

async function submitRequest() {
    submitting.value = true;
    errors.value = {};

    try {
        const response = await fetch('/api/document-requests', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                document_type: selectedType.value,
                notes: notes.value || null,
            }),
        });

        if (!response.ok) {
            const data = await response.json();
            if (response.status === 422 && data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors).map(([key, val]) => [
                        key,
                        Array.isArray(val) ? val[0] : val,
                    ]),
                ) as Record<string, string>;
                return;
            }
            throw new Error(data.message || 'Failed to submit request');
        }

        dialogOpen.value = false;
        selectedType.value = '';
        notes.value = '';
        router.reload();
    } catch (error) {
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Document Requests - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        My Document Requests
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Request documents such as certificates of employment, payslip
                        copies, and more.
                    </p>
                </div>

                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button v-if="hasEmployeeProfile">
                            <Plus class="mr-2 h-4 w-4" />
                            New Request
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>New Document Request</DialogTitle>
                            <DialogDescription>
                                Select the type of document you need and provide any
                                additional notes.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="flex flex-col gap-4 py-4">
                            <div class="flex flex-col gap-2">
                                <Label for="document_type">Document Type</Label>
                                <Select v-model="selectedType">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select document type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="type in documentTypes"
                                            :key="type.value"
                                            :value="type.value"
                                        >
                                            {{ type.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p
                                    v-if="errors.document_type"
                                    class="text-sm text-red-500"
                                >
                                    {{ errors.document_type }}
                                </p>
                            </div>

                            <div class="flex flex-col gap-2">
                                <Label for="notes">Notes (optional)</Label>
                                <Textarea
                                    id="notes"
                                    v-model="notes"
                                    placeholder="Any additional details or special instructions..."
                                    :rows="3"
                                />
                                <p v-if="errors.notes" class="text-sm text-red-500">
                                    {{ errors.notes }}
                                </p>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                :disabled="submitting"
                                @click="submitRequest"
                            >
                                <Loader2
                                    v-if="submitting"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                {{ submitting ? 'Submitting...' : 'Submit Request' }}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
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

            <!-- Requests Table -->
            <Card v-if="hasEmployeeProfile && documentRequests.length > 0">
                <CardHeader>
                    <CardTitle class="text-base">Request History</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b border-slate-200 dark:border-slate-700"
                                >
                                    <th
                                        class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Document Type
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Notes
                                    </th>
                                    <th
                                        class="px-4 py-3 text-left font-medium text-slate-500 dark:text-slate-400"
                                    >
                                        Submitted
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="request in documentRequests"
                                    :key="request.id"
                                    class="border-b border-slate-100 dark:border-slate-800"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ request.document_type_label }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <Badge
                                            variant="outline"
                                            :class="
                                                statusColorClass(request.status_color)
                                            "
                                        >
                                            {{ request.status_label }}
                                        </Badge>
                                    </td>
                                    <td
                                        class="max-w-xs truncate px-4 py-3 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ request.notes || '-' }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-slate-500 dark:text-slate-400"
                                    >
                                        {{ formatDate(request.created_at) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty State -->
            <Card
                v-if="hasEmployeeProfile && documentRequests.length === 0"
            >
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <FileText class="h-12 w-12 text-slate-300 dark:text-slate-600" />
                    <h3
                        class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No Document Requests
                    </h3>
                    <p
                        class="mt-2 max-w-md text-center text-sm text-slate-500 dark:text-slate-400"
                    >
                        You haven't submitted any document requests yet. Click "New
                        Request" to get started.
                    </p>
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
