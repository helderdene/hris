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
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    Key,
    Laptop,
    Users,
    GraduationCap,
    AlertCircle,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface OnboardingItem {
    id: number;
    category: string;
    category_label: string;
    category_icon: string;
    name: string;
    description: string | null;
    assigned_role: string;
    assigned_role_label: string;
    assigned_role_color: string;
    assigned_to: { id: number; name: string } | null;
    is_required: boolean;
    due_date: string | null;
    is_overdue: boolean;
    status: string;
    status_label: string;
    status_color: string;
    notes: string | null;
    equipment_details: Record<string, unknown> | null;
    completed_at: string | null;
    completed_by: string | null;
}

interface CategoryGroup {
    category: string;
    category_label: string;
    category_icon: string;
    items: OnboardingItem[];
}

interface Employee {
    id: number;
    full_name: string;
    employee_number: string;
    email: string;
    department: string | null;
    position: string | null;
    hire_date: string | null;
}

interface OnboardingChecklist {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    completed_at: string | null;
    progress_percentage: number;
    employee: Employee | null;
    template_name: string | null;
    created_at: string | null;
    items: OnboardingItem[];
    items_by_category: CategoryGroup[];
}

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

const props = defineProps<{
    checklist: OnboardingChecklist;
    itemStatuses: StatusOption[];
    categories: { value: string; label: string }[];
    roles: { value: string; label: string; color: string }[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Onboarding', href: '/onboarding' },
    { title: props.checklist.employee?.full_name ?? 'Checklist', href: `/onboarding/${props.checklist.id}` },
];

const processing = ref(false);
const completeDialogOpen = ref(false);
const selectedItemId = ref<number | null>(null);
const notes = ref('');
const equipmentDetails = ref({
    model: '',
    serial_number: '',
    asset_tag: '',
});

const skipDialogOpen = ref(false);
const skipReason = ref('');

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        orange: 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400',
        purple: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[color] ?? map.slate;
}

function getCategoryIcon(icon: string) {
    const icons: Record<string, unknown> = {
        key: Key,
        'computer-desktop': Laptop,
        'user-group': Users,
        'academic-cap': GraduationCap,
    };
    return icons[icon] ?? Key;
}

function openCompleteDialog(item: OnboardingItem) {
    selectedItemId.value = item.id;
    notes.value = '';
    equipmentDetails.value = { model: '', serial_number: '', asset_tag: '' };
    completeDialogOpen.value = true;
}

function confirmComplete() {
    if (!selectedItemId.value) return;
    processing.value = true;

    const data: Record<string, unknown> = {};
    if (notes.value.trim()) data.notes = notes.value;
    if (equipmentDetails.value.model || equipmentDetails.value.serial_number || equipmentDetails.value.asset_tag) {
        data.equipment_details = equipmentDetails.value;
    }

    router.post(`/api/onboarding-items/${selectedItemId.value}/complete`, data, {
        preserveState: true,
        onFinish: () => {
            processing.value = false;
            completeDialogOpen.value = false;
        },
        onSuccess: () => {
            router.reload({ only: ['checklist'] });
        },
    });
}

function openSkipDialog(item: OnboardingItem) {
    selectedItemId.value = item.id;
    skipReason.value = '';
    skipDialogOpen.value = true;
}

function confirmSkip() {
    if (!selectedItemId.value || !skipReason.value.trim()) return;
    processing.value = true;

    router.post(`/api/onboarding-items/${selectedItemId.value}/skip`, {
        reason: skipReason.value,
    }, {
        preserveState: true,
        onFinish: () => {
            processing.value = false;
            skipDialogOpen.value = false;
        },
        onSuccess: () => {
            router.reload({ only: ['checklist'] });
        },
    });
}
</script>

