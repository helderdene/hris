<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface LeaveType {
    id: number;
    name: string;
    code: string;
    description: string | null;
    leave_category: string;
    accrual_method: string;
    default_days_per_year: number;
    monthly_accrual_rate: number | null;
    tenure_brackets: { years: number; days: number }[] | null;
    allow_carry_over: boolean;
    max_carry_over_days: number | null;
    carry_over_expiry_months: number | null;
    is_convertible_to_cash: boolean;
    cash_conversion_rate: number | null;
    max_convertible_days: number | null;
    min_tenure_months: number | null;
    eligible_employment_types: string[] | null;
    gender_restriction: string | null;
    requires_attachment: boolean;
    requires_approval: boolean;
    max_consecutive_days: number | null;
    min_days_advance_notice: number | null;
    is_statutory: boolean;
    statutory_reference: string | null;
    is_active: boolean;
}

interface EnumOption {
    value: string;
    label: string;
    shortLabel?: string;
}

const props = defineProps<{
    leaveType: LeaveType | null;
    leaveCategories: EnumOption[];
    accrualMethods: EnumOption[];
    genderRestrictions: EnumOption[];
    employmentTypes: EnumOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    code: '',
    description: '',
    leave_category: '',
    accrual_method: '',
    default_days_per_year: 0,
    monthly_accrual_rate: null as number | null,
    tenure_brackets: [] as { years: number; days: number }[],
    allow_carry_over: false,
    max_carry_over_days: null as number | null,
    carry_over_expiry_months: null as number | null,
    is_convertible_to_cash: false,
    cash_conversion_rate: null as number | null,
    max_convertible_days: null as number | null,
    min_tenure_months: null as number | null,
    eligible_employment_types: [] as string[],
    gender_restriction: '',
    requires_attachment: false,
    requires_approval: true,
    max_consecutive_days: null as number | null,
    min_days_advance_notice: null as number | null,
    is_statutory: false,
    statutory_reference: '',
    is_active: true,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);
const activeTab = ref('basic');

const isEditing = computed(() => !!props.leaveType);

const showMonthlyAccrualRate = computed(
    () => form.value.accrual_method === 'monthly',
);
const showTenureBrackets = computed(
    () => form.value.accrual_method === 'tenure_based',
);

watch(
    () => props.leaveType,
    (newLeaveType) => {
        if (newLeaveType) {
            form.value = {
                name: newLeaveType.name,
                code: newLeaveType.code,
                description: newLeaveType.description || '',
                leave_category: newLeaveType.leave_category,
                accrual_method: newLeaveType.accrual_method,
                default_days_per_year: newLeaveType.default_days_per_year,
                monthly_accrual_rate: newLeaveType.monthly_accrual_rate,
                tenure_brackets: newLeaveType.tenure_brackets || [],
                allow_carry_over: newLeaveType.allow_carry_over,
                max_carry_over_days: newLeaveType.max_carry_over_days,
                carry_over_expiry_months: newLeaveType.carry_over_expiry_months,
                is_convertible_to_cash: newLeaveType.is_convertible_to_cash,
                cash_conversion_rate: newLeaveType.cash_conversion_rate,
                max_convertible_days: newLeaveType.max_convertible_days,
                min_tenure_months: newLeaveType.min_tenure_months,
                eligible_employment_types:
                    newLeaveType.eligible_employment_types || [],
                gender_restriction: newLeaveType.gender_restriction || '',
                requires_attachment: newLeaveType.requires_attachment,
                requires_approval: newLeaveType.requires_approval,
                max_consecutive_days: newLeaveType.max_consecutive_days,
                min_days_advance_notice: newLeaveType.min_days_advance_notice,
                is_statutory: newLeaveType.is_statutory,
                statutory_reference: newLeaveType.statutory_reference || '',
                is_active: newLeaveType.is_active,
            };
        } else {
            resetForm();
        }
        errors.value = {};
        activeTab.value = 'basic';
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
        activeTab.value = 'basic';
    }
});

