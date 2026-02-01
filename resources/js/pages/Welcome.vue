<script setup lang="ts">
import { login, register } from '@/routes';
import { select as tenantSelect } from '@/routes/tenant';
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const mounted = ref(false);
onMounted(() => {
    setTimeout(() => {
        mounted.value = true;
    }, 100);
});

const features = [
    {
        icon: 'users',
        title: 'Employee Management',
        description:
            'Complete employee lifecycle from onboarding to offboarding',
    },
    {
        icon: 'calendar',
        title: 'Time & Attendance',
        description: 'Biometric integration with MQTT-enabled devices',
    },
    {
        icon: 'peso',
        title: 'Payroll Processing',
        description:
            'Full Philippine compliance: BIR, SSS, PhilHealth, Pag-IBIG',
    },
    {
        icon: 'chart',
        title: 'Analytics & Reports',
        description: 'Real-time workforce insights and regulatory reporting',
    },
];

const currentYear = computed(() => new Date().getFullYear());
</script>

<template>
    <Head title="KasamaHR - Your HR Companion">
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
        class="welcome-page relative min-h-screen overflow-hidden bg-slate-50 dark:bg-slate-900"
    >
        <!-- Ambient Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <!-- Soft gradient orbs -->
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
            <div
                class="absolute -bottom-32 left-1/3 h-[400px] w-[400px] rounded-full opacity-[0.02] blur-3xl transition-all delay-500 duration-1000 dark:opacity-[0.04]"
                :class="
                    mounted
                        ? 'scale-100 bg-blue-400'
                        : 'scale-50 bg-transparent'
                "
            />

            <!-- Subtle grid pattern -->
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
                <!-- Logo -->
                <Link
                    :href="'/'"
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
                        >KasamaHR</span
                    >
                </Link>

                <!-- Auth Navigation -->
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="tenantSelect()"
                        class="inline-flex h-9 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-medium text-white transition-all hover:bg-blue-700"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="inline-flex h-9 items-center justify-center rounded-lg px-4 text-sm font-medium text-slate-700 transition-all hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-flex h-9 items-center justify-center rounded-lg bg-blue-600 px-4 text-sm font-medium text-white transition-all hover:bg-blue-700"
                        >
                            Get Started
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="relative z-10">
            <section class="mx-auto max-w-6xl px-6 py-20 lg:py-32">
                <div class="grid items-center gap-16 lg:grid-cols-2 lg:gap-20">
                    <!-- Hero Content -->
                    <div
                        class="transition-all delay-150 duration-700"
                        :class="
                            mounted
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0'
                        "
                    >
                        <!-- Badge -->
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
                            Philippine-compliant HRMS
                        </div>

                        <!-- Headline -->
                        <h1
                            class="mb-6 text-4xl leading-[1.1] font-bold tracking-tight text-slate-900 sm:text-5xl lg:text-6xl dark:text-slate-100"
                        >
                            Your trusted
                            <span class="relative">
                                <span class="relative z-10">HR companion</span>
                                <span
                                    class="absolute right-0 -bottom-1 left-0 h-3 bg-gradient-to-r from-blue-500/20 to-emerald-500/20 dark:from-blue-500/30 dark:to-emerald-500/30"
                                ></span>
                            </span>
                            for the Filipino workforce
                        </h1>

                        <!-- Subheadline -->
                        <p
                            class="mb-8 max-w-lg text-lg leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            <strong
                                class="font-medium text-slate-900 dark:text-slate-100"
                                >Kasama</strong
                            >
                            means together. We're here to simplify your HR
                            operations—from hiring to payroll—with full
                            compliance to Philippine labor laws.
                        </p>

                        <!-- CTA Buttons -->
                        <div class="flex flex-wrap items-center gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="tenantSelect()"
                                class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-xl hover:shadow-blue-500/30"
                            >
                                Go to Dashboard
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
                            </Link>
                            <template v-else>
                                <Link
                                    v-if="canRegister"
                                    :href="register()"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white shadow-lg shadow-blue-500/25 transition-all hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-xl hover:shadow-blue-500/30"
                                >
                                    Start for free
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
                                </Link>
                                <Link
                                    :href="login()"
                                    class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-300 bg-white px-6 text-sm font-semibold text-slate-900 transition-all hover:-translate-y-0.5 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:border-slate-500 dark:hover:bg-slate-700"
                                >
                                    Log in to your account
                                </Link>
                            </template>
                        </div>

                        <!-- Trust Indicators -->
                        <div
                            class="mt-10 flex items-center gap-6 text-sm text-slate-500 dark:text-slate-400"
                        >
                            <div class="flex items-center gap-2">
                                <svg
                                    class="h-4 w-4 text-emerald-500"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"
                                    />
                                    <path d="m9 12 2 2 4-4" />
                                </svg>
                                <span>Data secured</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg
                                    class="h-4 w-4 text-emerald-500"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path
                                        d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"
                                    />
                                    <path d="m9 12 2 2 4-4" />
                                </svg>
                                <span>100% PH compliant</span>
                            </div>
                        </div>
                    </div>

                    <!-- Hero Visual -->
                    <div
                        class="relative transition-all delay-300 duration-700"
                        :class="
                            mounted
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-12 opacity-0'
                        "
                    >
                        <!-- Floating Cards Composition -->
                        <div class="relative aspect-square max-w-lg lg:ml-auto">
                            <!-- Background glow -->
                            <div
                                class="absolute inset-0 rounded-3xl bg-gradient-to-br from-blue-500/5 via-transparent to-emerald-500/5"
                            />

                            <!-- Main Card -->
                            <div
                                class="absolute inset-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/5 dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/20"
                            >
                                <!-- Card Header -->
                                <div
                                    class="flex items-center gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 dark:border-slate-700 dark:bg-slate-800/50"
                                >
                                    <div
                                        class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-lg shadow-blue-500/30"
                                    >
                                        <svg
                                            class="h-5 w-5"
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
                                            <path
                                                d="M22 21v-2a4 4 0 0 0-3-3.87"
                                            />
                                            <path
                                                d="M16 3.13a4 4 0 0 1 0 7.75"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            Team Overview
                                        </p>
                                        <p
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            Updated just now
                                        </p>
                                    </div>
                                </div>

                                <!-- Card Content -->
                                <div class="p-5">
                                    <!-- Stats Row -->
                                    <div class="mb-6 grid grid-cols-3 gap-4">
                                        <div class="text-center">
                                            <p
                                                class="font-mono text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100"
                                            >
                                                248
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Employees
                                            </p>
                                        </div>
                                        <div class="text-center">
                                            <p
                                                class="font-mono text-2xl font-bold text-emerald-600 tabular-nums dark:text-emerald-400"
                                            >
                                                96%
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Attendance
                                            </p>
                                        </div>
                                        <div class="text-center">
                                            <p
                                                class="font-mono text-2xl font-bold text-slate-900 tabular-nums dark:text-slate-100"
                                            >
                                                12
                                            </p>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Pending
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Activity List -->
                                    <div class="space-y-3">
                                        <div
                                            class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50"
                                        >
                                            <div
                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400"
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
                                                    <path
                                                        d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"
                                                    />
                                                    <path d="m9 12 2 2 4-4" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <p
                                                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    Payroll processed
                                                </p>
                                                <p
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    January 2025
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50"
                                        >
                                            <div
                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400"
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
                                                    <path
                                                        d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"
                                                    />
                                                    <circle
                                                        cx="9"
                                                        cy="7"
                                                        r="4"
                                                    />
                                                    <line
                                                        x1="19"
                                                        x2="19"
                                                        y1="8"
                                                        y2="14"
                                                    />
                                                    <line
                                                        x1="22"
                                                        x2="16"
                                                        y1="11"
                                                        y2="11"
                                                    />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <p
                                                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    3 new hires onboarding
                                                </p>
                                                <p
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    This week
                                                </p>
                                            </div>
                                        </div>
                                        <div
                                            class="flex items-center gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-700/50"
                                        >
                                            <div
                                                class="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/50 dark:text-amber-400"
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
                                                    <rect
                                                        x="3"
                                                        y="4"
                                                        width="18"
                                                        height="18"
                                                        rx="2"
                                                        ry="2"
                                                    />
                                                    <line
                                                        x1="16"
                                                        x2="16"
                                                        y1="2"
                                                        y2="6"
                                                    />
                                                    <line
                                                        x1="8"
                                                        x2="8"
                                                        y1="2"
                                                        y2="6"
                                                    />
                                                    <line
                                                        x1="3"
                                                        x2="21"
                                                        y1="10"
                                                        y2="10"
                                                    />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <p
                                                    class="text-sm font-medium text-slate-900 dark:text-slate-100"
                                                >
                                                    5 leave requests
                                                </p>
                                                <p
                                                    class="text-xs text-slate-500 dark:text-slate-400"
                                                >
                                                    Awaiting approval
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Floating notification card -->
                            <div
                                class="welcome-float absolute top-16 -left-4 z-10 w-56 rounded-xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/30"
                                :class="
                                    mounted
                                        ? 'translate-x-0 opacity-100'
                                        : '-translate-x-4 opacity-0'
                                "
                            >
                                <div class="mb-2 flex items-center gap-2">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500 text-white"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path d="m5 12 5 5L20 7" />
                                        </svg>
                                    </div>
                                    <span
                                        class="text-xs font-medium text-slate-900 dark:text-slate-100"
                                        >SSS Contribution</span
                                    >
                                </div>
                                <p
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Auto-calculated for all 248 employees
                                </p>
                            </div>

                            <!-- Floating chart card -->
                            <div
                                class="welcome-float-reverse absolute -right-4 bottom-20 z-10 w-48 rounded-xl border border-slate-200 bg-white p-4 shadow-xl dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/30"
                                :class="
                                    mounted
                                        ? 'translate-x-0 opacity-100'
                                        : 'translate-x-4 opacity-0'
                                "
                            >
                                <p
                                    class="mb-2 text-xs font-medium text-slate-900 dark:text-slate-100"
                                >
                                    Attendance Rate
                                </p>
                                <div class="flex items-end gap-1">
                                    <div
                                        class="h-6 w-3 rounded-sm bg-slate-200 dark:bg-slate-600"
                                    />
                                    <div
                                        class="h-10 w-3 rounded-sm bg-slate-200 dark:bg-slate-600"
                                    />
                                    <div
                                        class="h-8 w-3 rounded-sm bg-slate-200 dark:bg-slate-600"
                                    />
                                    <div
                                        class="h-12 w-3 rounded-sm bg-slate-200 dark:bg-slate-600"
                                    />
                                    <div
                                        class="h-14 w-3 rounded-sm bg-emerald-500"
                                    />
                                </div>
                                <p
                                    class="mt-2 font-mono text-lg font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    +8.2%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section
                class="border-t border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-800/50"
            >
                <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                    <div
                        class="mb-16 text-center transition-all delay-500 duration-700"
                        :class="
                            mounted
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0'
                        "
                    >
                        <h2
                            class="mb-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl dark:text-slate-100"
                        >
                            Everything you need to manage your workforce
                        </h2>
                        <p
                            class="mx-auto max-w-2xl text-lg text-slate-600 dark:text-slate-400"
                        >
                            Built for Philippine businesses, designed for
                            simplicity. All the tools to run HR operations
                            smoothly.
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            v-for="(feature, index) in features"
                            :key="feature.title"
                            class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 p-6 transition-all duration-500 hover:-translate-y-1 hover:border-slate-300 hover:bg-white hover:shadow-lg dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600 dark:hover:bg-slate-700"
                            :class="
                                mounted
                                    ? 'translate-y-0 opacity-100'
                                    : 'translate-y-8 opacity-0'
                            "
                            :style="{
                                transitionDelay: `${600 + index * 100}ms`,
                            }"
                        >
                            <!-- Icon -->
                            <div
                                class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-slate-200 text-slate-700 transition-colors group-hover:bg-blue-600 group-hover:text-white dark:bg-slate-700 dark:text-slate-300"
                            >
                                <!-- Users icon -->
                                <svg
                                    v-if="feature.icon === 'users'"
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
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
                                <!-- Calendar icon -->
                                <svg
                                    v-else-if="feature.icon === 'calendar'"
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <rect
                                        x="3"
                                        y="4"
                                        width="18"
                                        height="18"
                                        rx="2"
                                        ry="2"
                                    />
                                    <line x1="16" x2="16" y1="2" y2="6" />
                                    <line x1="8" x2="8" y1="2" y2="6" />
                                    <line x1="3" x2="21" y1="10" y2="10" />
                                    <path d="M8 14h.01" />
                                    <path d="M12 14h.01" />
                                    <path d="M16 14h.01" />
                                    <path d="M8 18h.01" />
                                    <path d="M12 18h.01" />
                                </svg>
                                <!-- Peso icon -->
                                <svg
                                    v-else-if="feature.icon === 'peso'"
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M6 3v18" />
                                    <path
                                        d="M6 7h6a4 4 0 0 1 4 4v0a4 4 0 0 1-4 4H6"
                                    />
                                    <path d="M4 9h12" />
                                    <path d="M4 13h12" />
                                </svg>
                                <!-- Chart icon -->
                                <svg
                                    v-else-if="feature.icon === 'chart'"
                                    class="h-6 w-6"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M3 3v18h18" />
                                    <path d="m19 9-5 5-4-4-3 3" />
                                </svg>
                            </div>

                            <h3
                                class="mb-2 text-base font-semibold text-slate-900 dark:text-slate-100"
                            >
                                {{ feature.title }}
                            </h3>
                            <p
                                class="text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                            >
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="border-t border-slate-200 dark:border-slate-800">
                <div class="mx-auto max-w-6xl px-6 py-20 lg:py-28">
                    <div
                        class="relative overflow-hidden rounded-3xl bg-slate-900 p-10 text-center lg:p-16 dark:bg-slate-800"
                        :class="
                            mounted
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-8 opacity-0'
                        "
                        style="transition-delay: 800ms"
                    >
                        <!-- Background pattern -->
                        <div
                            class="pointer-events-none absolute inset-0 overflow-hidden opacity-10"
                        >
                            <svg
                                class="absolute -top-20 -right-20 h-96 w-96"
                                viewBox="0 0 200 200"
                            >
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="80"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="0.5"
                                    class="text-white"
                                />
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="60"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="0.5"
                                    class="text-white"
                                />
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="40"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="0.5"
                                    class="text-white"
                                />
                            </svg>
                            <svg
                                class="absolute -bottom-20 -left-20 h-96 w-96"
                                viewBox="0 0 200 200"
                            >
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="80"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="0.5"
                                    class="text-white"
                                />
                                <circle
                                    cx="100"
                                    cy="100"
                                    r="60"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="0.5"
                                    class="text-white"
                                />
                            </svg>
                        </div>

                        <div class="relative">
                            <h2
                                class="mb-4 text-3xl font-bold text-white sm:text-4xl"
                            >
                                Ready to simplify your HR?
                            </h2>
                            <p
                                class="mx-auto mb-8 max-w-xl text-lg text-slate-300"
                            >
                                Join companies across the Philippines using
                                KasamaHR to manage their most valuable
                                asset—their people.
                            </p>
                            <div
                                class="flex flex-wrap items-center justify-center gap-4"
                            >
                                <Link
                                    v-if="$page.props.auth.user"
                                    :href="tenantSelect()"
                                    class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-white px-6 text-sm font-semibold text-slate-900 transition-all hover:-translate-y-0.5 hover:bg-slate-100"
                                >
                                    Go to Dashboard
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
                                </Link>
                                <template v-else>
                                    <Link
                                        v-if="canRegister"
                                        :href="register()"
                                        class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-blue-700"
                                    >
                                        Create your account
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
                                    </Link>
                                    <Link
                                        :href="login()"
                                        class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-600 px-6 text-sm font-semibold text-white transition-all hover:-translate-y-0.5 hover:bg-slate-800"
                                    >
                                        Log in instead
                                    </Link>
                                </template>
                            </div>
                        </div>
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
            style="transition-delay: 900ms"
        >
            <div
                class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-6 py-6 sm:flex-row"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    &copy; {{ currentYear }} KasamaHR. Built for Philippine
                    businesses.
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
                    <a
                        href="#"
                        class="transition-colors hover:text-slate-900 dark:hover:text-slate-100"
                        >Contact</a
                    >
                </div>
            </div>
        </footer>
    </div>

    <style scoped>
        .welcome-page {
            font-family:
                'DM Sans',
                system-ui,
                -apple-system,
                sans-serif;
        }
        .welcome-page .font-mono {
            font-family:
                'JetBrains Mono', ui-monospace, SFMono-Regular, monospace;
        }
        .welcome-float {
            animation: welcome-float 6s ease-in-out infinite;
        }
        .welcome-float-reverse {
            animation: welcome-float 8s ease-in-out infinite reverse;
        }
        @keyframes welcome-float {
            0%,
            100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }
    </style>
</template>
