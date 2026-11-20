<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Circle } from 'lucide-vue-next';
import { computed } from 'vue';

interface Offboarding {
    separation_type_label: string;
    filed_date: string | null;
    last_working_date: string;
    reason: string | null;
    is_separated: boolean;
    final_pay_status: string;
    final_pay_released_date: string | null;
    coe_status: string;
    coe_issued_date: string | null;
    clearance_items: { id: number; name: string; is_cleared: boolean }[];
}

const props = defineProps<{
    hasEmployeeProfile: boolean;
    canFile: boolean;
    offboarding: Offboarding | null;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'Resignation', href: '/my/resignation' },
];

const form = useForm({ last_working_date: '', reason: '' });

function submit(): void {
    if (
        confirm(
            'File your resignation? HR will be notified. To withdraw it later you will need to contact HR.',
        )
    ) {
        form.post('/my/resignation', { preserveScroll: true });
    }
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString(undefined, {
              year: 'numeric',
              month: 'short',
              day: 'numeric',
          })
        : '---';
}

const steps = computed(() => {
    if (!props.offboarding) {
        return [];
    }

    const label = (status: string, done: string) =>
        ({ pending: 'Pending', processing: 'Processing' })[status] ?? done;

    return [
        {
            label: 'Final Pay',
            value: label(props.offboarding.final_pay_status, 'Released'),
            date: props.offboarding.final_pay_released_date,
        },
        {
            label: 'Certificate of Employment',
            value: label(props.offboarding.coe_status, 'Issued'),
            date: props.offboarding.coe_issued_date,
        },
    ];
});
</script>

<template>
    <Head :title="`Resignation - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto flex max-w-3xl flex-col gap-6">
            <div>
                <h1
                    class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                >
                    Resignation
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    File a resignation and track your clearance, final pay and
                    certificate of employment.
                </p>
            </div>

            <template v-if="offboarding">
                <Card data-test="offboarding-status">
                    <CardHeader>
                        <CardTitle class="text-base">
                            {{ offboarding.separation_type_label }} &middot;
                            {{
                                offboarding.is_separated
                                    ? 'Separated'
                                    : 'Offboarding in progress'
                            }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid gap-4 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">
                                    Date Filed
                                </dt>
                                <dd>
                                    {{ formatDate(offboarding.filed_date) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">
                                    Last Working Day
                                </dt>
                                <dd>
                                    {{
                                        formatDate(
                                            offboarding.last_working_date,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="offboarding.reason"
                                class="sm:col-span-2"
                            >
                                <dt class="text-slate-500 dark:text-slate-400">
                                    Reason
                                </dt>
                                <dd class="whitespace-pre-line">
                                    {{ offboarding.reason }}
                                </dd>
                            </div>
                            <div v-for="step in steps" :key="step.label">
                                <dt class="text-slate-500 dark:text-slate-400">
                                    {{ step.label }}
                                </dt>
                                <dd>
                                    {{ step.value }}
                                    <template v-if="step.date">
                                        &middot; {{ formatDate(step.date) }}
                                    </template>
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="text-base">
                            Clearance Checklist
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul class="flex flex-col gap-2 text-sm">
                            <li
                                v-for="item in offboarding.clearance_items"
                                :key="item.id"
                                class="flex items-center gap-2"
                            >
                                <CheckCircle2
                                    v-if="item.is_cleared"
                                    class="h-4 w-4 text-green-600"
                                />
                                <Circle v-else class="h-4 w-4 text-slate-400" />
                                <span>{{ item.name }}</span>
                                <span
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        item.is_cleared ? 'Cleared' : 'Pending'
                                    }}
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </template>

            <Card v-else-if="hasEmployeeProfile && canFile">
                <CardHeader>
                    <CardTitle class="text-base">File a Resignation</CardTitle>
                </CardHeader>
                <CardContent>
                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="space-y-1.5">
                            <Label for="last-working-date">
                                Intended Last Working Day
                                <span class="text-red-500">*</span>
                            </Label>
                            <Input
                                id="last-working-date"
                                v-model="form.last_working_date"
                                type="date"
                                class="sm:max-w-xs"
                                required
                            />
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                A 30-day notice is the usual requirement. HR
                                will confirm your final date.
                            </p>
                            <InputError
                                :message="form.errors.last_working_date"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="reason">Reason</Label>
                            <Textarea
                                id="reason"
                                v-model="form.reason"
                                rows="4"
                                maxlength="500"
                            />
                            <InputError :message="form.errors.reason" />
                        </div>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-test="file-resignation"
                        >
                            File Resignation
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <Card v-else>
                <CardContent
                    class="pt-6 text-sm text-slate-500 dark:text-slate-400"
                >
                    {{
                        hasEmployeeProfile
                            ? 'Online resignation filing is not available for your account. Please contact HR.'
                            : 'No employee profile is linked to your account.'
                    }}
                </CardContent>
            </Card>
        </div>
    </TenantLayout>
</template>
