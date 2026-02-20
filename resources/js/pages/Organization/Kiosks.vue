<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Check,
    ClipboardCopy,
    ExternalLink,
    Plus,
    RefreshCw,
    Trash2,
    X,
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';

interface WorkLocationOption {
    id: number;
    name: string;
    code: string;
}

interface KioskData {
    id: number;
    name: string;
    token: string;
    location: string | null;
    work_location_id: number;
    ip_whitelist: string[] | null;
    settings: { cooldown_minutes?: number } | null;
    is_active: boolean;
    last_activity_at: string | null;
    last_activity_human: string | null;
    cooldown_minutes: number;
    kiosk_url: string;
    work_location: { id: number; name: string; code: string } | null;
    created_at: string;
}

const props = withDefaults(defineProps<{
    workLocations?: WorkLocationOption[];
}>(), {
    workLocations: () => [],
});

defineOptions({ layout: TenantLayout });

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Organization', href: '/organization/departments' },
    { title: 'Kiosks', href: '/organization/kiosks' },
];

const kiosks = ref<KioskData[]>([]);
const loading = ref(true);
const showForm = ref(false);
const editingKiosk = ref<KioskData | null>(null);
const formLoading = ref(false);
const formError = ref('');
const copiedId = ref<number | null>(null);

const form = ref({
    name: '',
    work_location_id: '' as string | number,
    location: '',
    ip_whitelist: '',
    cooldown_minutes: 5,
    is_active: true,
});

async function fetchKiosks(): Promise<void> {
    loading.value = true;
    try {
        const response = await axios.get('/api/organization/kiosks');
        kiosks.value = response.data?.data ?? response.data ?? [];
    } catch {
        kiosks.value = [];
    } finally {
        loading.value = false;
    }
}

function openCreateForm(): void {
    editingKiosk.value = null;
    form.value = { name: '', work_location_id: '', location: '', ip_whitelist: '', cooldown_minutes: 5, is_active: true };
    formError.value = '';
    showForm.value = true;
}

function openEditForm(kiosk: KioskData): void {
    editingKiosk.value = kiosk;
    form.value = {
        name: kiosk.name,
        work_location_id: kiosk.work_location_id,
        location: kiosk.location || '',
        ip_whitelist: (kiosk.ip_whitelist || []).join(', '),
        cooldown_minutes: kiosk.cooldown_minutes,
        is_active: kiosk.is_active,
    };
    formError.value = '';
    showForm.value = true;
}

async function submitForm(): Promise<void> {
    formLoading.value = true;
    formError.value = '';

    const payload = {
        name: form.value.name,
        work_location_id: Number(form.value.work_location_id),
        location: form.value.location || null,
        ip_whitelist: form.value.ip_whitelist ? form.value.ip_whitelist.split(',').map((s: string) => s.trim()).filter(Boolean) : null,
        settings: { cooldown_minutes: form.value.cooldown_minutes },
        is_active: form.value.is_active,
    };

    try {
        if (editingKiosk.value) {
            await axios.put(`/api/organization/kiosks/${editingKiosk.value.id}`, payload);
        } else {
            await axios.post('/api/organization/kiosks', payload);
        }
        showForm.value = false;
        await fetchKiosks();
    } catch (error: any) {
        formError.value = error.response?.data?.message || 'An error occurred.';
    } finally {
        formLoading.value = false;
    }
}

async function deleteKiosk(kiosk: KioskData): Promise<void> {
    if (!confirm(`Delete kiosk "${kiosk.name}"? This cannot be undone.`)) {
        return;
    }

    try {
        await axios.delete(`/api/organization/kiosks/${kiosk.id}`);
        await fetchKiosks();
    } catch (error: any) {
        alert(error.response?.data?.message || 'Failed to delete kiosk.');
    }
}

async function regenerateToken(kiosk: KioskData): Promise<void> {
    if (!confirm('Regenerate token? The current kiosk URL will stop working.')) {
        return;
    }

    try {
        await axios.post(`/api/organization/kiosks/${kiosk.id}/regenerate-token`);
        await fetchKiosks();
    } catch (error: any) {
        alert(error.response?.data?.message || 'Failed to regenerate token.');
    }
}

