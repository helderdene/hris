<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
} from '@/components/ui/dialog';
import PreboardingChecklistItemVue from '@/components/preboarding/PreboardingChecklistItem.vue';
import PreboardingProgressBar from '@/components/preboarding/PreboardingProgressBar.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { PreboardingChecklist, StatusOption } from '@/types/preboarding';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, UserPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    checklist: PreboardingChecklist;
    itemStatuses: StatusOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pre-boarding', href: '/preboarding' },
    { title: props.checklist.candidate_name ?? 'Checklist', href: `/preboarding/${props.checklist.id}` },
];

const rejectDialogOpen = ref(false);
const rejectingItemId = ref<number | null>(null);
const rejectionReason = ref('');
const processing = ref(false);

const convertDialogOpen = ref(false);
const converting = ref(false);
const convertError = ref<string | null>(null);

const isCompleted = computed(() => props.checklist.status === 'completed');
const hasEmployee = computed(() => !!props.checklist.employee_id);

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}

function approveItem(itemId: number) {
    processing.value = true;
    router.post(`/api/preboarding-items/${itemId}/approve`, {}, {
        preserveState: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            router.reload({ only: ['checklist'] });
        },
    });
}

function openRejectDialog(itemId: number) {
    rejectingItemId.value = itemId;
    rejectionReason.value = '';
    rejectDialogOpen.value = true;
}

function confirmReject() {
    if (!rejectingItemId.value || !rejectionReason.value.trim()) return;
    processing.value = true;

    router.post(`/api/preboarding-items/${rejectingItemId.value}/reject`, {
        rejection_reason: rejectionReason.value,
    }, {
        preserveState: true,
        onFinish: () => {
            processing.value = false;
            rejectDialogOpen.value = false;
        },
        onSuccess: () => {
            router.reload({ only: ['checklist'] });
        },
    });
}

function openConvertDialog() {
    convertError.value = null;
    convertDialogOpen.value = true;
}

function confirmConvert() {
    converting.value = true;
    convertError.value = null;

    router.post(`/api/preboarding-checklists/${props.checklist.id}/convert-to-employee`, {}, {
        onFinish: () => {
            converting.value = false;
            convertDialogOpen.value = false;
        },
        onError: (errors) => {
            convertError.value = errors.employee || errors.message || 'Failed to convert to employee.';
        },
    });
}
</script>

<template>
    <Head :title="`${checklist.candidate_name ?? 'Pre-boarding'} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <Button
                    variant="ghost"
                    size="sm"
                    class="mb-3"
                    @click="router.visit('/preboarding')"
                >
                    <ArrowLeft class="mr-1.5 h-4 w-4" />
                    Back to list
                </Button>
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {{ checklist.candidate_name }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ checklist.position_title }}
                            <span v-if="checklist.start_date"> &middot; Start: {{ checklist.start_date }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button
                            v-if="hasEmployee"
                            variant="outline"
                            @click="router.visit(`/employees/${checklist.employee_id}`)"
                        >
                            <UserPlus class="mr-1.5 h-4 w-4" />
                            View Employee
                        </Button>
                        <Button
                            v-else-if="isCompleted"
                            @click="openConvertDialog"
                        >
                            <UserPlus class="mr-1.5 h-4 w-4" />
                            Convert to Employee
                        </Button>
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-sm font-medium"
                            :class="badgeClasses(checklist.status_color)"
                        >
                            {{ checklist.status_label }}
                        </span>
                    </div>
                </div>
            </div>

            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Progress</CardTitle>
                    <CardDescription v-if="checklist.deadline">
                        Deadline: {{ checklist.deadline }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <PreboardingProgressBar
                        :percentage="checklist.progress_percentage"
                    />
                </CardContent>
            </Card>

            <div class="flex flex-col gap-3">
                <PreboardingChecklistItemVue
                    v-for="item in checklist.items"
                    :key="item.id"
                    :item="item"
                    mode="reviewer"
                    @approve="approveItem"
                    @reject="openRejectDialog"
                />
            </div>
        </div>

        <!-- Reject Dialog -->
        <Dialog v-model:open="rejectDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Reject Item</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for rejection. The new hire will be notified.
                    </DialogDescription>
                </DialogHeader>
                <textarea
                    v-model="rejectionReason"
                    rows="3"
                    class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    placeholder="Enter rejection reason..."
                />
                <DialogFooter>
                    <Button variant="outline" @click="rejectDialogOpen = false">Cancel</Button>
                    <Button
                        variant="destructive"
                        :disabled="!rejectionReason.trim() || processing"
                        @click="confirmReject"
                    >
                        {{ processing ? 'Rejecting...' : 'Reject' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Convert to Employee Dialog -->
        <Dialog v-model:open="convertDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Convert to Employee</DialogTitle>
                    <DialogDescription>
                        This will create an employee record for <strong>{{ checklist.candidate_name }}</strong> using data from their application and offer.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-3 text-sm text-slate-600 dark:text-slate-400">
                    <p>The following information will be used:</p>
                    <ul class="ml-4 list-disc space-y-1">
                        <li>Personal details from the candidate profile</li>
                        <li>Salary and start date from the offer</li>
                        <li>Department and position (if matching records exist)</li>
                    </ul>
                    <p class="text-slate-500">You can edit the employee details after creation.</p>
                </div>
                <div v-if="convertError" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    {{ convertError }}
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="convertDialogOpen = false">Cancel</Button>
                    <Button
                        :disabled="converting"
                        @click="confirmConvert"
                    >
                        <UserPlus v-if="!converting" class="mr-1.5 h-4 w-4" />
                        {{ converting ? 'Creating...' : 'Create Employee' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
