<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { PreboardingChecklistItem } from '@/types/preboarding';
import { router } from '@inertiajs/vue3';
import {
    CheckCircle,
    Clock,
    FileUp,
    FormInput,
    HandMetal,
    RotateCcw,
    XCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    item: PreboardingChecklistItem;
    mode: 'employee' | 'reviewer';
}>();

const emit = defineEmits<{
    (e: 'approve', itemId: number): void;
    (e: 'reject', itemId: number): void;
}>();

const formValue = ref(props.item.form_value ?? '');
const selectedFile = ref<File | null>(null);
const submitting = ref(false);

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    selectedFile.value = target.files?.[0] ?? null;
}

function submitItem() {
    if (submitting.value) return;
    submitting.value = true;

    const formData = new FormData();

    if (props.item.type === 'document_upload' && selectedFile.value) {
        formData.append('file', selectedFile.value);
    } else if (props.item.type === 'form_field') {
        formData.append('form_value', formValue.value);
    }

    router.post(`/api/preboarding-items/${props.item.id}/submit`, formData, {
        forceFormData: true,
        preserveState: true,
        onFinish: () => {
            submitting.value = false;
        },
        onSuccess: () => {
            router.reload({ only: ['checklist'] });
        },
    });
}

const typeIcon = {
    document_upload: FileUp,
    form_field: FormInput,
    acknowledgment: HandMetal,
};
</script>

<template>
    <div
        class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
        :class="{ 'opacity-60': item.status === 'approved' }"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <component
                    :is="typeIcon[item.type]"
                    class="mt-0.5 h-5 w-5 shrink-0 text-slate-400"
                />
                <div>
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-slate-900 dark:text-slate-100">
                            {{ item.name }}
                        </h4>
                        <span
                            v-if="item.is_required"
                            class="text-xs text-red-500"
                        >Required</span>
                    </div>
                    <p
                        v-if="item.description"
                        class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ item.description }}
                    </p>
                </div>
            </div>
            <span
                class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                :class="badgeClasses(item.status_color)"
            >
                {{ item.status_label }}
            </span>
        </div>

        <!-- Rejection reason -->
        <div
            v-if="item.status === 'rejected' && item.rejection_reason"
            class="mt-3 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
        >
            <strong>Revision needed:</strong> {{ item.rejection_reason }}
        </div>

        <!-- Employee actions -->
        <div
            v-if="mode === 'employee' && (item.status === 'pending' || item.status === 'rejected')"
            class="mt-3"
        >
            <!-- Document upload -->
            <div v-if="item.type === 'document_upload'" class="flex items-center gap-3">
                <input
                    type="file"
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx"
                    class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-blue-700 hover:file:bg-blue-100 dark:text-slate-400 dark:file:bg-blue-900/30 dark:file:text-blue-400"
                    @change="onFileChange"
                />
                <Button
                    size="sm"
                    :disabled="!selectedFile || submitting"
                    @click="submitItem"
                >
                    {{ submitting ? 'Uploading...' : 'Upload' }}
                </Button>
            </div>

            <!-- Form field -->
            <div v-else-if="item.type === 'form_field'" class="flex items-center gap-3">
                <input
                    v-model="formValue"
                    type="text"
                    class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    :placeholder="`Enter ${item.name}`"
                />
                <Button
                    size="sm"
                    :disabled="!formValue.trim() || submitting"
                    @click="submitItem"
                >
                    {{ submitting ? 'Saving...' : 'Submit' }}
                </Button>
            </div>

            <!-- Acknowledgment -->
            <div v-else-if="item.type === 'acknowledgment'">
                <Button
                    size="sm"
                    :disabled="submitting"
                    @click="submitItem"
                >
                    <CheckCircle class="mr-1.5 h-4 w-4" />
                    {{ submitting ? 'Acknowledging...' : 'I Acknowledge' }}
                </Button>
            </div>
        </div>

        <!-- Submitted form value display -->
        <div
            v-if="item.type === 'form_field' && item.form_value && item.status !== 'pending'"
            class="mt-3 rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-300"
        >
            <strong>Value:</strong> {{ item.form_value }}
        </div>

        <!-- Document preview for reviewer -->
        <div
            v-if="mode === 'reviewer' && item.document"
            class="mt-3 text-sm text-blue-600 dark:text-blue-400"
        >
            <a
                v-if="item.document.url"
                :href="item.document.url"
                target="_blank"
                class="underline"
            >
                View: {{ item.document.original_filename }}
            </a>
            <span v-else>{{ item.document.original_filename }}</span>
        </div>

        <!-- Reviewer actions -->
        <div
            v-if="mode === 'reviewer' && item.status === 'submitted'"
            class="mt-3 flex items-center gap-2"
        >
            <Button
                size="sm"
                variant="default"
                @click="emit('approve', item.id)"
            >
                <CheckCircle class="mr-1.5 h-4 w-4" />
                Approve
            </Button>
            <Button
                size="sm"
                variant="destructive"
                @click="emit('reject', item.id)"
            >
                <XCircle class="mr-1.5 h-4 w-4" />
                Reject
            </Button>
        </div>

        <!-- Timestamps -->
        <div
            v-if="item.submitted_at || item.reviewed_at"
            class="mt-2 text-xs text-slate-400"
        >
            <span v-if="item.submitted_at">Submitted: {{ item.submitted_at }}</span>
            <span v-if="item.reviewed_at" class="ml-3">Reviewed: {{ item.reviewed_at }}</span>
        </div>
    </div>
</template>
