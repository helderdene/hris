<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import OfferPreview from '@/components/Recruitment/OfferPreview.vue';
import RichTextEditor from '@/components/Recruitment/RichTextEditor.vue';
import PlaceholderInsertMenu from '@/components/Recruitment/PlaceholderInsertMenu.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface Template {
    id: number;
    name: string;
    content: string;
    is_default: boolean;
}

interface JobApplicationOption {
    id: number;
    label: string;
    candidate: { id: number; full_name: string; email: string };
    job_posting: { id: number; title: string };
}

interface JobApplicationData {
    id: number;
    candidate: { id: number; full_name: string; email: string };
    job_posting: { id: number; title: string };
}

const props = defineProps<{
    jobApplication: JobApplicationData | null;
    jobApplications: JobApplicationOption[];
    templates: Template[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Offers', href: '/recruitment/offers' },
    { title: 'Create', href: '#' },
];

const form = ref({
    job_application_id: props.jobApplication?.id ? String(props.jobApplication.id) : '',
    offer_template_id: '',
    content: '',
    salary: '',
    salary_currency: 'PHP',
    salary_frequency: 'monthly',
    benefits: [''],
    terms: '',
    start_date: '',
    expiry_date: '',
    position_title: props.jobApplication?.job_posting?.title ?? '',
    department: '',
    work_location: '',
    employment_type: 'full_time',
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);
const showPreview = ref(false);
const editorRef = ref<InstanceType<typeof RichTextEditor> | null>(null);

// Set default template
const defaultTemplate = props.templates.find((t) => t.is_default);
if (defaultTemplate) {
    form.value.offer_template_id = String(defaultTemplate.id);
    form.value.content = defaultTemplate.content;
}

watch(
    () => form.value.offer_template_id,
    (templateId) => {
        if (templateId) {
            const template = props.templates.find((t) => t.id === Number(templateId));
            if (template) {
                form.value.content = template.content;
            }
        }
    },
);

// When job application changes, auto-fill position title
watch(
    () => form.value.job_application_id,
    (appId) => {
        if (appId) {
            const app = props.jobApplications.find((a) => a.id === Number(appId));
            if (app && !form.value.position_title) {
                form.value.position_title = app.job_posting.title;
            }
        }
    },
);

function addBenefit(): void {
    form.value.benefits.push('');
}

function removeBenefit(index: number): void {
    form.value.benefits.splice(index, 1);
}

function handleInsertPlaceholder(placeholder: string): void {
    editorRef.value?.insertText(placeholder);
}

function submit(): void {
    processing.value = true;
    errors.value = {};

    const data = {
        ...form.value,
        job_application_id: Number(form.value.job_application_id) || '',
        offer_template_id: form.value.offer_template_id ? Number(form.value.offer_template_id) : null,
        benefits: form.value.benefits.filter((b) => b.trim()),
    };

    router.post('/api/offers', data, {
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Create Offer - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Create Offer
                </h1>
                <p
                    v-if="jobApplication"
                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                >
                    For {{ jobApplication.candidate.full_name }} —
                    {{ jobApplication.job_posting.title }}
                </p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Job Application Selection (when not pre-selected) -->
                <Card v-if="!jobApplication">
                    <CardHeader>
                        <CardTitle>Job Application</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-2">
                            <Label>Select Candidate</Label>
                            <Select v-model="form.job_application_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a job application..." />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="app in jobApplications"
                                        :key="app.id"
                                        :value="String(app.id)"
                                    >
                                        {{ app.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="errors.job_application_id" class="text-sm text-destructive">{{ errors.job_application_id }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Template Selection -->
                <Card>
                    <CardHeader>
                        <CardTitle>Template</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label>Offer Template</Label>
                            <Select v-model="form.offer_template_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Select a template (optional)" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in templates" :key="t.id" :value="String(t.id)">
                                        {{ t.name }}
                                        <span v-if="t.is_default" class="text-xs text-muted-foreground">(Default)</span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>Offer Content</Label>
                                <div class="flex items-center gap-2">
                                    <PlaceholderInsertMenu @insert="handleInsertPlaceholder" />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="showPreview = !showPreview"
                                    >
                                        {{ showPreview ? 'Edit' : 'Preview' }}
                                    </Button>
                                </div>
                            </div>
                            <OfferPreview v-if="showPreview" :content="form.content" />
                            <RichTextEditor
                                v-else
                                ref="editorRef"
                                v-model="form.content"
                                placeholder="Write offer content..."
                            />
                            <p v-if="errors.content" class="text-sm text-destructive">{{ errors.content }}</p>
                        </div>
                    </CardContent>
                </Card>

                <!-- Compensation Details -->
                <Card>
                    <CardHeader>
                        <CardTitle>Compensation</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label for="salary">Salary</Label>
                                <Input id="salary" v-model="form.salary" type="number" step="0.01" min="0" />
                                <p v-if="errors.salary" class="text-sm text-destructive">{{ errors.salary }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label>Currency</Label>
                                <Select v-model="form.salary_currency">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="PHP">PHP</SelectItem>
                                        <SelectItem value="USD">USD</SelectItem>
                                        <SelectItem value="EUR">EUR</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-2">
                                <Label>Frequency</Label>
                                <Select v-model="form.salary_frequency">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="monthly">Monthly</SelectItem>
                                        <SelectItem value="semi_monthly">Semi-Monthly</SelectItem>
                                        <SelectItem value="annual">Annual</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label>Benefits</Label>
                            <div v-for="(benefit, index) in form.benefits" :key="index" class="flex items-center gap-2">
                                <Input v-model="form.benefits[index]" placeholder="e.g., Health Insurance" />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="removeBenefit(index)"
                                    :disabled="form.benefits.length <= 1"
                                >
                                    Remove
                                </Button>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="addBenefit">
                                Add Benefit
                            </Button>
                        </div>

                        <div class="space-y-2">
                            <Label for="terms">Additional Terms</Label>
                            <Textarea id="terms" v-model="form.terms" rows="3" placeholder="Any additional terms..." />
                        </div>
                    </CardContent>
                </Card>

                <!-- Position Details -->
                <Card>
                    <CardHeader>
                        <CardTitle>Position Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="position_title">Position Title</Label>
                                <Input id="position_title" v-model="form.position_title" />
                                <p v-if="errors.position_title" class="text-sm text-destructive">{{ errors.position_title }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="department">Department</Label>
                                <Input id="department" v-model="form.department" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="work_location">Work Location</Label>
                                <Input id="work_location" v-model="form.work_location" />
                            </div>
                            <div class="space-y-2">
                                <Label>Employment Type</Label>
                                <Select v-model="form.employment_type">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="full_time">Full Time</SelectItem>
                                        <SelectItem value="part_time">Part Time</SelectItem>
                                        <SelectItem value="contract">Contract</SelectItem>
                                        <SelectItem value="internship">Internship</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label for="start_date">Start Date</Label>
                                <Input id="start_date" v-model="form.start_date" type="date" />
                                <p v-if="errors.start_date" class="text-sm text-destructive">{{ errors.start_date }}</p>
                            </div>
                            <div class="space-y-2">
                                <Label for="expiry_date">Offer Expiry Date</Label>
                                <Input id="expiry_date" v-model="form.expiry_date" type="date" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="router.visit('/recruitment/offers')">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Creating...' : 'Create Offer' }}
                    </Button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