<template>
    <Head :title="`${checklist.employee?.full_name ?? 'Onboarding'} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <Button
                    variant="ghost"
                    size="sm"
                    class="mb-3"
                    @click="router.visit('/onboarding')"
                >
                    <ArrowLeft class="mr-1.5 h-4 w-4" />
                    Back to list
                </Button>
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {{ checklist.employee?.full_name }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ checklist.employee?.employee_number }}
                            <span v-if="checklist.employee?.position"> &middot; {{ checklist.employee.position }}</span>
                            <span v-if="checklist.employee?.department"> &middot; {{ checklist.employee.department }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <Button
                            variant="outline"
                            @click="router.visit(`/employees/${checklist.employee?.id}`)"
                        >
                            View Employee
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

            <!-- Progress Card -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Onboarding Progress</CardTitle>
                    <CardDescription v-if="checklist.start_date">
                        Start Date: {{ checklist.start_date }}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4">
                        <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="checklist.progress_percentage === 100 ? 'bg-green-500' : 'bg-blue-500'"
                                :style="{ width: `${checklist.progress_percentage}%` }"
                            />
                        </div>
                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ checklist.progress_percentage }}%
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Items by Category -->
            <div class="space-y-6">
                <Card v-for="group in checklist.items_by_category" :key="group.category">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <component :is="getCategoryIcon(group.category_icon)" class="h-5 w-5" />
                            {{ group.category_label }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="item in group.items"
                            :key="item.id"
                            class="flex items-start justify-between rounded-lg border p-4"
                            :class="[
                                item.status === 'completed' ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/20' :
                                item.status === 'skipped' ? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50' :
                                item.is_overdue ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-900/20' :
                                'border-slate-200 dark:border-slate-700'
                            ]"
                        >
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <CheckCircle2
                                        v-if="item.status === 'completed'"
                                        class="h-5 w-5 text-green-500"
                                    />
                                    <Clock
                                        v-else-if="item.status === 'in_progress'"
                                        class="h-5 w-5 text-amber-500"
                                    />
                                    <AlertCircle
                                        v-else-if="item.is_overdue"
                                        class="h-5 w-5 text-red-500"
                                    />
                                    <div
                                        v-else
                                        class="h-5 w-5 rounded-full border-2 border-slate-300 dark:border-slate-600"
                                    />
                                    <span class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ item.name }}
                                    </span>
                                    <span
                                        v-if="!item.is_required"
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                                    >
                                        Optional
                                    </span>
                                </div>
                                <p v-if="item.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ item.description }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 font-medium"
                                        :class="badgeClasses(item.assigned_role_color)"
                                    >
                                        {{ item.assigned_role_label }}
                                    </span>
                                    <span v-if="item.assigned_to" class="text-slate-500 dark:text-slate-400">
                                        Assigned to: {{ item.assigned_to.name }}
                                    </span>
                                    <span v-if="item.due_date" class="text-slate-500 dark:text-slate-400">
                                        Due: {{ item.due_date }}
                                    </span>
                                    <span
                                        v-if="item.completed_at"
                                        class="text-green-600 dark:text-green-400"
                                    >
                                        Completed: {{ item.completed_at }}
                                        <span v-if="item.completed_by">by {{ item.completed_by }}</span>
                                    </span>
                                </div>
                                <p v-if="item.notes" class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                    Notes: {{ item.notes }}
                                </p>
                                <div v-if="item.equipment_details" class="mt-2 text-sm">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">Equipment:</span>
                                    <span v-if="item.equipment_details.model" class="ml-2 text-slate-500">
                                        {{ item.equipment_details.model }}
                                    </span>
                                    <span v-if="item.equipment_details.serial_number" class="ml-2 text-slate-500">
                                        S/N: {{ item.equipment_details.serial_number }}
                                    </span>
                                    <span v-if="item.equipment_details.asset_tag" class="ml-2 text-slate-500">
                                        Asset: {{ item.equipment_details.asset_tag }}
                                    </span>
                                </div>
                            </div>
                            <div v-if="item.status === 'pending' || item.status === 'in_progress'" class="flex gap-2">
                                <Button
                                    size="sm"
                                    @click="openCompleteDialog(item)"
                                >
                                    Complete
                                </Button>
                                <Button
                                    v-if="!item.is_required"
                                    size="sm"
                                    variant="outline"
                                    @click="openSkipDialog(item)"
                                >
                                    Skip
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Complete Dialog -->
        <Dialog v-model:open="completeDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Complete Item</DialogTitle>
                    <DialogDescription>
                        Add any notes or equipment details if applicable.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                            Notes (optional)
                        </label>
                        <textarea
                            v-model="notes"
                            rows="2"
                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            placeholder="Any additional notes..."
                        />
                    </div>
                    <div class="border-t pt-4">
                        <p class="mb-3 text-sm font-medium text-slate-700 dark:text-slate-300">
                            Equipment Details (for equipment items)
                        </p>
                        <div class="grid grid-cols-3 gap-3">
                            <input
                                v-model="equipmentDetails.model"
                                type="text"
                                placeholder="Model"
                                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                            <input
                                v-model="equipmentDetails.serial_number"
                                type="text"
                                placeholder="Serial Number"
                                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                            <input
                                v-model="equipmentDetails.asset_tag"
                                type="text"
                                placeholder="Asset Tag"
                                class="rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="completeDialogOpen = false">Cancel</Button>
                    <Button
                        :disabled="processing"
                        @click="confirmComplete"
                    >
                        {{ processing ? 'Completing...' : 'Mark Complete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Skip Dialog -->
        <Dialog v-model:open="skipDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Skip Item</DialogTitle>
                    <DialogDescription>
                        Please provide a reason for skipping this item.
                    </DialogDescription>
                </DialogHeader>
                <textarea
                    v-model="skipReason"
                    rows="3"
                    class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                    placeholder="Enter reason for skipping..."
                />
                <DialogFooter>
                    <Button variant="outline" @click="skipDialogOpen = false">Cancel</Button>
                    <Button
                        :disabled="!skipReason.trim() || processing"
                        @click="confirmSkip"
                    >
                        {{ processing ? 'Skipping...' : 'Skip Item' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
