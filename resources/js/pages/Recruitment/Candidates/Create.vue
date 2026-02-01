<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface EducationLevelOption {
    value: string;
    label: string;
}

const props = defineProps<{
    educationLevels: EducationLevelOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/candidates' },
    { title: 'Candidates', href: '/recruitment/candidates' },
    { title: 'Add Candidate', href: '/recruitment/candidates/create' },
];

const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    date_of_birth: '',
    address: '',
    city: '',
    state: '',
    zip_code: '',
    country: '',
    linkedin_url: '',
    portfolio_url: '',
    skills: [] as string[],
    notes: '',
    education: [] as { education_level: string; institution: string; field_of_study: string; start_date: string; end_date: string; is_current: boolean }[],
    work_experience: [] as { company: string; job_title: string; description: string; start_date: string; end_date: string; is_current: boolean }[],
});

const resume = ref<File | null>(null);
const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);
const skillInput = ref('');
const duplicateWarning = ref<{ exact: any[]; potential: any[] } | null>(null);

function addSkill() {
    const skill = skillInput.value.trim();
    if (skill && !form.value.skills.includes(skill)) {
        form.value.skills.push(skill);
        skillInput.value = '';
    }
}

function removeSkill(index: number) {
    form.value.skills.splice(index, 1);
}

function addEducation() {
    form.value.education.push({
        education_level: '',
        institution: '',
        field_of_study: '',
        start_date: '',
        end_date: '',
        is_current: false,
    });
}

function removeEducation(index: number) {
    form.value.education.splice(index, 1);
}

function addWorkExperience() {
    form.value.work_experience.push({
        company: '',
        job_title: '',
        description: '',
        start_date: '',
        end_date: '',
        is_current: false,
    });
}

function removeWorkExperience(index: number) {
    form.value.work_experience.splice(index, 1);
}

