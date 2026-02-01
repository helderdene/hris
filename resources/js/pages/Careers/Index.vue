<script setup lang="ts">
import { useTenant } from '@/composables/useTenant';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface JobPosting {
    id: number;
    slug: string;
    title: string;
    department: { id: number; name: string };
    employment_type_label: string;
    location: string;
    salary_display: string | null;
    published_at: string | null;
}

interface Department {
    id: number;
    name: string;
}

interface Filters {
    department_id: string | null;
    search: string | null;
}

const props = defineProps<{
    postings: { data: JobPosting[]; links: any; meta: any };
    departments: Department[];
    filters: Filters;
}>();

const { tenantName } = useTenant();

const mounted = ref(false);
onMounted(() => {
    setTimeout(() => {
        mounted.value = true;
    }, 100);
});

const selectedDepartment = ref(
    props.filters.department_id ? String(props.filters.department_id) : '',
);
const searchQuery = ref(props.filters.search || '');

function reloadPage() {
    const params: Record<string, string> = {};
    if (selectedDepartment.value) params.department_id = selectedDepartment.value;
    if (searchQuery.value) params.search = searchQuery.value;
    router.get('/careers', params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function handleDepartmentChange(e: Event) {
    selectedDepartment.value = (e.target as HTMLSelectElement).value;
    reloadPage();
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
function handleSearch() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        reloadPage();
    }, 300);
}

const currentYear = computed(() => new Date().getFullYear());
</script>

