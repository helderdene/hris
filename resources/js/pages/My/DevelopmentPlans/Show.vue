<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
    Plus,
    Send,
    Target,
    Users,
} from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

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
    completed_at: string | null;
    completion_notes: string | null;
    is_overdue: boolean;
    days_until_due: number | null;
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
    proficiency_gap: number | null;
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
    completed_at: string | null;
    career_path_notes: string | null;
    approval_notes: string | null;
    progress: number;
    is_editable: boolean;
    can_add_activities: boolean;
    is_overdue: boolean;
    days_remaining: number | null;
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

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
    icon?: string;
}

const props = defineProps<{
    plan: DevelopmentPlan;
    statuses: EnumOption[];
    itemStatuses: EnumOption[];
    priorities: EnumOption[];
    activityTypes: EnumOption[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/my/dashboard' },
    { title: 'Development Plans', href: '/my/development-plans' },
    { title: props.plan.title, href: '#' },
];

const expandedItems = ref<Set<number>>(new Set(props.plan.items.map(i => i.id)));

// Add Item Modal
const showAddItemModal = ref(false);
const newItem = ref({
    title: '',
    description: '',
    current_level: '',
    target_level: '',
    priority: 'medium',
});
const isAddingItem = ref(false);

// Add Activity Modal
const showAddActivityModal = ref(false);
const selectedItemForActivity = ref<number | null>(null);
const newActivity = ref({
    activity_type: 'training',
    title: '',
    description: '',
    resource_url: '',
    due_date: '',
});
const isAddingActivity = ref(false);

// Add Check-in Modal
const showAddCheckInModal = ref(false);
const newCheckIn = ref({
    check_in_date: new Date().toISOString().split('T')[0],
    notes: '',
});
const isAddingCheckIn = ref(false);

// Completing Activity
const completingActivityId = ref<number | null>(null);

// Submitting Plan
const isSubmitting = ref(false);

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

async function handleAddItem() {
    isAddingItem.value = true;
    try {
        await axios.post(`/api/my/development-plans/${props.plan.id}/items`, {
            ...newItem.value,
            current_level: newItem.value.current_level ? parseInt(newItem.value.current_level) : null,
            target_level: newItem.value.target_level ? parseInt(newItem.value.target_level) : null,
        });
        showAddItemModal.value = false;
        newItem.value = { title: '', description: '', current_level: '', target_level: '', priority: 'medium' };
        router.reload();
    } catch (error) {
        console.error('Error adding item:', error);
    } finally {
        isAddingItem.value = false;
    }
}

function openAddActivityModal(itemId: number) {
    selectedItemForActivity.value = itemId;
    showAddActivityModal.value = true;
}

async function handleAddActivity() {
    if (!selectedItemForActivity.value) return;
    isAddingActivity.value = true;
    try {
        await axios.post(`/api/my/development-plans/${props.plan.id}/items/${selectedItemForActivity.value}/activities`, newActivity.value);
        showAddActivityModal.value = false;
        newActivity.value = { activity_type: 'training', title: '', description: '', resource_url: '', due_date: '' };
        router.reload();
    } catch (error) {
        console.error('Error adding activity:', error);
    } finally {
        isAddingActivity.value = false;
    }
}

async function handleCompleteActivity(activity: Activity) {
    completingActivityId.value = activity.id;
    try {
        await axios.post(`/api/my/development-plans/activities/${activity.id}/complete`);
        router.reload();
    } catch (error) {
        console.error('Error completing activity:', error);
    } finally {
        completingActivityId.value = null;
    }
}

async function handleAddCheckIn() {
    isAddingCheckIn.value = true;
    try {
        await axios.post(`/api/my/development-plans/${props.plan.id}/check-ins`, newCheckIn.value);
        showAddCheckInModal.value = false;
        newCheckIn.value = { check_in_date: new Date().toISOString().split('T')[0], notes: '' };
        router.reload();
    } catch (error) {
        console.error('Error adding check-in:', error);
    } finally {
        isAddingCheckIn.value = false;
    }
}

async function handleSubmitForApproval() {
    isSubmitting.value = true;
    try {
        await axios.post(`/api/my/development-plans/${props.plan.id}/submit`);
        router.reload();
    } catch (error) {
        console.error('Error submitting plan:', error);
    } finally {
        isSubmitting.value = false;
    }
}

const canSubmit = computed(() => {
    return props.plan.status === 'draft' && props.plan.items.length > 0;
});
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

                <div class="flex items-center gap-3">
                    <Button
                        v-if="canSubmit"
                        @click="handleSubmitForApproval"
                        :disabled="isSubmitting"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <Loader2 v-if="isSubmitting" class="mr-2 h-4 w-4 animate-spin" />
                        <Send v-else class="mr-2 h-4 w-4" />
                        Submit for Approval
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
                        <CardHeader class="flex flex-row items-center justify-between">
                            <div>
                                <CardTitle>Development Items</CardTitle>
                                <CardDescription>Skills and competencies to develop</CardDescription>
                            </div>
                            <Button v-if="plan.is_editable" size="sm" variant="outline" @click="showAddItemModal = true">
                                <Plus class="mr-2 h-4 w-4" />
                                Add Item
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div v-if="plan.items.length === 0" class="py-8 text-center text-slate-500 dark:text-slate-400">
                                No development items yet. Add your first item to get started.
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
                                        <div class="flex items-center justify-between">
                                            <h5 class="text-sm font-medium text-slate-700 dark:text-slate-300">Activities</h5>
                                            <Button
                                                v-if="plan.can_add_activities"
                                                size="sm"
                                                variant="ghost"
                                                class="h-7 text-xs"
                                                @click.stop="openAddActivityModal(item.id)"
                                            >
                                                <Plus class="mr-1 h-3 w-3" />
                                                Add
                                            </Button>
                                        </div>

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
                                            <Button
                                                v-if="!activity.is_completed && plan.can_add_activities"
                                                size="sm"
                                                variant="ghost"
                                                class="h-8"
                                                :disabled="completingActivityId === activity.id"
                                                @click.stop="handleCompleteActivity(activity)"
                                            >
                                                <Loader2 v-if="completingActivityId === activity.id" class="h-4 w-4 animate-spin" />
                                                <Check v-else class="h-4 w-4" />
                                            </Button>
                                            <CheckCircle2 v-else-if="activity.is_completed" class="h-5 w-5 text-green-500" />
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
                            <p v-else class="text-sm text-slate-500 italic">No career path notes yet.</p>
                        </CardContent>
                    </Card>

                    <!-- Manager Info -->
                    <Card v-if="plan.manager">
                        <CardHeader>
                            <CardTitle class="text-base">Manager</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                    <Users class="h-5 w-5" />
                                </div>
                                <div>
                                    <p class="font-medium text-slate-900 dark:text-slate-100">{{ plan.manager.full_name }}</p>
                                    <p class="text-sm text-slate-500">Reviewing Manager</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Approval Notes -->
                    <Card v-if="plan.approval_notes">
                        <CardHeader>
                            <CardTitle class="text-base">Manager Feedback</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-400">
                                {{ plan.approval_notes }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Check-ins -->
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <CardTitle class="text-base">Check-ins</CardTitle>
                            <Button size="sm" variant="ghost" @click="showAddCheckInModal = true">
                                <Plus class="h-4 w-4" />
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <div v-if="plan.check_ins.length === 0" class="py-4 text-center text-sm text-slate-500">
                                No check-ins recorded yet.
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

        <!-- Add Item Modal -->
        <Dialog v-model:open="showAddItemModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Development Item</DialogTitle>
                    <DialogDescription>Add a new skill or competency to develop.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="item-title">Title *</Label>
                        <Input id="item-title" v-model="newItem.title" placeholder="e.g., Improve presentation skills" />
                    </div>
                    <div class="space-y-2">
                        <Label for="item-description">Description</Label>
                        <Textarea id="item-description" v-model="newItem.description" rows="2" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="item-current">Current Level (1-5)</Label>
                            <Input id="item-current" v-model="newItem.current_level" type="number" min="1" max="5" />
                        </div>
                        <div class="space-y-2">
                            <Label for="item-target">Target Level (1-5)</Label>
                            <Input id="item-target" v-model="newItem.target_level" type="number" min="1" max="5" />
                        </div>
                    </div>
                    <div class="space-y-2">
                        <Label>Priority</Label>
                        <Select v-model="newItem.priority">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="p in priorities" :key="p.value" :value="p.value">{{ p.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddItemModal = false">Cancel</Button>
                    <Button :disabled="isAddingItem || !newItem.title" @click="handleAddItem" :style="{ backgroundColor: primaryColor }">
                        <Loader2 v-if="isAddingItem" class="mr-2 h-4 w-4 animate-spin" />
                        Add Item
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Activity Modal -->
        <Dialog v-model:open="showAddActivityModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Activity</DialogTitle>
                    <DialogDescription>Add a development activity.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label>Activity Type</Label>
                        <Select v-model="newActivity.activity_type">
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in activityTypes" :key="t.value" :value="t.value">{{ t.label }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="activity-title">Title *</Label>
                        <Input id="activity-title" v-model="newActivity.title" placeholder="e.g., Complete online course" />
                    </div>
                    <div class="space-y-2">
                        <Label for="activity-description">Description</Label>
                        <Textarea id="activity-description" v-model="newActivity.description" rows="2" />
                    </div>
                    <div class="space-y-2">
                        <Label for="activity-url">Resource URL</Label>
                        <Input id="activity-url" v-model="newActivity.resource_url" type="url" placeholder="https://" />
                    </div>
                    <div class="space-y-2">
                        <Label for="activity-due">Due Date</Label>
                        <Input id="activity-due" v-model="newActivity.due_date" type="date" />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddActivityModal = false">Cancel</Button>
                    <Button :disabled="isAddingActivity || !newActivity.title" @click="handleAddActivity" :style="{ backgroundColor: primaryColor }">
                        <Loader2 v-if="isAddingActivity" class="mr-2 h-4 w-4 animate-spin" />
                        Add Activity
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Add Check-in Modal -->
        <Dialog v-model:open="showAddCheckInModal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Record Check-in</DialogTitle>
                    <DialogDescription>Document your development discussion.</DialogDescription>
                </DialogHeader>
                <div class="space-y-4 py-4">
                    <div class="space-y-2">
                        <Label for="checkin-date">Date *</Label>
                        <Input id="checkin-date" v-model="newCheckIn.check_in_date" type="date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="checkin-notes">Notes *</Label>
                        <Textarea id="checkin-notes" v-model="newCheckIn.notes" rows="4" placeholder="Summary of the discussion..." />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAddCheckInModal = false">Cancel</Button>
                    <Button :disabled="isAddingCheckIn || !newCheckIn.notes" @click="handleAddCheckIn" :style="{ backgroundColor: primaryColor }">
                        <Loader2 v-if="isAddingCheckIn" class="mr-2 h-4 w-4 animate-spin" />
                        Save Check-in
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
