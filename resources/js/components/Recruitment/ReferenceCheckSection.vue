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

interface ReferenceCheck {
    id: number;
    referee_name: string;
    referee_email: string | null;
    referee_phone: string | null;
    referee_company: string | null;
    relationship: string | null;
    contacted: boolean;
    contacted_at: string | null;
    feedback: string | null;
    recommendation: string | null;
    recommendation_label: string | null;
    recommendation_color: string | null;
    notes: string | null;
}

interface RecommendationOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    referenceChecks: ReferenceCheck[];
    applicationId: number;
    referenceRecommendations: RecommendationOption[];
}>();

function getBadgeClasses(color: string): string {
    const colorMap: Record<string, string> = {
        green: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
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
    referee_name: '',
    referee_email: '',
    referee_phone: '',
    referee_company: '',
    relationship: '',
    feedback: '',
    recommendation: '',
    notes: '',
});

function openCreateForm() {
    editingId.value = null;
    form.value = { referee_name: '', referee_email: '', referee_phone: '', referee_company: '', relationship: '', feedback: '', recommendation: '', notes: '' };
    showForm.value = true;
}

function openEditForm(rc: ReferenceCheck) {
    editingId.value = rc.id;
    form.value = {
        referee_name: rc.referee_name,
        referee_email: rc.referee_email ?? '',
        referee_phone: rc.referee_phone ?? '',
        referee_company: rc.referee_company ?? '',
        relationship: rc.relationship ?? '',
        feedback: rc.feedback ?? '',
        recommendation: rc.recommendation ?? '',
        notes: rc.notes ?? '',
    };
    showForm.value = true;
}

async function submitForm() {
    if (isProcessing.value) return;
    isProcessing.value = true;

    try {
        const url = editingId.value
            ? `/api/reference-checks/${editingId.value}`
            : `/api/applications/${props.applicationId}/reference-checks`;
        const method = editingId.value ? 'PUT' : 'POST';

        const body: Record<string, unknown> = { ...form.value };
        for (const key of ['referee_email', 'referee_phone', 'referee_company', 'relationship', 'feedback', 'recommendation', 'notes']) {
            if (!body[key]) body[key] = null;
        }

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

async function deleteReference(id: number) {
    if (!confirm('Delete this reference check?')) return;
    await fetch(`/api/reference-checks/${id}`, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
    });
    router.reload();
}

async function markContacted(id: number) {
    await fetch(`/api/reference-checks/${id}/mark-contacted`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-XSRF-TOKEN': getCsrfToken() },
        credentials: 'same-origin',
    });
    router.reload();
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Reference Checks</h2>
            <Button size="sm" @click="openCreateForm">Add Reference</Button>
        </div>

        <div v-if="referenceChecks.length" class="space-y-3">
            <div v-for="rc in referenceChecks" :key="rc.id" class="rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ rc.referee_name }}</span>
                            <span v-if="rc.contacted" class="inline-flex rounded-md bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-300">Contacted</span>
                            <span v-else class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700/50 dark:text-slate-300">Not Contacted</span>
                            <span v-if="rc.recommendation_label" class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getBadgeClasses(rc.recommendation_color ?? 'slate')">
                                {{ rc.recommendation_label }}
                            </span>
                        </div>
                        <div class="mt-1 flex gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span v-if="rc.referee_company">{{ rc.referee_company }}</span>
                            <span v-if="rc.relationship">{{ rc.relationship }}</span>
                            <span v-if="rc.referee_email">{{ rc.referee_email }}</span>
                            <span v-if="rc.referee_phone">{{ rc.referee_phone }}</span>
                        </div>
                        <p v-if="rc.feedback" class="mt-2 text-xs text-slate-600 dark:text-slate-300">{{ rc.feedback }}</p>
                        <p v-if="rc.notes" class="mt-1 text-xs text-slate-500">{{ rc.notes }}</p>
                    </div>
                    <div class="flex gap-1">
                        <button v-if="!rc.contacted" @click="markContacted(rc.id)" class="text-xs text-emerald-600 hover:text-emerald-800 dark:text-emerald-400">Mark Contacted</button>
                        <button @click="openEditForm(rc)" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400">Edit</button>
                        <button @click="deleteReference(rc.id)" class="text-xs text-red-600 hover:text-red-800 dark:text-red-400">Delete</button>
                    </div>
                </div>
            </div>
        </div>
        <p v-else class="text-sm text-slate-500 dark:text-slate-400">No reference checks recorded.</p>

        <Dialog v-model:open="showForm">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit' : 'Add' }} Reference Check</DialogTitle>
                    <DialogDescription>Track a referee and their feedback.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Referee Name *</label>
                        <input v-model="form.referee_name" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                            <input v-model="form.referee_email" type="email" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                            <input v-model="form.referee_phone" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Company</label>
                            <input v-model="form.referee_company" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Relationship</label>
                            <input v-model="form.relationship" type="text" placeholder="e.g. Manager, Colleague" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Recommendation</label>
                        <select v-model="form.recommendation" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                            <option value="">None</option>
                            <option v-for="opt in referenceRecommendations" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Feedback</label>
                        <textarea v-model="form.feedback" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showForm = false">Cancel</Button>
                    <Button @click="submitForm" :disabled="isProcessing || !form.referee_name">
                        {{ isProcessing ? 'Saving...' : 'Save' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