<template>
    <Head :title="`Careers - ${tenantName}`">
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
                        id="grid"
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
                    fill="url(#grid)"
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
                        class="inline-flex h-9 items-center justify-center rounded-lg bg-blue-50 px-4 text-sm font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                    >
                        Careers
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="relative z-10">
            <!-- Hero Section -->
            <section class="mx-auto max-w-6xl px-6 pt-16 pb-12 lg:pt-24 lg:pb-16">
                <div
                    class="mx-auto max-w-3xl text-center transition-all delay-150 duration-700"
                    :class="
                        mounted
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-8 opacity-0'
                    "
                >
                    <div
                        class="mb-6 inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/60 px-3 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400"
                    >
                        <span class="relative flex h-2 w-2">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                            ></span>
                        </span>
                        We're hiring
                    </div>

                    <h1
                        class="mb-6 text-4xl leading-[1.1] font-bold tracking-tight text-slate-900 sm:text-5xl dark:text-slate-100"
                    >
                        Build your
                        <span class="relative">
                            <span class="relative z-10">career</span>
                            <span
                                class="absolute right-0 -bottom-1 left-0 h-3 bg-gradient-to-r from-blue-500/20 to-emerald-500/20 dark:from-blue-500/30 dark:to-emerald-500/30"
                            ></span>
                        </span>
                        with us
                    </h1>

                    <p
                        class="mx-auto mb-10 max-w-xl text-lg leading-relaxed text-slate-600 dark:text-slate-400"
                    >
                        Explore open positions and join a team where your work
                        makes a real impact. We're looking for talented people
                        to grow with us.
                    </p>
                </div>
            </section>

            <!-- Filters & Listings -->
            <section
                class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-800/50"
            >
                <div class="mx-auto max-w-6xl px-6 py-12 lg:py-16">
                    <!-- Filters -->
                    <div
                        class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center transition-all delay-300 duration-700"
                        :class="
                            mounted
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-4 opacity-0'
                        "
                    >
                        <div class="relative flex-1 sm:max-w-sm">
                            <svg
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <circle cx="11" cy="11" r="8" />
                                <path d="m21 21-4.3-4.3" />
                            </svg>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search positions..."
                                class="h-11 w-full rounded-xl border border-slate-200 bg-slate-50 pl-10 pr-4 text-sm text-slate-900 placeholder-slate-400 outline-none transition-colors focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500 dark:focus:border-blue-400 dark:focus:bg-slate-800"
                                @input="handleSearch"
                            />
                        </div>
                        <select
                            :value="selectedDepartment"
                            class="h-11 rounded-xl border border-slate-200 bg-slate-50 px-4 text-sm text-slate-700 outline-none transition-colors focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-500/10 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:focus:border-blue-400"
                            @change="handleDepartmentChange"
                        >
                            <option value="">All Departments</option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="String(dept.id)"
                            >
                                {{ dept.name }}
                            </option>
                        </select>
                        <div class="ml-auto text-sm text-slate-500 dark:text-slate-400">
                            <span class="font-mono font-semibold text-slate-900 dark:text-slate-100">{{ postings.data.length }}</span>
                            open {{ postings.data.length === 1 ? 'position' : 'positions' }}
                        </div>
                    </div>

                    <!-- Job Listings -->
                    <div
                        v-if="postings.data.length > 0"
                        class="grid gap-4 sm:grid-cols-2"
                    >
                        <Link
                            v-for="(posting, index) in postings.data"
                            :key="posting.id"
                            :href="`/careers/${posting.slug}`"
                            class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-6 transition-all duration-300 hover:-translate-y-1 hover:border-slate-300 hover:bg-white hover:shadow-lg dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600 dark:hover:bg-slate-700"
                            :class="
                                mounted
                                    ? 'translate-y-0 opacity-100'
                                    : 'translate-y-8 opacity-0'
                            "
                            :style="{
                                transitionDelay: `${400 + index * 80}ms`,
                            }"
                        >
                            <!-- Hover accent -->
                            <div
                                class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-blue-500 to-emerald-500 opacity-0 transition-opacity group-hover:opacity-100"
                            />

                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <h2
                                        class="text-lg font-semibold text-slate-900 transition-colors group-hover:text-blue-600 dark:text-slate-100 dark:group-hover:text-blue-400"
                                    >
                                        {{ posting.title }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ posting.department.name }}
                                    </p>
                                </div>
                                <svg
                                    class="mt-1 h-5 w-5 shrink-0 text-slate-300 transition-all group-hover:translate-x-0.5 group-hover:text-blue-500 dark:text-slate-600"
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

                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-200/60 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-700/60 dark:text-slate-300"
                                >
                                    <svg
                                        class="h-3 w-3"
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
                                    class="inline-flex items-center rounded-lg bg-slate-200/60 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-700/60 dark:text-slate-300"
                                >
                                    {{ posting.employment_type_label }}
                                </span>
                                <span
                                    v-if="posting.salary_display"
                                    class="inline-flex items-center rounded-lg bg-emerald-100/60 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                                >
                                    {{ posting.salary_display }}
                                </span>
                            </div>

                            <div
                                v-if="posting.published_at"
                                class="mt-4 text-xs text-slate-400 dark:text-slate-500"
                            >
                                Posted {{ posting.published_at }}
                            </div>
                        </Link>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="py-20 text-center transition-all delay-400 duration-700"
                        :class="
                            mounted
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0'
                        "
                    >
                        <div
                            class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800"
                        >
                            <svg
                                class="h-8 w-8 text-slate-400 dark:text-slate-500"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <rect
                                    x="2"
                                    y="7"
                                    width="20"
                                    height="14"
                                    rx="2"
                                    ry="2"
                                />
                                <path
                                    d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"
                                />
                            </svg>
                        </div>
                        <h3
                            class="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100"
                        >
                            No open positions right now
                        </h3>
                        <p
                            class="mx-auto max-w-sm text-sm text-slate-500 dark:text-slate-400"
                        >
                            We don't have any openings at the moment, but check
                            back soon — new roles are posted regularly.
                        </p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer
            class="relative z-10 border-t border-slate-200 transition-all duration-700 dark:border-slate-800"
            :class="
                mounted
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-4 opacity-0'
            "
            style="transition-delay: 600ms"
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