function resetForm() {
    form.value = {
        name: '',
        code: '',
        description: '',
        leave_category: '',
        accrual_method: 'annual',
        default_days_per_year: 0,
        monthly_accrual_rate: null,
        tenure_brackets: [],
        allow_carry_over: false,
        max_carry_over_days: null,
        carry_over_expiry_months: null,
        is_convertible_to_cash: false,
        cash_conversion_rate: null,
        max_convertible_days: null,
        min_tenure_months: null,
        eligible_employment_types: [],
        gender_restriction: '',
        requires_attachment: false,
        requires_approval: true,
        max_consecutive_days: null,
        min_days_advance_notice: null,
        is_statutory: false,
        statutory_reference: '',
        is_active: true,
    };
    errors.value = {};
}

function addTenureBracket() {
    form.value.tenure_brackets.push({ years: 0, days: 0 });
}

function removeTenureBracket(index: number) {
    form.value.tenure_brackets.splice(index, 1);
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/leave-types/${props.leaveType!.id}`
        : '/api/organization/leave-types';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        ...form.value,
        gender_restriction: form.value.gender_restriction || null,
        description: form.value.description || null,
        statutory_reference: form.value.statutory_reference || null,
        tenure_brackets:
            form.value.tenure_brackets.length > 0
                ? form.value.tenure_brackets
                : null,
        eligible_employment_types:
            form.value.eligible_employment_types.length > 0
                ? form.value.eligible_employment_types
                : null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch (error) {
        errors.value = {
            general: 'An error occurred while saving the leave type',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Leave Type' : 'Add Leave Type' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the leave type configuration below.'
                            : 'Configure a new leave type for your organization.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <Tabs v-model="activeTab" class="w-full">
                    <TabsList class="grid w-full grid-cols-4">
                        <TabsTrigger value="basic">Basic</TabsTrigger>
                        <TabsTrigger value="entitlement"
                            >Entitlement</TabsTrigger
                        >
                        <TabsTrigger value="carryover">Carry-over</TabsTrigger>
                        <TabsTrigger value="settings">Settings</TabsTrigger>
                    </TabsList>

                    <!-- Basic Info Tab -->
                    <TabsContent value="basic" class="mt-4 space-y-4">
                        <!-- Name & Code -->
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="name">Leave Type Name *</Label>
                                <Input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="e.g., Vacation Leave"
                                    :class="{ 'border-red-500': errors.name }"
                                    data-test="leave-type-name-input"
                                />
                                <p
                                    v-if="errors.name"
                                    class="text-sm text-red-500"
                                >
                                    {{ errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="code">Code *</Label>
                                <Input
                                    id="code"
                                    v-model="form.code"
                                    type="text"
                                    placeholder="e.g., VL"
                                    :class="{ 'border-red-500': errors.code }"
                                    data-test="leave-type-code-input"
                                />
                                <p
                                    v-if="errors.code"
                                    class="text-sm text-red-500"
                                >
                                    {{ errors.code }}
                                </p>
                            </div>
                        </div>

                        <!-- Category -->
                        <div class="space-y-2">
                            <Label for="leave_category">Category *</Label>
                            <EnumSelect
                                id="leave_category"
                                v-model="form.leave_category"
                                :options="leaveCategories"
                                placeholder="Select category"
                                data-test="leave-category-select"
                            />
                            <p
                                v-if="errors.leave_category"
                                class="text-sm text-red-500"
                            >
                                {{ errors.leave_category }}
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="space-y-2">
                            <Label for="description">Description</Label>
                            <Textarea
                                id="description"
                                v-model="form.description"
                                placeholder="Optional description"
                                rows="2"
                                data-test="leave-type-description-input"
                            />
                        </div>

                        <!-- Active Status -->
                        <div class="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                v-model="form.is_active"
                                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                data-test="is-active-checkbox"
                            />
                            <Label for="is_active" class="cursor-pointer"
                                >Active</Label
                            >
                        </div>
                    </TabsContent>

                    <!-- Entitlement Tab -->
                    <TabsContent value="entitlement" class="mt-4 space-y-4">
                        <!-- Accrual Method -->
                        <div class="space-y-2">
                            <Label for="accrual_method">Accrual Method *</Label>
                            <EnumSelect
                                id="accrual_method"
                                v-model="form.accrual_method"
                                :options="accrualMethods"
                                placeholder="Select method"
                                data-test="accrual-method-select"
                            />
                            <p
                                v-if="errors.accrual_method"
                                class="text-sm text-red-500"
                            >
                                {{ errors.accrual_method }}
                            </p>
                        </div>

                        <!-- Days Per Year -->
                        <div class="space-y-2">
                            <Label for="default_days_per_year"
                                >Days Per Year *</Label
                            >
                            <Input
                                id="default_days_per_year"
                                v-model.number="form.default_days_per_year"
                                type="number"
                                step="0.5"
                                min="0"
                                :class="{
                                    'border-red-500':
                                        errors.default_days_per_year,
                                }"
                                data-test="days-per-year-input"
                            />
                            <p
                                v-if="errors.default_days_per_year"
                                class="text-sm text-red-500"
                            >
                                {{ errors.default_days_per_year }}
                            </p>
                        </div>

                        <!-- Monthly Accrual Rate (conditional) -->
                        <div v-if="showMonthlyAccrualRate" class="space-y-2">
                            <Label for="monthly_accrual_rate"
                                >Monthly Accrual Rate</Label
                            >
                            <Input
                                id="monthly_accrual_rate"
                                v-model.number="form.monthly_accrual_rate"
                                type="number"
                                step="0.0001"
                                min="0"
                                placeholder="Days accrued per month"
                            />
                        </div>

                        <!-- Tenure Brackets (conditional) -->
                        <div v-if="showTenureBrackets" class="space-y-3">
                            <div class="flex items-center justify-between">
                                <Label>Tenure Brackets</Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="addTenureBracket"
                                >
                                    Add Bracket
                                </Button>
                            </div>
                            <div
                                v-for="(bracket, index) in form.tenure_brackets"
                                :key="index"
                                class="flex items-center gap-2"
                            >
                                <Input
                                    v-model.number="bracket.years"
                                    type="number"
                                    min="0"
                                    placeholder="Years"
                                    class="w-24"
                                />
                                <span
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                    >years →</span
                                >
                                <Input
                                    v-model.number="bracket.days"
                                    type="number"
                                    min="0"
                                    step="0.5"
                                    placeholder="Days"
                                    class="w-24"
                                />
                                <span
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                    >days</span
                                >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removeTenureBracket(index)"
                                    class="text-red-500 hover:text-red-700"
                                >
                                    Remove
                                </Button>
                            </div>
                        </div>

                        <!-- Eligibility -->
                        <div class="space-y-4 border-t pt-4">
                            <h4
                                class="text-sm font-medium text-slate-900 dark:text-slate-100"
                            >
                                Eligibility Requirements
                            </h4>

                            <!-- Min Tenure -->
                            <div class="space-y-2">
                                <Label for="min_tenure_months"
                                    >Minimum Tenure (months)</Label
                                >
                                <Input
                                    id="min_tenure_months"
                                    v-model.number="form.min_tenure_months"
                                    type="number"
                                    min="0"
                                    placeholder="e.g., 12"
                                />
                            </div>

                            <!-- Gender Restriction -->
                            <div class="space-y-2">
                                <Label for="gender_restriction"
                                    >Gender Restriction</Label
                                >
                                <EnumSelect
                                    id="gender_restriction"
                                    v-model="form.gender_restriction"
                                    :options="genderRestrictions"
                                    placeholder="No restriction"
                                />
                            </div>
                        </div>
                    </TabsContent>

                    <!-- Carry-over & Conversion Tab -->
                    <TabsContent value="carryover" class="mt-4 space-y-4">
                        <!-- Carry-over -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input
                                    id="allow_carry_over"
                                    type="checkbox"
                                    v-model="form.allow_carry_over"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                />
                                <div>
                                    <Label
                                        for="allow_carry_over"
                                        class="cursor-pointer"
                                        >Allow Carry-over</Label
                                    >
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Unused leave can be carried to next year
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="form.allow_carry_over"
                                class="grid grid-cols-1 gap-4 pl-7 sm:grid-cols-2"
                            >
                                <div class="space-y-2">
                                    <Label for="max_carry_over_days"
                                        >Max Carry-over Days</Label
                                    >
                                    <Input
                                        id="max_carry_over_days"
                                        v-model.number="form.max_carry_over_days"
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        placeholder="e.g., 5"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="carry_over_expiry_months"
                                        >Expiry (months)</Label
                                    >
                                    <Input
                                        id="carry_over_expiry_months"
                                        v-model.number="
                                            form.carry_over_expiry_months
                                        "
                                        type="number"
                                        min="1"
                                        placeholder="e.g., 3"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Cash Conversion -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center gap-3">
                                <input
                                    id="is_convertible_to_cash"
                                    type="checkbox"
                                    v-model="form.is_convertible_to_cash"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                />
                                <div>
                                    <Label
                                        for="is_convertible_to_cash"
                                        class="cursor-pointer"
                                        >Convertible to Cash</Label
                                    >
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        Unused leave can be converted to cash
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="form.is_convertible_to_cash"
                                class="grid grid-cols-1 gap-4 pl-7 sm:grid-cols-2"
                            >
                                <div class="space-y-2">
                                    <Label for="cash_conversion_rate"
                                        >Conversion Rate</Label
                                    >
                                    <Input
                                        id="cash_conversion_rate"
                                        v-model.number="
                                            form.cash_conversion_rate
                                        "
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="2"
                                        placeholder="e.g., 1.0"
                                    />
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        1.0 = 100% of daily rate
                                    </p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="max_convertible_days"
                                        >Max Convertible Days</Label
                                    >
                                    <Input
                                        id="max_convertible_days"
                                        v-model.number="
                                            form.max_convertible_days
                                        "
                                        type="number"
                                        step="0.5"
                                        min="0"
                                        placeholder="e.g., 5"
                                    />
                                </div>
                            </div>
                        </div>
                    </TabsContent>

                    <!-- Settings Tab -->
                    <TabsContent value="settings" class="mt-4 space-y-4">
                        <!-- Approval Settings -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <input
                                    id="requires_approval"
                                    type="checkbox"
                                    v-model="form.requires_approval"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                />
                                <Label
                                    for="requires_approval"
                                    class="cursor-pointer"
                                    >Requires Approval</Label
                                >
                            </div>

                            <div class="flex items-center gap-3">
                                <input
                                    id="requires_attachment"
                                    type="checkbox"
                                    v-model="form.requires_attachment"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                />
                                <Label
                                    for="requires_attachment"
                                    class="cursor-pointer"
                                    >Requires Attachment</Label
                                >
                            </div>
                        </div>

                        <!-- Limits -->
                        <div
                            class="grid grid-cols-1 gap-4 border-t pt-4 sm:grid-cols-2"
                        >
                            <div class="space-y-2">
                                <Label for="max_consecutive_days"
                                    >Max Consecutive Days</Label
                                >
                                <Input
                                    id="max_consecutive_days"
                                    v-model.number="form.max_consecutive_days"
                                    type="number"
                                    min="1"
                                    placeholder="No limit"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="min_days_advance_notice"
                                    >Min Days Advance Notice</Label
                                >
                                <Input
                                    id="min_days_advance_notice"
                                    v-model.number="form.min_days_advance_notice"
                                    type="number"
                                    min="0"
                                    placeholder="e.g., 3"
                                />
                            </div>
                        </div>

                        <!-- Statutory Info -->
                        <div class="space-y-4 border-t pt-4">
                            <div class="flex items-center gap-3">
                                <input
                                    id="is_statutory"
                                    type="checkbox"
                                    v-model="form.is_statutory"
                                    class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                                />
                                <div>
                                    <Label
                                        for="is_statutory"
                                        class="cursor-pointer"
                                        >Statutory Leave</Label
                                    >
                                    <p
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        This leave is mandated by law
                                    </p>
                                </div>
                            </div>

                            <div v-if="form.is_statutory" class="space-y-2 pl-7">
                                <Label for="statutory_reference"
                                    >Legal Reference</Label
                                >
                                <Input
                                    id="statutory_reference"
                                    v-model="form.statutory_reference"
                                    type="text"
                                    placeholder="e.g., RA 11210, Labor Code Art. 95"
                                />
                            </div>
                        </div>
                    </TabsContent>
                </Tabs>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isSubmitting"
                        data-test="submit-leave-type-button"
                    >
                        <svg
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{
                            isEditing ? 'Update Leave Type' : 'Create Leave Type'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
