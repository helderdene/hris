<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Award,
    Book,
    BookOpen,
    Briefcase,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    ExternalLink,
    Folder,
    GraduationCap,
    Loader2,
    Target,
    Users,
    X,
} from 'lucide-vue-next';
import { ref, type Component } from 'vue';

interface Activity {
    id: number;
    activity_type: string;
    activity_type_label: string;
    activity_type_color: string;
    activity_type_icon: string;
    title: string;
    description: string | null;
    resource_url: string | null;
    due_date: string | null;
    is_completed: boolean;
    is_overdue: boolean;
}

interface Item {
    id: number;
    title: string;
    description: string | null;
    current_level: number | null;
    target_level: number | null;
    priority: string;
    priority_label: string;
    priority_color: string;
    status: string;
    status_label: string;
    status_color: string;
    progress_percentage: number;
    competency: {
        id: number;
        name: string;
        code: string;
    } | null;
    activities: Activity[];
}

interface CheckIn {
    id: number;
    check_in_date: string;
    notes: string;
    created_by_user: {
        id: number;
        name: string;
    } | null;
}

interface DevelopmentPlan {
    id: number;
    title: string;
    description: string | null;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    target_completion_date: string | null;
    career_path_notes: string | null;
    approval_notes: string | null;
    progress: number;
    is_overdue: boolean;
    employee: {
        full_name: string;
        position: string | null;
        department: string | null;
    };
    manager: {
        id: number;
        full_name: string;
    } | null;
    items: Item[];
    check_ins: CheckIn[];
}

const props = defineProps<{
    plan: DevelopmentPlan;
    canApprove: boolean;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Performance', href: '#' },
    { title: 'Development Plans', href: '/performance/development-plans' },
    { title: props.plan.title, href: '#' },
];

const expandedItems = ref<Set<number>>(new Set(props.plan.items.map(i => i.id)));

// Approval Modal
const showApprovalModal = ref(false);
const approvalNotes = ref('');
const isApproving = ref(false);
const isRejecting = ref(false);

const activityIcons: Record<string, Component> = {
    'graduation-cap': GraduationCap,
    'users': Users,
    'book-open': BookOpen,
    'briefcase': Briefcase,
    'award': Award,
    'folder': Folder,
};

function toggleItem(itemId: number) {
    if (expandedItems.value.has(itemId)) {
        expandedItems.value.delete(itemId);
    } else {
        expandedItems.value.add(itemId);
    }
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString();
}

async function handleApprove() {
    isApproving.value = true;
    try {
        await axios.post(`/api/performance/development-plans/${props.plan.id}/approve`, {
            notes: approvalNotes.value,
        });
        showApprovalModal.value = false;
        router.reload();
    } catch (error) {
        console.error('Error approving plan:', error);
    } finally {
        isApproving.value = false;
    }
}

async function handleReject() {
    isRejecting.value = true;
    try {
        await axios.post(`/api/performance/development-plans/${props.plan.id}/reject`, {
            notes: approvalNotes.value,
        });
        showApprovalModal.value = false;
        router.reload();
    } catch (error) {
        console.error('Error rejecting plan:', error);
    } finally {
        isRejecting.value = false;
    }
}
</script>