function onResumeChange(event: Event) {
    const target = event.target as HTMLInputElement;
    resume.value = target.files?.[0] || null;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function checkDuplicates() {
    if (!form.value.email && !form.value.phone) {
        return;
    }

    try {
        const response = await fetch('/api/candidates/check-duplicates', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                email: form.value.email,
                phone: form.value.phone,
                first_name: form.value.first_name,
                last_name: form.value.last_name,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            if (data.has_duplicates) {
                duplicateWarning.value = data;
            } else {
                duplicateWarning.value = null;
            }
        }
    } catch {
        // Silently fail
    }
}

async function submit() {
    isSubmitting.value = true;
    errors.value = {};

    const formData = new FormData();
    Object.entries(form.value).forEach(([key, value]) => {
        if (key === 'education' || key === 'work_experience' || key === 'skills') {
            formData.append(key, JSON.stringify(value));
        } else if (value) {
            formData.append(key, String(value));
        }
    });

    if (resume.value) {
        formData.append('resume', resume.value);
    }

    try {
        const response = await fetch('/api/candidates', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: formData,
        });

        if (response.ok) {
            const data = await response.json();
            router.visit(`/recruitment/candidates/${data.data.id}`);
        } else if (response.status === 422) {
            const data = await response.json();
            errors.value = data.errors || {};
        }
    } catch {
        // Error handling
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Head :title="`Add Candidate - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <h1 class="mb-6 text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Add Candidate</h1>

            <!-- Duplicate Warning -->
            <div
                v-if="duplicateWarning"
                class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
            >
                <p class="font-medium text-amber-800 dark:text-amber-200">Possible duplicate detected</p>
                <div v-if="duplicateWarning.exact.length" class="mt-2">
                    <p class="text-sm text-amber-700 dark:text-amber-300">Exact matches (email/phone):</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-amber-600 dark:text-amber-400">
                        <li v-for="c in duplicateWarning.exact" :key="c.id">{{ c.full_name }} ({{ c.email }})</li>
                    </ul>
                </div>
                <div v-if="duplicateWarning.potential.length" class="mt-2">
                    <p class="text-sm text-amber-700 dark:text-amber-300">Similar names:</p>
                    <ul class="mt-1 list-inside list-disc text-sm text-amber-600 dark:text-amber-400">
                        <li v-for="c in duplicateWarning.potential" :key="c.id">{{ c.full_name }} ({{ c.email }})</li>
                    </ul>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
                <!-- Personal Info -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Personal Information</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">First Name *</label>
                            <input v-model="form.first_name" type="text" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            <p v-if="errors.first_name" class="mt-1 text-xs text-red-600">{{ errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Last Name *</label>
                            <input v-model="form.last_name" type="text" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email *</label>
                            <input v-model="form.email" type="email" required @blur="checkDuplicates" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                            <input v-model="form.phone" type="text" @blur="checkDuplicates" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Date of Birth</label>
                            <input v-model="form.date_of_birth" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Country</label>
                            <input v-model="form.country" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                        </div>
                    </div>
                </div>

                <!-- Resume -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Resume</h2>
                    <input type="file" accept=".pdf,.docx" @change="onResumeChange" class="text-sm text-slate-600 dark:text-slate-400" />
                    <p class="mt-1 text-xs text-slate-500">PDF or DOCX, max 5MB</p>
                </div>

                <!-- Skills -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Skills</h2>
                    <div class="flex gap-2">
                        <input
                            v-model="skillInput"
                            type="text"
                            placeholder="Add a skill..."
                            @keydown.enter.prevent="addSkill"
                            class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        />
                        <Button type="button" variant="outline" size="sm" @click="addSkill">Add</Button>
                    </div>
                    <div v-if="form.skills.length" class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="(skill, i) in form.skills"
                            :key="i"
                            class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-300"
                        >
                            {{ skill }}
                            <button type="button" @click="removeSkill(i)" class="ml-1 text-blue-500 hover:text-blue-700">&times;</button>
                        </span>
                    </div>
                </div>

                <!-- Education -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Education</h2>
                        <Button type="button" variant="outline" size="sm" @click="addEducation">Add Education</Button>
                    </div>
                    <div v-for="(edu, i) in form.education" :key="i" class="mb-4 rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <div class="mb-2 flex justify-end">
                            <button type="button" @click="removeEducation(i)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Level</label>
                                <select v-model="edu.education_level" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                    <option value="">Select...</option>
                                    <option v-for="level in educationLevels" :key="level.value" :value="level.value">{{ level.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Institution</label>
                                <input v-model="edu.institution" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Field of Study</label>
                                <input v-model="edu.field_of_study" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Start Date</label>
                                <input v-model="edu.start_date" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">End Date</label>
                                <input v-model="edu.end_date" type="date" :disabled="edu.is_current" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="edu.is_current" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" />
                                <label class="text-xs text-slate-600 dark:text-slate-400">Currently studying</label>
                            </div>
                        </div>
                    </div>
                    <p v-if="!form.education.length" class="text-sm text-slate-500 dark:text-slate-400">No education records added.</p>
                </div>

                <!-- Work Experience -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">Work Experience</h2>
                        <Button type="button" variant="outline" size="sm" @click="addWorkExperience">Add Experience</Button>
                    </div>
                    <div v-for="(exp, i) in form.work_experience" :key="i" class="mb-4 rounded-lg border border-slate-100 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800">
                        <div class="mb-2 flex justify-end">
                            <button type="button" @click="removeWorkExperience(i)" class="text-xs text-red-500 hover:text-red-700">Remove</button>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Company</label>
                                <input v-model="exp.company" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Job Title</label>
                                <input v-model="exp.job_title" type="text" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Description</label>
                                <textarea v-model="exp.description" rows="2" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></textarea>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">Start Date</label>
                                <input v-model="exp.start_date" type="date" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">End Date</label>
                                <input v-model="exp.end_date" type="date" :disabled="exp.is_current" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="exp.is_current" type="checkbox" class="rounded border-slate-300 dark:border-slate-600" />
                                <label class="text-xs text-slate-600 dark:text-slate-400">Currently working here</label>
                            </div>
                        </div>
                    </div>
                    <p v-if="!form.work_experience.length" class="text-sm text-slate-500 dark:text-slate-400">No work experience added.</p>
                </div>

                <!-- Notes -->
                <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Notes</h2>
                    <textarea v-model="form.notes" rows="3" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Any additional notes..."></textarea>
                </div>

                <div class="flex justify-end gap-3">
                    <Button type="button" variant="outline" @click="router.visit('/recruitment/candidates')">Cancel</Button>
                    <Button type="submit" :disabled="isSubmitting">
                        {{ isSubmitting ? 'Saving...' : 'Save Candidate' }}
                    </Button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
