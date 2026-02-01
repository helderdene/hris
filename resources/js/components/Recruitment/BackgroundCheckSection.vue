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
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Document {
    id: number;
    file_name: string;
    file_size: number;
    mime_type: string;
}

interface BackgroundCheck {
    id: number;
    check_type: string;
    status: string;
    status_label: string;
    status_color: string;
    provider: string | null;
    notes: string | null;
    started_at: string | null;
    completed_at: string | null;
    documents: Document[];
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    backgroundChecks: BackgroundCheck[];
    applicationId: number;
    backgroundCheckStatuses: StatusOption[];
}>();

function getStatusBadgeClasses(color: string): string {
    const colorMap: Record<string, string> = {
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300',
    };
    return colorMap[color] || colorMap.slate;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

const showForm = ref(false);
const isProcessing = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    check_type: '',
    status: 'pending',
    provider: '',
    notes: '',
    started_at: '',
    completed_at: '',
});

function openCreateForm() {
    editingId.value = null;
    form.value = { check_type: '', status: 'pending', provider: '', notes: '', started_at: '', completed_at: '' };
    showForm.value = true;
}

function openEditForm(check: BackgroundCheck) {
    editingId.value = check.id;
    form.value = {
        check_type: check.check_type,
        status: check.status,
        provider: check.provider ?? '',
        notes: check.notes ?? '',
        started_at: check.started_at?.split(' ')[0] ?? '',
        completed_at: check.completed_at?.split(' ')[0] ?? '',
    };
    showForm.value = true;
}

async function submitForm() {
    if (isProcessing.value) return;
    isProcessing.value = true;

    try {
        const url = editingId.value
            ? `/api/background-checks/${editingId.value}`
            : `/api/applications/${props.applicationId}/background-checks`;
        const method = editingId.value ? 'PUT' : 'POST';

        const body: Record<string, unknown> = { ...form.value };
        if (!body.started_at) body.started_at = null;
        if (!body.completed_at) body.completed_at = null;
        if (!body.provider) body.provider = null;

        const response = await fetch(url, {
            method,
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (response.ok) {
            showForm.value = false;
            router.reload();
        }
    } finally {
        isProcessing.value = false;
    }
}

async function deleteCheck(id: number) {
    if (!confirm('Delete this background check and all its documents?')) return;
    await fetch(`/api/background-checks/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
    });
    router.reload();
}

async function uploadDocument(checkId: number, event: Event) {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;

    const formData = new FormData();
    formData.append('file', input.files[0]);

    await fetch(`/api/background-checks/${checkId}/documents`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
        body: formData,
    });

    input.value = '';
    router.reload();
}

async function deleteDocument(docId: number) {
    if (!confirm('Delete this document?')) return;
    await fetch(`/api/background-check-documents/${docId}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
    });
    router.reload();
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Background Checks</h2>
            <Button size="sm" @click="openCreateForm">Add Check</Button>
        </div>

        <div v-if="backgroundChecks.length" class="space-y-4">
            <div v-for="check in backgroundChecks" :key="check.id" class="rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ check.check_type }}</span>
                            <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getStatusBadgeClasses(check.status_color)">
                                {{ check.status_label }}
                            </span>
                        </div>
                        <div class="mt-1 flex gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span v-if="check.provider">Provider: {{ check.provider }}</span>
                            <span v-if="check.started_at">Started: {{ check.started_at.split(' ')[0] }}</span>
                            <span v-if="check.completed_at">Completed: {{ check.completed_at.split(' ')[0] }}</span>
                        </div>
                        <p v-if="check.notes" class="mt-1 text-xs text-slate-500">{{ check.notes }}</p>
                    </div>
                    <div class="flex gap-1">
                        <button @click="openEditForm(check)" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">Edit</button>
                        <button @click="deleteCheck(check.id)" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">Delete</button>
                    </div>
                </div>

                <!-- Documents -->
                <div class="mt-3">
                    <div v-if="check.documents.length" class="mb-2 space-y-1">
                        <div v-for="doc in check.documents" :key="doc.id" class="flex items-center justify-between rounded bg-slate-50 px-2 py-1 text-xs dark:bg-slate-800">
                            <span class="text-slate-700 dark:text-slate-300">{{ doc.file_name }} ({{ formatFileSize(doc.file_size) }})</span>
                            <button @click="deleteDocument(doc.id)" class="text-red-500 hover:text-red-700">Remove</button>
                        </div>
                    </div>
                    <label class="cursor-pointer text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400">
                        Upload Document
                        <input type="file" class="hidden" @change="uploadDocument(check.id, $event)" />
                    </label>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No background checks recorded.</p>

        <Dialog v-model:open="showForm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit' : 'Add' }} Background Check</DialogTitle>
                    <DialogDescription>Track a background verification.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Check Type *</label>
                        <input v-model="form.check_type" type="text" placeholder="e.g. Criminal, Employment, Education" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                        <select v-model="form.status" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                            <option v-for="opt in backgroundCheckStatuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Provider</label>
                        <input v-model="form.provider" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Started At</label>
                            <input v-model="form.started_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Completed At</label>
                            <input v-model="form.completed_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showForm = false">Cancel</Button>
                    <Button @click="submitForm" :disabled="isProcessing || !form.check_type">
                        {{ isProcessing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
