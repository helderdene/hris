<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface ClearanceItem {
    id: number;
    name: string;
    remarks: string | null;
    cleared_at: string | null;
    cleared_by_user: { id: number; name: string } | null;
}

interface Offboarding {
    separation_type: string;
    filed_date: string | null;
    last_working_date: string;
    reason: string | null;
    separated_at: string | null;
    exit_interview_date: string | null;
    exit_interviewer: string | null;
    exit_interview_notes: string | null;
    final_pay_status: string;
    final_pay_amount: string | null;
    final_pay_released_date: string | null;
    coe_status: string;
    coe_issued_date: string | null;
    coe_document_request: { id: number; status: string } | null;
    clearance_items: ClearanceItem[];
}

const props = defineProps<{
    employee: {
        id: number;
        employment_status: string | null;
        termination_date: string | null;
    };
    offboarding: Offboarding | null;
}>();

const separationTypes = [
    { value: 'resigned', label: 'Resigned' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'retired', label: 'Retired' },
    { value: 'end_of_contract', label: 'End of Contract' },
    { value: 'deceased', label: 'Deceased' },
];

const baseUrl = computed(() => `/employees/${props.employee.id}/offboarding`);

const isAlreadySeparated = computed(
    () =>
        !!props.employee.employment_status &&
        props.employee.employment_status !== 'active',
);

const selectClass =
    'flex h-9 w-full rounded-md border border-slate-200 bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:ring-slate-950 focus-visible:outline-none dark:border-slate-800 dark:focus-visible:ring-slate-300';
const cardClass =
    'rounded-lg border border-slate-200 p-4 dark:border-slate-700';
const headingClass = 'text-sm font-semibold text-slate-700 dark:text-slate-300';

const form = useForm({
    separation_type:
        props.offboarding?.separation_type ??
        (isAlreadySeparated.value ? props.employee.employment_status! : ''),
    filed_date: props.offboarding?.filed_date ?? '',
    last_working_date:
        props.offboarding?.last_working_date ??
        props.employee.termination_date?.slice(0, 10) ??
        '',
    reason: props.offboarding?.reason ?? '',
    exit_interview_date: props.offboarding?.exit_interview_date ?? '',
    exit_interviewer: props.offboarding?.exit_interviewer ?? '',
    exit_interview_notes: props.offboarding?.exit_interview_notes ?? '',
    final_pay_status: props.offboarding?.final_pay_status ?? 'pending',
    final_pay_amount: props.offboarding?.final_pay_amount ?? '',
    final_pay_released_date: props.offboarding?.final_pay_released_date ?? '',
    coe_status: props.offboarding?.coe_status ?? 'pending',
    coe_issued_date: props.offboarding?.coe_issued_date ?? '',
});

function save(): void {
    form.transform((data) =>
        Object.fromEntries(
            Object.entries(data).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        ),
    ).submit(props.offboarding ? 'put' : 'post', baseUrl.value, {
        preserveScroll: true,
    });
}

const clearedCount = computed(
    () =>
        props.offboarding?.clearance_items.filter((item) => item.cleared_at)
            .length ?? 0,
);

const newItemName = ref('');

function addItem(): void {
    if (!newItemName.value.trim()) {
        return;
    }

    router.post(
        `${baseUrl.value}/clearance-items`,
        { name: newItemName.value.trim() },
        { preserveScroll: true, onSuccess: () => (newItemName.value = '') },
    );
}

function updateItem(
    item: ClearanceItem,
    data: { is_cleared?: boolean; remarks?: string | null },
): void {
    router.put(`${baseUrl.value}/clearance-items/${item.id}`, data, {
        preserveScroll: true,
    });
}

function removeItem(item: ClearanceItem): void {
    router.delete(`${baseUrl.value}/clearance-items/${item.id}`, {
        preserveScroll: true,
    });
}

function requestCoe(): void {
    router.post(`${baseUrl.value}/coe-request`, {}, { preserveScroll: true });
}

function updateCoeRequest(status: string): void {
    router.put(
        `${baseUrl.value}/coe-request`,
        { status },
        { preserveScroll: true },
    );
}

