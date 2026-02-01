<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

interface Course {
    id: number;
    course: {
        id: number;
        title: string;
        code: string;
    };
}

interface Creator {
    id: number;
    full_name: string;
}

interface Rule {
    id: number;
    compliance_course_id: number;
    name: string;
    description: string | null;
    rule_type: string;
    rule_type_label: string;
    rule_type_description: string;
    conditions: Record<string, unknown>;
    days_to_complete_override: number | null;
    priority: number;
    is_active: boolean;
    apply_to_new_hires: boolean;
    apply_to_existing: boolean;
    effective_from: string | null;
    effective_until: string | null;
    is_effective: boolean;
    assignments_count?: number;
    creator?: Creator;
    created_at: string;
}

interface RuleTypeOption {
    value: string;
    label: string;
    description: string;
}

interface Department {
    id: number;
    name: string;
}

interface Position {
    id: number;
    title: string;
}

interface Filters {
    compliance_course_id: string | null;
    is_active: string | null;
}

const props = defineProps<{
    rules: Rule[];
    courses: Course[];
    filters: Filters;
    ruleTypeOptions: RuleTypeOption[];
    departments: Department[];
    positions: Position[];
}>();

const { tenantName, primaryColor } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Compliance', href: '/compliance' },
    { title: 'Rules', href: '/compliance/rules' },
];

const courseFilter = ref(props.filters.compliance_course_id ?? 'all');
const activeFilter = ref(props.filters.is_active ?? 'all');

const rulesData = computed(() => props.rules ?? []);
const coursesData = computed(() => props.courses ?? []);

function applyFilters() {
    router.get('/compliance/rules', {
        compliance_course_id: courseFilter.value !== 'all' ? courseFilter.value : undefined,
        is_active: activeFilter.value !== 'all' ? activeFilter.value : undefined,
    }, { preserveState: true });
}

function getRuleTypeIcon(ruleType: string): string {
    const icons: Record<string, string> = {
        department: 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18',
        position: 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0',
        all_employees: 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z',
    };
    return icons[ruleType] ?? icons.all_employees;
}

function formatDate(dateStr: string | null): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString();
}

