<script setup lang="ts">
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
    title?: string;
    description?: string;
}>();

const mounted = ref(false);
onMounted(() => {
    setTimeout(() => {
        mounted.value = true;
    }, 50);
});
</script>

<template>
    <div
        class="auth-split-layout relative grid min-h-dvh flex-col items-center justify-center lg:max-w-none lg:grid-cols-2"
    >
        <!-- Left Panel - Branding -->
        <div
            class="relative hidden h-full flex-col overflow-hidden bg-slate-900 p-10 text-white lg:flex dark:bg-slate-950"
        >
            <!-- Background gradient -->
            <div class="pointer-events-none absolute inset-0">
                <div
                    class="absolute -top-32 -left-32 h-[600px] w-[600px] rounded-full bg-blue-600 opacity-20 blur-3xl"
                />
                <div
                    class="absolute -right-32 -bottom-32 h-[500px] w-[500px] rounded-full bg-emerald-600 opacity-10 blur-3xl"
                />

                <!-- Grid pattern -->
                <svg
                    class="absolute inset-0 h-full w-full opacity-[0.03]"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <defs>
                        <pattern
                            id="split-grid"
                            width="40"
                            height="40"
                            patternUnits="userSpaceOnUse"
                        >
                            <path
                                d="M 40 0 L 0 0 0 40"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="0.5"
                            />
                        </pattern>
                    </defs>
                    <rect
                        width="100%"
                        height="100%"
                        fill="url(#split-grid)"
                        class="text-white"
                    />
                </svg>
            </div>

            <!-- Logo -->
            <Link
                :href="home()"
                class="relative z-20 flex items-center gap-3 transition-opacity hover:opacity-80"
                :class="
                    mounted
                        ? 'translate-y-0 opacity-100'
                        : '-translate-y-4 opacity-0'
                "
                style="transition: all 0.5s ease"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/10 backdrop-blur-sm"
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
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <span class="text-lg font-semibold">{{
                    name || 'KasamaHR'
                }}</span>
            </Link>

            <!-- Quote / Testimonial -->
            <div
                v-if="quote"
                class="relative z-20 mt-auto"
                :class="
                    mounted
                        ? 'translate-y-0 opacity-100'
                        : 'translate-y-4 opacity-0'
                "
                style="transition: all 0.7s ease 0.2s"
            >
                <blockquote class="space-y-4">
                    <p
                        class="text-xl leading-relaxed font-medium text-white/90"
                    >
                        &ldquo;{{ quote.message }}&rdquo;
                    </p>
                    <footer class="text-sm text-slate-400">
                        {{ quote.author }}
                    </footer>
                </blockquote>
            </div>

            <!-- Default content if no quote -->
            <div
                v-else
                class="relative z-20 mt-auto"
                :class="
                    mounted
                        ? 'translate-y-0 opacity-100'
                        : 'translate-y-4 opacity-0'
                "
                style="transition: all 0.7s ease 0.2s"
            >
                <div class="space-y-4">
                    <h2 class="text-2xl font-semibold text-white">
                        Simplify your HR operations
                    </h2>
                    <p class="leading-relaxed text-slate-400">
                        Join companies across the Philippines using KasamaHR to
                        manage their workforce with full compliance to local
                        labor laws.
                    </p>
                    <div class="flex items-center gap-4 pt-2">
                        <div
                            class="flex items-center gap-2 text-sm text-slate-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-400"
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
                            <span>Secure</span>
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-slate-400"
                        >
                            <svg
                                class="h-4 w-4 text-emerald-400"
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
                            <span>100% PH Compliant</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Form -->
        <div
            class="relative flex h-full flex-col items-center justify-center bg-slate-50 px-6 py-10 lg:px-8 dark:bg-slate-900"
        >
            <!-- Background -->
            <div
                class="pointer-events-none absolute inset-0 overflow-hidden lg:hidden"
            >
                <div
                    class="absolute -top-32 -left-32 h-[400px] w-[400px] rounded-full bg-blue-500 opacity-[0.03] blur-3xl dark:opacity-[0.05]"
                />
                <div
                    class="absolute -right-32 -bottom-32 h-[300px] w-[300px] rounded-full bg-emerald-500 opacity-[0.02] blur-3xl dark:opacity-[0.04]"
                />
            </div>

            <!-- Mobile Logo -->
            <Link
                :href="home()"
                class="relative z-10 mb-8 flex items-center gap-3 lg:hidden"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-500/20"
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
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <span
                    class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                    >KasamaHR</span
                >
            </Link>

            <!-- Form Container -->
            <div
                class="relative z-10 mx-auto flex w-full max-w-sm flex-col justify-center space-y-6"
                :class="
                    mounted
                        ? 'translate-y-0 opacity-100'
                        : 'translate-y-4 opacity-0'
                "
                style="transition: all 0.5s ease 0.1s"
            >
                <div class="flex flex-col space-y-2 text-center">
                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>
                    <p
                        class="text-sm text-slate-500 dark:text-slate-400"
                        v-if="description"
                    >
                        {{ description }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-900/5 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none dark:border-slate-700 dark:bg-slate-800 lg:dark:bg-transparent"
                >
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-split-layout {
    font-family:
        'DM Sans',
        system-ui,
        -apple-system,
        sans-serif;
}
</style>