function cancelOffboarding(): void {
    if (
        confirm(
            'Cancel this offboarding? Its clearance checklist and notes will be deleted.',
        )
    ) {
        router.delete(baseUrl.value, { preserveScroll: true });
    }
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <form class="space-y-6" data-test="offboarding-tab" @submit.prevent="save">
        <!-- Separation status -->
        <section :class="cardClass" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 :class="headingClass">
                    Resignation / Termination / Separation
                </h3>
                <span
                    v-if="offboarding"
                    class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                    :class="
                        offboarding.separated_at
                            ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                    "
                    data-test="offboarding-status"
                >
                    {{
                        offboarding.separated_at
                            ? 'Separated'
                            : 'Offboarding in progress'
                    }}
                </span>
            </div>

            <p
                v-if="!offboarding"
                class="text-sm text-slate-500 dark:text-slate-400"
            >
                {{
                    isAlreadySeparated
                        ? 'This employee was separated without an offboarding record. Record one to track the exit interview, clearance, final pay and COE.'
                        : 'Start offboarding to track the exit interview, clearance, final pay and COE. The employee stays active until you use Separate.'
                }}
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="space-y-1.5">
                    <Label for="separation-type">
                        Type <span class="text-red-500">*</span>
                    </Label>
                    <select
                        id="separation-type"
                        v-model="form.separation_type"
                        :class="selectClass"
                        :disabled="!!offboarding?.separated_at"
                        required
                    >
                        <option value="" disabled>Select type</option>
                        <option
                            v-for="type in separationTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.separation_type" />
                </div>
                <div class="space-y-1.5">
                    <Label for="filed-date">Date Filed / Notice Date</Label>
                    <Input
                        id="filed-date"
                        v-model="form.filed_date"
                        type="date"
                    />
                    <InputError :message="form.errors.filed_date" />
                </div>
                <div class="space-y-1.5">
                    <Label for="last-working-date">
                        Last Working Day <span class="text-red-500">*</span>
                    </Label>
                    <Input
                        id="last-working-date"
                        v-model="form.last_working_date"
                        type="date"
                        :disabled="!!offboarding?.separated_at"
                        required
                    />
                    <InputError :message="form.errors.last_working_date" />
                </div>
            </div>
            <div class="space-y-1.5">
                <Label for="separation-reason">Reason</Label>
                <Textarea
                    id="separation-reason"
                    v-model="form.reason"
                    rows="2"
                    maxlength="500"
                />
                <InputError :message="form.errors.reason" />
            </div>
            <div class="flex justify-end gap-2">
                <Button
                    v-if="offboarding && !offboarding.separated_at"
                    type="button"
                    variant="outline"
                    data-test="offboarding-cancel"
                    @click="cancelOffboarding"
                >
                    Cancel Offboarding
                </Button>
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="offboarding-save"
                >
                    {{
                        offboarding
                            ? 'Save'
                            : isAlreadySeparated
                              ? 'Record Offboarding'
                              : 'Start Offboarding'
                    }}
                </Button>
            </div>
        </section>

        <template v-if="offboarding">
            <!-- Exit interview -->
            <section :class="cardClass" class="space-y-4">
                <h3 :class="headingClass">Exit Interview</h3>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <Label for="exit-interview-date">Interview Date</Label>
                        <Input
                            id="exit-interview-date"
                            v-model="form.exit_interview_date"
                            type="date"
                        />
                        <InputError
                            :message="form.errors.exit_interview_date"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="exit-interviewer">Conducted By</Label>
                        <Input
                            id="exit-interviewer"
                            v-model="form.exit_interviewer"
                        />
                        <InputError :message="form.errors.exit_interviewer" />
                    </div>
                </div>
                <div class="space-y-1.5">
                    <Label for="exit-interview-notes">Notes</Label>
                    <Textarea
                        id="exit-interview-notes"
                        v-model="form.exit_interview_notes"
                        rows="4"
                        placeholder="Reason for leaving, feedback, recommendations..."
                    />
                    <InputError :message="form.errors.exit_interview_notes" />
                </div>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        Save
                    </Button>
                </div>
            </section>

            <!-- Clearance checklist -->
            <section :class="cardClass" class="space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 :class="headingClass">
                        Clearance Checklist (Company Properties)
                    </h3>
                    <span
                        class="text-xs text-slate-500 dark:text-slate-400"
                        data-test="clearance-progress"
                    >
                        {{ clearedCount }} of
                        {{ offboarding.clearance_items.length }} cleared
                    </span>
                </div>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="item in offboarding.clearance_items"
                        :key="item.id"
                        class="flex flex-col gap-2 rounded-md border border-slate-200 p-3 sm:flex-row sm:items-center dark:border-slate-700"
                    >
                        <div class="flex flex-1 items-start gap-3">
                            <Checkbox
                                :id="`clearance-${item.id}`"
                                class="mt-0.5"
                                :model-value="!!item.cleared_at"
                                @update:model-value="
                                    updateItem(item, { is_cleared: !!$event })
                                "
                            />
                            <div>
                                <Label
                                    :for="`clearance-${item.id}`"
                                    :class="{
                                        'text-slate-400 line-through':
                                            item.cleared_at,
                                    }"
                                >
                                    {{ item.name }}
                                </Label>
                                <p
                                    v-if="item.cleared_at"
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Cleared {{ formatDate(item.cleared_at) }}
                                    <template v-if="item.cleared_by_user">
                                        by {{ item.cleared_by_user.name }}
                                    </template>
                                </p>
                            </div>
                        </div>
                        <Input
                            class="sm:w-64"
                            :model-value="item.remarks ?? ''"
                            :aria-label="`Remarks for ${item.name}`"
                            placeholder="Remarks"
                            maxlength="500"
                            @keydown.enter.prevent="
                                ($event.target as HTMLInputElement).blur()
                            "
                            @change="
                                updateItem(item, {
                                    remarks:
                                        ($event.target as HTMLInputElement)
                                            .value || null,
                                })
                            "
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            :aria-label="`Remove ${item.name}`"
                            @click="removeItem(item)"
                        >
                            Remove
                        </Button>
                    </li>
                </ul>
                <div class="flex gap-2">
                    <Input
                        v-model="newItemName"
                        aria-label="New clearance item"
                        placeholder="Add item (e.g. Company phone)"
                        maxlength="255"
                        @keydown.enter.prevent="addItem"
                    />
                    <Button type="button" variant="outline" @click="addItem">
                        Add
                    </Button>
                </div>
            </section>

            <!-- Final pay & COE -->
            <section :class="cardClass" class="space-y-4">
                <h3 :class="headingClass">Final Pay / COE Status</h3>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="final-pay-status">Final Pay</Label>
                        <select
                            id="final-pay-status"
                            v-model="form.final_pay_status"
                            :class="selectClass"
                        >
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Released</option>
                        </select>
                        <InputError :message="form.errors.final_pay_status" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="final-pay-amount">Amount</Label>
                        <Input
                            id="final-pay-amount"
                            v-model="form.final_pay_amount"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="form.errors.final_pay_amount" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="final-pay-released-date">
                            Release Date
                        </Label>
                        <Input
                            id="final-pay-released-date"
                            v-model="form.final_pay_released_date"
                            type="date"
                        />
                        <InputError
                            :message="form.errors.final_pay_released_date"
                        />
                    </div>
                </div>
                <div
                    v-if="offboarding.coe_document_request"
                    class="grid gap-4 sm:grid-cols-3"
                >
                    <div class="space-y-1.5">
                        <Label for="coe-request-status">
                            Certificate of Employment
                        </Label>
                        <select
                            id="coe-request-status"
                            :value="offboarding.coe_document_request.status"
                            :class="selectClass"
                            data-test="coe-request-status"
                            @change="
                                updateCoeRequest(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="ready">Ready for Pickup</option>
                            <option value="collected">Collected</option>
                        </select>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Linked to document request #{{
                                offboarding.coe_document_request.id
                            }}. Changes here update that request and notify the
                            employee.
                        </p>
                    </div>
                    <div v-if="offboarding.coe_issued_date" class="space-y-1.5">
                        <Label>Date Issued</Label>
                        <p class="py-1.5 text-sm">
                            {{ formatDate(offboarding.coe_issued_date) }}
                        </p>
                    </div>
                </div>
                <div v-else class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-1.5">
                        <Label for="coe-status">
                            Certificate of Employment
                        </Label>
                        <select
                            id="coe-status"
                            v-model="form.coe_status"
                            :class="selectClass"
                        >
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Issued</option>
                        </select>
                        <InputError :message="form.errors.coe_status" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="coe-issued-date">Date Issued</Label>
                        <Input
                            id="coe-issued-date"
                            v-model="form.coe_issued_date"
                            type="date"
                        />
                        <InputError :message="form.errors.coe_issued_date" />
                    </div>
                    <div class="flex items-end">
                        <Button
                            v-if="
                                offboarding.separated_at ||
                                offboarding.separation_type === 'resigned'
                            "
                            type="button"
                            variant="outline"
                            data-test="coe-request-create"
                            @click="requestCoe"
                        >
                            Create COE Request
                        </Button>
                    </div>
                </div>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        Save
                    </Button>
                </div>
            </section>
        </template>
    </form>
</template>
