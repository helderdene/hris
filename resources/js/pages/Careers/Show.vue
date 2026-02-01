<script setup lang="ts">
import { useTenant } from '@/composables/useTenant';
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface JobPostingDetail {
    id: number;
    slug: string;
    title: string;
    department: { id: number; name: string };
    description: string;
    requirements: string | null;
    benefits: string | null;
    employment_type_label: string;
    location: string;
    salary_display: string | null;
    application_instructions: string | null;
    published_at: string | null;
}

const props = defineProps<{
    posting: JobPostingDetail;
}>();

const { tenantName } = useTenant();

const mounted = ref(false);
onMounted(() => {
    setTimeout(() => {
        mounted.value = true;
    }, 100);
});

const currentYear = computed(() => new Date().getFullYear());

const page = usePage();
const applicationSubmitted = computed(() => page.props.flash?.success != null);
const applicationError = ref('');

</script>

<template>
    <Head :title="`${posting.title} - Careers - ${tenantName}`">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin="anonymous"
        />
        <link
            href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="careers-page relative min-h-screen overflow-hidden bg-slate-50 dark:bg-slate-900"
    >
        <!-- Ambient Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-40 -left-40 h-[600px] w-[600px] rounded-full opacity-[0.04] blur-3xl transition-all duration-1000 dark:opacity-[0.06]"
                :class="
                    mounted
                        ? 'scale-100 bg-blue-500'
                        : 'scale-50 bg-transparent'
                "
            />
            <div
                class="absolute top-1/4 -right-20 h-[500px] w-[500px] rounded-full opacity-[0.03] blur-3xl transition-all delay-300 duration-1000 dark:opacity-[0.05]"
                :class="
                    mounted
                        ? 'scale-100 bg-emerald-500'
                        : 'scale-50 bg-transparent'
                "
            />
            <svg
                class="absolute inset-0 h-full w-full opacity-[0.02] dark:opacity-[0.04]"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <pattern
                        id="grid-show"
                        width="60"
                        height="60"
                        patternUnits="userSpaceOnUse"
                    >
                        <path
                            d="M 60 0 L 0 0 0 60"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                        />
                    </pattern>
                </defs>
                <rect
                    width="100%"
                    height="100%"
                    fill="url(#grid-show)"
                    class="text-slate-900 dark:text-slate-100"
                />
            </svg>
        </div>

        <!-- Navigation -->
        <header
            class="relative z-10 border-b border-slate-200/60 bg-slate-50/80 backdrop-blur-xl transition-all duration-700 dark:border-slate-700/60 dark:bg-slate-900/80"
            :class="
                mounted
                    ? 'translate-y-0 opacity-100'
                    : '-translate-y-4 opacity-0'
            "
        >
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6"
            >
                <Link
                    href="/"
                    class="flex items-center gap-3 transition-opacity hover:opacity-80"
                >
                    <div
                        class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-600"
                    >
                        <svg
                            class="h-5 w-5 text-white"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                            />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                    </div>
                    <span
                        class="text-lg font-semibold tracking-tight text-slate-900 dark:text-slate-100"
                        >{{ tenantName }}</span
                    >
                </Link>
                <nav class="flex items-center gap-2">
                    <Link
                        href="/careers"
                        class="inline-flex h-9 items-center justify-center rounded-lg px-4 text-sm font-medium text-slate-700 transition-all hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        All Positions
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="relative z-10">
            <!-- Job Header -->
            <section class="mx-auto max-w-6xl px-6 pt-12 pb-8 lg:pt-16">
                <div
                    class="transition-all delay-150 duration-700"
                    :class="
                        mounted
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-8 opacity-0'
                    "
                >
                    <!-- Back -->
                    <Link
                        href="/careers"
                        class="mb-8 inline-flex items-center gap-2 text-sm text-slate-500 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-100"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M19 12H5" />
                            <path d="m12 19-7-7 7-7" />
                        </svg>
                        Back to all positions
                    </Link>

                    <h1
                        class="mb-4 text-3xl leading-tight font-bold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl dark:text-slate-100"
                    >
                        {{ posting.title }}
                    </h1>

                    <!-- Meta pills -->
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
                        >
                            <svg
                                class="h-3.5 w-3.5 text-slate-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path
                                    d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                />
                                <circle cx="9" cy="7" r="4" />
                            </svg>
                            {{ posting.department.name }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
                        >
                            <svg
                                class="h-3.5 w-3.5 text-slate-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
                                <circle cx="12" cy="10" r="3" />
                            </svg>
                            {{ posting.location }}
                        </span>
                        <span
                            class="inline-flex items-center rounded-lg bg-white px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700"
                        >
                            {{ posting.employment_type_label }}
                        </span>
                        <span
                            v-if="posting.salary_display"
                            class="inline-flex items-center rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-400 dark:ring-emerald-800"
                        >
                            {{ posting.salary_display }}
                        </span>
                    </div>

                    <div
                        v-if="posting.published_at"
                        class="mt-4 text-sm text-slate-400 dark:text-slate-500"
                    >
                        Posted {{ posting.published_at }}
                    </div>
                </div>
            </section>

            <!-- Job Content -->
            <section
                class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-800/50"
            >
                <div class="mx-auto max-w-6xl px-6 py-12 lg:py-16">
                    <div class="grid gap-12 lg:grid-cols-3">
                        <!-- Main Content -->
                        <div
                            class="space-y-10 lg:col-span-2 transition-all delay-300 duration-700"
                            :class="
                                mounted
                                    ? 'translate-y-0 opacity-100'
                                    : 'translate-y-8 opacity-0'
                            "
                        >
                            <div>
                                <h2
                                    class="mb-4 text-xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    About the Role
                                </h2>
                                <div
                                    class="whitespace-pre-wrap text-base leading-relaxed text-slate-600 dark:text-slate-400"
                                >{{ posting.description }}</div>
                            </div>

                            <div v-if="posting.requirements">
                                <h2
                                    class="mb-4 text-xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    Requirements
                                </h2>
                                <div
                                    class="whitespace-pre-wrap text-base leading-relaxed text-slate-600 dark:text-slate-400"
                                >{{ posting.requirements }}</div>
                            </div>

                            <div v-if="posting.benefits">
                                <h2
                                    class="mb-4 text-xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    Benefits
                                </h2>
                                <div
                                    class="whitespace-pre-wrap text-base leading-relaxed text-slate-600 dark:text-slate-400"
                                >{{ posting.benefits }}</div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div
                            class="transition-all delay-500 duration-700"
                            :class="
                                mounted
                                    ? 'translate-y-0 opacity-100'
                                    : 'translate-y-8 opacity-0'
                            "
                        >
                            <div class="sticky top-8 space-y-6">
                                <!-- Apply Card -->
                                <div
                                    v-if="posting.application_instructions"
                                    class="overflow-hidden rounded-2xl border border-blue-200 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800 dark:from-blue-950 dark:to-blue-900/50"
                                >
                                    <div class="p-6">
                                        <div class="mb-3 flex items-center gap-2">
                                            <div
                                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600 text-white"
                                            >
                                                <svg
                                                    class="h-4 w-4"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                >
                                                    <path d="M5 12h14" />
                                                    <path d="m12 5 7 7-7 7" />
                                                </svg>
                                            </div>
                                            <h3
                                                class="text-lg font-bold text-blue-900 dark:text-blue-100"
                                            >
                                                How to Apply
                                            </h3>
                                        </div>
                                        <div
                                            class="whitespace-pre-wrap text-sm leading-relaxed text-blue-800 dark:text-blue-200"
                                        >{{ posting.application_instructions }}</div>
                                    </div>
                                </div>

                                <!-- Job Details Card -->
                                <div
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-6 dark:border-slate-700 dark:bg-slate-800"
                                >
                                    <h3
                                        class="mb-4 text-sm font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                                    >
                                        Job Details
                                    </h3>
                                    <dl class="space-y-4">
                                        <div>
                                            <dt
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Department
                                            </dt>
                                            <dd
                                                class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{ posting.department.name }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Location
                                            </dt>
                                            <dd
                                                class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{ posting.location }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Employment Type
                                            </dt>
                                            <dd
                                                class="mt-0.5 text-sm font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{
                                                    posting.employment_type_label
                                                }}
                                            </dd>
                                        </div>
                                        <div v-if="posting.salary_display">
                                            <dt
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Compensation
                                            </dt>
                                            <dd
                                                class="mt-0.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400"
                                            >
                                                {{ posting.salary_display }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Back Link -->
                                <Link
                                    href="/careers"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white py-3 text-sm font-semibold text-slate-900 transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-lg dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:border-slate-500"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    >
                                        <path d="M19 12H5" />
                                        <path d="m12 19-7-7 7-7" />
                                    </svg>
                                    View All Positions
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <!-- Application Form -->
        <section
            id="apply"
            class="border-t border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="mx-auto max-w-6xl px-6 py-12 lg:py-16">
                <div class="mx-auto max-w-xl">
                    <div
                        class="transition-all delay-500 duration-700"
                        :class="mounted ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'"
                    >
                        <h2 class="mb-6 text-center text-2xl font-bold text-slate-900 dark:text-slate-100">
                            Apply for this Position
                        </h2>

                        <div v-if="applicationSubmitted" class="rounded-2xl border border-green-200 bg-green-50 p-6 text-center dark:border-green-800 dark:bg-green-900/30">
                            <p class="text-lg font-semibold text-green-800 dark:text-green-200">Application Submitted!</p>
                            <p class="mt-2 text-sm text-green-700 dark:text-green-300">Thank you for your interest. We will review your application and get back to you.</p>
                        </div>

                        <Form
                            v-else
                            :action="`/careers/${posting.slug}/apply`"
                            method="post"
                            enctype="multipart/form-data"
                            #default="{ errors, processing, wasSuccessful }"
                            @success="applicationSubmitted = true"
                            class="space-y-4"
                        >
                            <div v-if="applicationError" class="rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-300">
                                {{ applicationError }}
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">First Name *</label>
                                    <input
                                        name="first_name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                    />
                                    <p v-if="errors.first_name" class="mt-1 text-xs text-red-600">{{ errors.first_name }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Last Name *</label>
                                    <input
                                        name="last_name"
                                        type="text"
                                        required
                                        class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                    />
                                    <p v-if="errors.last_name" class="mt-1 text-xs text-red-600">{{ errors.last_name }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email *</label>
                                <input
                                    name="email"
                                    type="email"
                                    required
                                    class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                />
                                <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                                <input
                                    name="phone"
                                    type="text"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                />
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Resume</label>
                                <input
                                    name="resume"
                                    type="file"
                                    accept=".pdf,.docx"
                                    class="w-full text-sm text-slate-600 dark:text-slate-400 file:mr-4 file:rounded-lg file:border-0 file:bg-blue-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/30 dark:file:text-blue-300"
                                />
                                <p class="mt-1 text-xs text-slate-500">PDF or DOCX, max 5MB</p>
                                <p v-if="errors.resume" class="mt-1 text-xs text-red-600">{{ errors.resume }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Cover Letter</label>
                                <textarea
                                    name="cover_letter"
                                    rows="4"
                                    class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                    placeholder="Tell us why you'd be a great fit..."
                                ></textarea>
                            </div>

                            <button
                                type="submit"
                                :disabled="processing"
                                class="w-full rounded-xl bg-blue-600 py-3 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg disabled:opacity-50"
                            >
                                {{ processing ? 'Submitting...' : 'Submit Application' }}
                            </button>
                        </Form>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer
            class="relative z-10 border-t border-slate-200 transition-all duration-700 dark:border-slate-800"
            :class="
                mounted
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-4 opacity-0'
            "
            style="transition-delay: 700ms"
        >
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-6 sm:flex-row"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    &copy; {{ currentYear }} {{ tenantName }}
                </p>
                <div
                    class="flex items-center gap-6 text-sm text-slate-500 dark:text-slate-400"
                >
                    <a
                        href="#"
                        class="transition-colors hover:text-slate-900 dark:hover:text-slate-100"
                        >Privacy</a
                    >
                    <a
                        href="#"
                        class="transition-colors hover:text-slate-900 dark:hover:text-slate-100"
                        >Terms</a
                    >
                </div>
            </div>
        </footer>
    </div>

    <style scoped>
        .careers-page {
            font-family:
                'DM Sans',
                system-ui,
                -apple-system,
                sans-serif;
        }
        .careers-page .font-mono {
            font-family:
                'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
        }
    </style>
</template>
