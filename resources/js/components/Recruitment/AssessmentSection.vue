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

interface Assessment {
    id: number;
    test_name: string;
    type: string;
    type_label: string;
    type_color: string;
    score: number | null;
    max_score: number | null;
    passed: boolean | null;
    assessed_at: string | null;
    notes: string | null;
}

interface TypeOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    assessments: Assessment[];
    applicationId: number;
    assessmentTypes: TypeOption[];
}>();

function getTypeBadgeClasses(color: string): string {
    const colorMap: Record<string, string> = {
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        purple: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        indigo: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
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

const showForm = ref(false);
const isProcessing = ref(false);
const editingId = ref<number | null>(null);

const form = ref({
    test_name: '',
    type: '',
    score: '' as string | number,
    max_score: '' as string | number,
    passed: null as boolean | null,
    assessed_at: '',
    notes: '',
});

function openCreateForm() {
    editingId.value = null;
    form.value = { test_name: '', type: '', score: '', max_score: '', passed: null, assessed_at: '', notes: '' };
    showForm.value = true;
}

function openEditForm(assessment: Assessment) {
    editingId.value = assessment.id;
    form.value = {
        test_name: assessment.test_name,
        type: assessment.type,
        score: assessment.score ?? '',
        max_score: assessment.max_score ?? '',
        passed: assessment.passed,
        assessed_at: assessment.assessed_at?.split(' ')[0] ?? '',
        notes: assessment.notes ?? '',
    };
    showForm.value = true;
}

async function submitForm() {
    if (isProcessing.value) return;
    isProcessing.value = true;

    try {
        const url = editingId.value
            ? `/api/assessments/${editingId.value}`
            : `/api/applications/${props.applicationId}/assessments`;
        const method = editingId.value ? 'PUT' : 'POST';

        const body: Record<string, unknown> = { ...form.value };
        if (body.score === '') body.score = null;
        if (body.max_score === '') body.max_score = null;
        if (!body.assessed_at) body.assessed_at = null;

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

async function deleteAssessment(id: number) {
    if (!confirm('Delete this assessment?')) return;
    await fetch(`/api/assessments/${id}`, {
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
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Assessments</h2>
            <Button size="sm" @click="openCreateForm">Add Assessment</Button>
        </div>

        <div v-if="assessments.length" class="space-y-3">
            <div v-for="assessment in assessments" :key="assessment.id" class="flex items-start justify-between rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-900 dark:text-slate-100">{{ assessment.test_name }}</span>
                        <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getTypeBadgeClasses(assessment.type_color)">
                            {{ assessment.type_label }}
                        </span>
                        <span v-if="assessment.passed !== null" class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="assessment.passed ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300'">
                            {{ assessment.passed ? 'Passed' : 'Failed' }}
                        </span>
                    </div>
                    <div class="mt-1 flex gap-3 text-xs text-slate-500 dark:text-slate-400">
                        <span v-if="assessment.score !== null">Score: {{ assessment.score }}{{ assessment.max_score ? `/${assessment.max_score}` : '' }}</span>
                        <span v-if="assessment.assessed_at">{{ assessment.assessed_at.split(' ')[0] }}</span>
                    </div>
                    <p v-if="assessment.notes" class="mt-1 text-xs text-slate-500">{{ assessment.notes }}</p>
                </div>
                <div class="flex gap-1">
                    <button @click="openEditForm(assessment)" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">Edit</button>
                    <button @click="deleteAssessment(assessment.id)" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">Delete</button>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No assessments recorded.</p>

        <Dialog v-model:open="showForm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit' : 'Add' }} Assessment</DialogTitle>
                    <DialogDescription>Record an assessment result.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Test Name *</label>
                        <input v-model="form.test_name" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Type *</label>
                        <select v-model="form.type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">Select type</option>
                            <option v-for="opt in assessmentTypes" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Score</label>
                            <input v-model="form.score" type="number" step="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Max Score</label>
                            <input v-model="form.max_score" type="number" step="0.01" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Passed</label>
                            <select v-model="form.passed" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                <option :value="null">N/A</option>
                                <option :value="true">Yes</option>
                                <option :value="false">No</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Assessed At</label>
                            <input v-model="form.assessed_at" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showForm = false">Cancel</Button>
                    <Button @click="submitForm" :disabled="isProcessing || !form.test_name || !form.type">
                        {{ isProcessing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