function copyUrl(kiosk: KioskData): void {
    navigator.clipboard.writeText(kiosk.kiosk_url);
    copiedId.value = kiosk.id;
    setTimeout(() => { copiedId.value = null; }, 2000);
}

onMounted(fetchKiosks);
</script>

<template>
    <Head :title="`Kiosks - ${tenantName}`" />

    <div class="flex flex-col gap-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">Kiosks</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Manage shared attendance terminals for PIN-based clock-in/out.
                </p>
            </div>
            <Button @click="openCreateForm">
                <Plus class="mr-2 h-4 w-4" />
                Add Kiosk
            </Button>
        </div>

        <!-- Form Modal (inline) -->
        <div v-if="showForm" class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800">
            <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ editingKiosk ? 'Edit Kiosk' : 'Create Kiosk' }}
            </h2>

            <p v-if="formError" class="mb-4 text-sm text-red-600 dark:text-red-400">{{ formError }}</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name *</label>
                    <input v-model="form.name" type="text" placeholder="e.g., Front Lobby" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Work Location *</label>
                    <select v-model="form.work_location_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">
                        <option value="">Select location...</option>
                        <option v-for="loc in workLocations" :key="loc.id" :value="loc.id">{{ loc.name }} ({{ loc.code }})</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Physical Location</label>
                    <input v-model="form.location" type="text" placeholder="e.g., 2nd floor near entrance" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Cooldown (minutes)</label>
                    <input v-model.number="form.cooldown_minutes" type="number" min="1" max="60" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">IP Whitelist</label>
                    <input v-model="form.ip_whitelist" type="text" placeholder="Comma-separated IPs or CIDRs (e.g., 192.168.1.0/24)" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" />
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300" />
                        Active
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3">
                <Button :disabled="formLoading" @click="submitForm">
                    {{ formLoading ? 'Saving...' : editingKiosk ? 'Update' : 'Create' }}
                </Button>
                <Button variant="outline" @click="showForm = false">Cancel</Button>
            </div>
        </div>

        <!-- Kiosk List -->
        <div v-if="loading" class="py-12 text-center text-slate-500">Loading kiosks...</div>

        <div v-else-if="kiosks.length === 0" class="rounded-lg border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-600">
            <p class="text-slate-500 dark:text-slate-400">No kiosks configured yet.</p>
            <Button class="mt-4" variant="outline" @click="openCreateForm">
                <Plus class="mr-2 h-4 w-4" />
                Add Your First Kiosk
            </Button>
        </div>

        <div v-else class="grid gap-4">
            <div
                v-for="kiosk in kiosks"
                :key="kiosk.id"
                class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-start justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-3">
                            <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ kiosk.name }}</h3>
                            <span
                                :class="[
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    kiosk.is_active
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
                                ]"
                            >
                                {{ kiosk.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p v-if="kiosk.work_location" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            {{ kiosk.work_location.name }}
                            <span v-if="kiosk.location" class="text-slate-400 dark:text-slate-500"> - {{ kiosk.location }}</span>
                        </p>
                        <p v-if="kiosk.last_activity_human" class="mt-1 text-xs text-slate-400 dark:text-slate-500">
                            Last activity: {{ kiosk.last_activity_human }}
                        </p>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-1">
                        <button
                            class="rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                            title="Copy kiosk URL"
                            @click="copyUrl(kiosk)"
                        >
                            <Check v-if="copiedId === kiosk.id" class="h-4 w-4 text-green-500" />
                            <ClipboardCopy v-else class="h-4 w-4" />
                        </button>
                        <a
                            :href="kiosk.kiosk_url"
                            target="_blank"
                            class="rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                            title="Open kiosk"
                        >
                            <ExternalLink class="h-4 w-4" />
                        </a>
                        <button
                            class="rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                            title="Regenerate token"
                            @click="regenerateToken(kiosk)"
                        >
                            <RefreshCw class="h-4 w-4" />
                        </button>
                        <button
                            class="rounded-md p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                            @click="openEditForm(kiosk)"
                        >
                            Edit
                        </button>
                        <button
                            class="rounded-md p-2 text-red-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                            title="Delete"
                            @click="deleteKiosk(kiosk)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
