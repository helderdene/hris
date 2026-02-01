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
import { ref, watch } from 'vue';

interface TypeOption {
    value: string;
    label: string;
    color: string;
}

interface EmployeeOption {
    id: number;
    full_name: string;
}

interface InterviewData {
    id: number;
    type: string;
    title: string;
    scheduled_at: string;
    duration_minutes: number;
    location: string | null;
    meeting_url: string | null;
    notes: string | null;
    panelists: { employee: { id: number } }[];
}

const props = defineProps<{
    jobApplicationId: number;
    interviewTypes: TypeOption[];
    employees: EmployeeOption[];
    interview?: InterviewData;
}>();

const open = defineModel<boolean>('open', { default: false });

const isEditing = !!props.interview;

const form = ref({
    type: props.interview?.type ?? 'video_interview',
    title: props.interview?.title ?? '',
    scheduled_at: props.interview?.scheduled_at ? props.interview.scheduled_at.replace(' ', 'T').slice(0, 16) : '',
    duration_minutes: props.interview?.duration_minutes ?? 60,
    location: props.interview?.location ?? '',
    meeting_url: props.interview?.meeting_url ?? '',
    notes: props.interview?.notes ?? '',
    panelist_ids: props.interview?.panelists?.map((p) => p.employee.id) ?? ([] as number[]),
    lead_panelist_id: null as number | null,
});

const errors = ref<Record<string, string>>({});
const isProcessing = ref(false);

watch(open, (val) => {
    if (val && !isEditing) {
        form.value = {
            type: 'video_interview',
            title: '',
            scheduled_at: '',
            duration_minutes: 60,
            location: '',
            meeting_url: '',
            notes: '',
            panelist_ids: [],
            lead_panelist_id: null,
        };
        errors.value = {};
    }
});

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function togglePanelist(id: number) {
    const idx = form.value.panelist_ids.indexOf(id);
    if (idx === -1) {
        form.value.panelist_ids.push(id);
    } else {
        form.value.panelist_ids.splice(idx, 1);
        if (form.value.lead_panelist_id === id) {
            form.value.lead_panelist_id = null;
        }
    }
}

async function submit() {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    errors.value = {};

    const url = isEditing
        ? `/api/interviews/${props.interview!.id}`
        : `/api/applications/${props.jobApplicationId}/interviews`;

    const method = isEditing ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                ...form.value,
                location: form.value.location || null,
                meeting_url: form.value.meeting_url || null,
                notes: form.value.notes || null,
            }),
        });

        if (response.ok) {
            open.value = false;
            router.reload();
        } else if (response.status === 422) {
            const data = await response.json();
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors).map(([k, v]) => [k, (v as string[])[0]]),
                );
            }
        }
    } catch {
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ isEditing ? 'Edit Interview' : 'Schedule Interview' }}</DialogTitle>
                <DialogDescription>{{ isEditing ? 'Update interview details.' : 'Schedule a new interview for this application.' }}</DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-4 py-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Type *</label>
                    <select v-model="form.type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <option v-for="t in interviewTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                    </select>
                    <p v-if="errors.type" class="mt-1 text-xs text-red-500">{{ errors.type }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Title *</label>
                    <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="e.g. Technical Interview - Round 1" />
                    <p v-if="errors.title" class="mt-1 text-xs text-red-500">{{ errors.title }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Date & Time *</label>
                        <input v-model="form.scheduled_at" type="datetime-local" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        <p v-if="errors.scheduled_at" class="mt-1 text-xs text-red-500">{{ errors.scheduled_at }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Duration (min) *</label>
                        <input v-model.number="form.duration_minutes" type="number" min="15" max="480" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        <p v-if="errors.duration_minutes" class="mt-1 text-xs text-red-500">{{ errors.duration_minutes }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Location</label>
                    <input v-model="form.location" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="e.g. Conference Room A" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Meeting URL</label>
                    <input v-model="form.meeting_url" type="url" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="https://zoom.us/j/..." />
                    <p v-if="errors.meeting_url" class="mt-1 text-xs text-red-500">{{ errors.meeting_url }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Panelists</label>
                    <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-300 bg-white p-2 dark:border-slate-600 dark:bg-slate-800">
                        <label
                            v-for="emp in employees"
                            :key="emp.id"
                            class="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-slate-50 dark:hover:bg-slate-700"
                        >
                            <input type="checkbox" :checked="form.panelist_ids.includes(emp.id)" @change="togglePanelist(emp.id)" class="rounded border-slate-300" />
                            <span class="text-slate-700 dark:text-slate-300">{{ emp.full_name }}</span>
                            <button
                                v-if="form.panelist_ids.includes(emp.id)"
                                type="button"
                                @click.stop="form.lead_panelist_id = form.lead_panelist_id === emp.id ? null : emp.id"
                                class="ml-auto text-xs"
                                :class="form.lead_panelist_id === emp.id ? 'text-blue-600 font-semibold' : 'text-slate-400 hover:text-blue-500'"
                            >
                                {{ form.lead_panelist_id === emp.id ? 'Lead' : 'Set lead' }}
                            </button>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Notes</label>
                    <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Optional notes..."></textarea>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Cancel</Button>
                    <Button type="submit" :disabled="isProcessing">
                        {{ isProcessing ? 'Saving...' : (isEditing ? 'Update' : 'Schedule') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