async function handleToggleActive(rule: Rule) {
    try {
        const response = await fetch(`/api/compliance/rules/${rule.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ is_active: !rule.is_active }),
        });

        if (response.ok) {
            router.reload({ only: ['rules'] });
        }
    } catch (error) {
        console.error('Failed to toggle rule:', error);
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Create Rule Dialog
const showCreateDialog = ref(false);
const ruleForm = reactive({
    compliance_course_id: '',
    name: '',
    description: '',
    rule_type: '',
    conditions: {
        department_ids: [] as number[],
        position_ids: [] as number[],
    },
    priority: '10',
    is_active: true,
    apply_to_new_hires: true,
    apply_to_existing: false,
    errors: {} as Record<string, string>,
    processing: false,
});

const ruleTypeOptions = computed(() => props.ruleTypeOptions ?? []);
const departmentsData = computed(() => props.departments ?? []);
const positionsData = computed(() => props.positions ?? []);

function resetRuleForm() {
    ruleForm.compliance_course_id = '';
    ruleForm.name = '';
    ruleForm.description = '';
    ruleForm.rule_type = '';
    ruleForm.conditions = { department_ids: [], position_ids: [] };
    ruleForm.priority = '10';
    ruleForm.is_active = true;
    ruleForm.apply_to_new_hires = true;
    ruleForm.apply_to_existing = false;
    ruleForm.errors = {};
    ruleForm.processing = false;
}

function openCreateDialog() {
    resetRuleForm();
    showCreateDialog.value = true;
}

async function submitRule() {
    ruleForm.processing = true;
    ruleForm.errors = {};

    try {
        // Build conditions based on rule type (always send conditions, even if empty)
        let conditions: Record<string, unknown> = {};
        if (ruleForm.rule_type === 'department') {
            conditions = { department_ids: ruleForm.conditions.department_ids };
        } else if (ruleForm.rule_type === 'position') {
            conditions = { position_ids: ruleForm.conditions.position_ids };
        }
        // For all_employees and other types, conditions stays as empty object

        const payload: Record<string, unknown> = {
            name: ruleForm.name,
            description: ruleForm.description || null,
            rule_type: ruleForm.rule_type,
            conditions: conditions,
            priority: ruleForm.priority ? parseInt(ruleForm.priority) : 10,
            is_active: ruleForm.is_active,
            apply_to_new_hires: ruleForm.apply_to_new_hires,
            apply_to_existing: ruleForm.apply_to_existing,
        };

        await axios.post(`/api/compliance/courses/${ruleForm.compliance_course_id}/rules`, payload);
        showCreateDialog.value = false;
        router.reload({ only: ['rules'] });
    } catch (error: any) {
        console.error('Rule creation error:', error.response?.data);
        if (error.response?.status === 422) {
            const validationErrors = error.response.data.errors || {};
            for (const [key, messages] of Object.entries(validationErrors)) {
                ruleForm.errors[key] = Array.isArray(messages) ? messages[0] : (messages as string);
            }
        } else {
            ruleForm.errors.general = error.response?.data?.message || 'An error occurred';
        }
    } finally {
        ruleForm.processing = false;
    }
}

function toggleDepartment(deptId: number) {
    const idx = ruleForm.conditions.department_ids.indexOf(deptId);
    if (idx === -1) {
        ruleForm.conditions.department_ids.push(deptId);
    } else {
        ruleForm.conditions.department_ids.splice(idx, 1);
    }
}

function togglePosition(posId: number) {
    const idx = ruleForm.conditions.position_ids.indexOf(posId);
    if (idx === -1) {
        ruleForm.conditions.position_ids.push(posId);
    } else {
        ruleForm.conditions.position_ids.splice(idx, 1);
    }
}
</script>

<template>
    <Head :title="`Assignment Rules - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Assignment Rules
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Configure automatic training assignment rules.
                    </p>
                </div>
                <Button :style="{ backgroundColor: primaryColor }" @click="openCreateDialog">
                    Create Rule
                </Button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap gap-3">
                <Select :model-value="courseFilter" @update:model-value="(v) => { courseFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Course" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Courses</SelectItem>
                        <SelectItem
                            v-for="course in coursesData"
                            :key="course.id"
                            :value="String(course.id)"
                        >
                            {{ course.course?.title ?? 'Untitled' }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Select :model-value="activeFilter" @update:model-value="(v) => { activeFilter = v; applyFilters(); }">
                    <SelectTrigger class="w-40">
                        <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Status</SelectItem>
                        <SelectItem value="1">Active</SelectItem>
                        <SelectItem value="0">Inactive</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Rules List -->
            <div v-if="rulesData.length > 0" class="flex flex-col gap-4">
                <div
                    v-for="rule in rulesData"
                    :key="rule.id"
                    class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800">
                                <svg
                                    class="h-5 w-5 text-slate-600 dark:text-slate-400"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="1.5"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" :d="getRuleTypeIcon(rule.rule_type)" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                                        {{ rule.name }}
                                    </h3>
                                    <Badge :variant="rule.is_active ? 'default' : 'outline'">
                                        {{ rule.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                    <Badge v-if="!rule.is_effective" variant="secondary">
                                        Not Effective
                                    </Badge>
                                </div>
                                <p v-if="rule.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ rule.description }}
                                </p>
                                <div class="mt-2 flex flex-wrap gap-4 text-sm text-slate-500 dark:text-slate-400">
                                    <span>Type: {{ rule.rule_type_label }}</span>
                                    <span>Priority: {{ rule.priority }}</span>
                                    <span v-if="rule.assignments_count !== undefined">
                                        {{ rule.assignments_count }} assignments
                                    </span>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <Badge v-if="rule.apply_to_new_hires" variant="outline">
                                        New Hires
                                    </Badge>
                                    <Badge v-if="rule.apply_to_existing" variant="outline">
                                        Existing Employees
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                    <span class="sr-only">Open menu</span>
                                    <svg
                                        class="h-4 w-4"
                                        xmlns="http://www.w3.org/2000/svg"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                        />
                                    </svg>
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem>Edit Rule</DropdownMenuItem>
                                <DropdownMenuItem>Preview Affected</DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem @click="handleToggleActive(rule)">
                                    {{ rule.is_active ? 'Deactivate' : 'Activate' }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <div v-if="rule.effective_from || rule.effective_until" class="mt-4 text-sm text-slate-500 dark:text-slate-400">
                        Effective: {{ formatDate(rule.effective_from) }} - {{ formatDate(rule.effective_until) }}
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white py-12 dark:border-slate-700 dark:bg-slate-900"
            >
                <svg
                    class="h-12 w-12 text-slate-400 dark:text-slate-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"
                    />
                </svg>
                <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                    No assignment rules
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Create rules to automatically assign training to employees.
                </p>
                <Button class="mt-4" :style="{ backgroundColor: primaryColor }" @click="openCreateDialog">
                    Create Rule
                </Button>
            </div>
        </div>

        <!-- Create Rule Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent class="sm:max-w-[500px]">
                <DialogHeader>
                    <DialogTitle>Create Assignment Rule</DialogTitle>
                    <DialogDescription>
                        Create a rule to automatically assign compliance training to employees.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitRule" class="flex flex-col gap-4">
                    <!-- General error display -->
                    <div v-if="Object.keys(ruleForm.errors).length > 0" class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400">
                        <p class="font-medium">Please fix the following errors:</p>
                        <ul class="mt-1 list-inside list-disc">
                            <li v-for="(error, key) in ruleForm.errors" :key="key">{{ error }}</li>
                        </ul>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="rule_course">Compliance Course *</Label>
                        <Select v-model="ruleForm.compliance_course_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select course" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="course in coursesData"
                                    :key="course.id"
                                    :value="String(course.id)"
                                >
                                    {{ course.course?.title ?? 'Untitled' }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="ruleForm.errors.compliance_course_id" class="text-sm text-red-500">
                            {{ ruleForm.errors.compliance_course_id }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="rule_name">Rule Name *</Label>
                        <Input
                            id="rule_name"
                            v-model="ruleForm.name"
                            placeholder="e.g., All Engineering Department"
                            required
                        />
                        <p v-if="ruleForm.errors.name" class="text-sm text-red-500">
                            {{ ruleForm.errors.name }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="rule_type">Rule Type *</Label>
                        <Select v-model="ruleForm.rule_type">
                            <SelectTrigger>
                                <SelectValue placeholder="Select rule type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in ruleTypeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="ruleForm.errors.rule_type" class="text-sm text-red-500">
                            {{ ruleForm.errors.rule_type }}
                        </p>
                    </div>

                    <!-- Department Selection -->
                    <div v-if="ruleForm.rule_type === 'department'" class="flex flex-col gap-2">
                        <Label>Select Departments</Label>
                        <div class="max-h-40 overflow-y-auto rounded-md border border-slate-200 p-2 dark:border-slate-700">
                            <label
                                v-for="dept in departmentsData"
                                :key="dept.id"
                                class="flex cursor-pointer items-center gap-2 rounded p-1 hover:bg-slate-50 dark:hover:bg-slate-800"
                            >
                                <input
                                    type="checkbox"
                                    :checked="ruleForm.conditions.department_ids.includes(dept.id)"
                                    @change="toggleDepartment(dept.id)"
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ dept.name }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Position Selection -->
                    <div v-if="ruleForm.rule_type === 'position'" class="flex flex-col gap-2">
                        <Label>Select Positions</Label>
                        <div class="max-h-40 overflow-y-auto rounded-md border border-slate-200 p-2 dark:border-slate-700">
                            <label
                                v-for="pos in positionsData"
                                :key="pos.id"
                                class="flex cursor-pointer items-center gap-2 rounded p-1 hover:bg-slate-50 dark:hover:bg-slate-800"
                            >
                                <input
                                    type="checkbox"
                                    :checked="ruleForm.conditions.position_ids.includes(pos.id)"
                                    @change="togglePosition(pos.id)"
                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                />
                                <span class="text-sm text-slate-700 dark:text-slate-300">
                                    {{ pos.title }}
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="rule_description">Description</Label>
                        <Textarea
                            id="rule_description"
                            v-model="ruleForm.description"
                            placeholder="Optional description"
                            rows="2"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <Label for="rule_priority">Priority</Label>
                        <Input
                            id="rule_priority"
                            v-model="ruleForm.priority"
                            type="number"
                            min="0"
                            max="100"
                            placeholder="10"
                        />
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Lower numbers = higher priority
                        </p>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="ruleForm.is_active"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                Rule is active
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="ruleForm.apply_to_new_hires"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                Apply to new hires
                            </span>
                        </label>

                        <label class="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="ruleForm.apply_to_existing"
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                Apply to existing employees
                            </span>
                        </label>
                    </div>

                    <DialogFooter class="gap-2 pt-4">
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="ruleForm.processing"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            {{ ruleForm.processing ? 'Creating...' : 'Create Rule' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