<template>
    <Head :title="`${plan.title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ plan.title }}
                        </h1>
                        <span :class="plan.status_color" class="rounded-full px-2.5 py-0.5 text-sm font-medium">
                            {{ plan.status_label }}
                        </span>
                    </div>
                    <p v-if="plan.description" class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ plan.description }}
                    </p>
                    <div class="mt-3 flex items-center gap-3">
                        <div class="flex items-center gap-2 text-sm">
                            <Users class="h-4 w-4 text-slate-400" />
                            <span class="font-medium text-slate-900 dark:text-slate-100">{{ plan.employee.full_name }}</span>
                            <span v-if="plan.employee.position" class="text-slate-500">- {{ plan.employee.position }}</span>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                        <span v-if="plan.start_date" class="flex items-center gap-1">
                            <Calendar class="h-4 w-4" />
                            Start: {{ formatDate(plan.start_date) }}
                        </span>
                        <span v-if="plan.target_completion_date" class="flex items-center gap-1">
                            <Target class="h-4 w-4" />
                            Target: {{ formatDate(plan.target_completion_date) }}
                        </span>
                        <span v-if="plan.is_overdue" class="flex items-center gap-1 text-red-600 dark:text-red-400">
                            <Clock class="h-4 w-4" />
                            Overdue
                        </span>
                    </div>
                </div>

                <div v-if="canApprove" class="flex items-center gap-3">
                    <Button variant="outline" @click="showApprovalModal = true">
                        Review & Decide
                    </Button>
                </div>
            </div>

            <!-- Progress Overview -->
            <Card>
                <CardContent class="p-6">
                    <div class="flex items-center gap-6">
                        <div class="flex-1">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Overall Progress</span>
                                <span class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ plan.progress.toFixed(0) }}%</span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{ width: `${plan.progress}%` }"
                                />
                            </div>
                        </div>
                        <div class="flex gap-6 border-l border-slate-200 pl-6 dark:border-slate-700">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-slate-900 dark:text-slate-100">{{ plan.items.length }}</div>
                                <div class="text-xs text-slate-500">Items</div>
                            </div>
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ plan.items.filter(i => i.status === 'completed').length }}
                                </div>
                                <div class="text-xs text-slate-500">Completed</div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="lg:col-span-2 flex flex-col gap-6">
                    <!-- Development Items -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Development Items</CardTitle>
                            <CardDescription>Skills and competencies to develop</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="plan.items.length === 0" class="py-8 text-center text-slate-500 dark:text-slate-400">
                                No development items in this plan.
                            </div>

                            <div v-for="item in plan.items" :key="item.id" class="rounded-lg border border-slate-200 dark:border-slate-700">
                                <!-- Item Header -->
                                <div
                                    class="flex cursor-pointer items-center gap-3 p-4"
                                    @click="toggleItem(item.id)"
                                >
                                    <component
                                        :is="expandedItems.has(item.id) ? ChevronDown : ChevronRight"
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                    />
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-medium text-slate-900 dark:text-slate-100">{{ item.title }}</h4>
                                            <span :class="item.status_color" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                                {{ item.status_label }}
                                            </span>
                                            <span :class="item.priority_color" class="rounded-full px-2 py-0.5 text-xs font-medium">
                                                {{ item.priority_label }}
                                            </span>
                                        </div>
                                        <div v-if="item.current_level && item.target_level" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            Level {{ item.current_level }} → {{ item.target_level }}
                                            <span v-if="item.competency" class="ml-2">({{ item.competency.name }})</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ item.progress_percentage }}%</div>
                                        </div>
                                        <div class="h-2 w-20 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div
                                                class="h-full rounded-full bg-indigo-500 transition-all"
                                                :style="{ width: `${item.progress_percentage}%` }"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Item Content (Expanded) -->
                                <div v-if="expandedItems.has(item.id)" class="border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                                    <p v-if="item.description" class="mb-4 text-sm text-slate-600 dark:text-slate-400">
                                        {{ item.description }}
                                    </p>

                                    <!-- Activities -->
                                    <div class="space-y-2">
                                        <h5 class="text-sm font-medium text-slate-700 dark:text-slate-300">Activities</h5>

                                        <div v-if="item.activities.length === 0" class="py-4 text-center text-sm text-slate-500">
                                            No activities yet
                                        </div>

                                        <div
                                            v-for="activity in item.activities"
                                            :key="activity.id"
                                            class="flex items-center gap-3 rounded-lg bg-white p-3 dark:bg-slate-900"
                                        >
                                            <component
                                                :is="activityIcons[activity.activity_type_icon] || Book"
                                                class="h-5 w-5 shrink-0"
                                                :class="activity.is_completed ? 'text-green-500' : 'text-slate-400'"
                                            />
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2">
                                                    <span class="font-medium text-slate-900 dark:text-slate-100" :class="{ 'line-through': activity.is_completed }">
                                                        {{ activity.title }}
                                                    </span>
                                                    <span :class="activity.activity_type_color" class="rounded-full px-2 py-0.5 text-xs">
                                                        {{ activity.activity_type_label }}
                                                    </span>
                                                </div>
                                                <div class="mt-1 flex items-center gap-3 text-xs text-slate-500">
                                                    <span v-if="activity.due_date" :class="{ 'text-red-500': activity.is_overdue }">
                                                        Due: {{ formatDate(activity.due_date) }}
                                                    </span>
                                                    <a v-if="activity.resource_url" :href="activity.resource_url" target="_blank" class="flex items-center gap-1 text-indigo-600 hover:underline">
                                                        <ExternalLink class="h-3 w-3" />
                                                        Resource
                                                    </a>
                                                </div>
                                            </div>
                                            <CheckCircle2 v-if="activity.is_completed" class="h-5 w-5 text-green-500" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="flex flex-col gap-6">
                    <!-- Career Path Notes -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Career Path Notes</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p v-if="plan.career_path_notes" class="whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-400">
                                {{ plan.career_path_notes }}
                            </p>
                            <p v-else class="text-sm text-slate-500 italic">No career path notes.</p>
                        </CardContent>
                    </Card>

                    <!-- Check-ins -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Check-ins</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div v-if="plan.check_ins.length === 0" class="py-4 text-center text-sm text-slate-500">
                                No check-ins recorded.
                            </div>
                            <div v-else class="space-y-4">
                                <div v-for="checkIn in plan.check_ins" :key="checkIn.id" class="border-l-2 border-slate-200 pl-4 dark:border-slate-700">
                                    <div class="flex items-center gap-2 text-xs text-slate-500">
                                        <Calendar class="h-3 w-3" />
                                        {{ formatDate(checkIn.check_in_date) }}
                                        <span v-if="checkIn.created_by_user">by {{ checkIn.created_by_user.name }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ checkIn.notes }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Approval Modal -->
        <Dialog v-model:open="showApprovalModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Review Development Plan</DialogTitle>
                    <DialogDescription>
                        Approve or return this plan for revision.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <p class="text-sm">
                            <strong>{{ plan.employee.full_name }}</strong> has submitted a development plan with
                            <strong>{{ plan.items.length }}</strong> development items.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <label class="text-sm font-medium">Feedback Notes (optional)</label>
                        <Textarea
                            v-model="approvalNotes"
                            rows="3"
                            placeholder="Add any feedback or suggestions..."
                        />
                    </div>
                </div>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="showApprovalModal = false">
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        :disabled="isRejecting"
                        @click="handleReject"
                    >
                        <Loader2 v-if="isRejecting" class="mr-2 h-4 w-4 animate-spin" />
                        <X v-else class="mr-2 h-4 w-4" />
                        Return for Revision
                    </Button>
                    <Button
                        :disabled="isApproving"
                        @click="handleApprove"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <Loader2 v-if="isApproving" class="mr-2 h-4 w-4 animate-spin" />
                        <Check v-else class="mr-2 h-4 w-4" />
                        Approve
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
