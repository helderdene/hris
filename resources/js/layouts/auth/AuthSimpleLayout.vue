<script setup lang="ts">
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    {
        size: 'sm',
    },
);

const containerClass = computed(() => {
    const sizes = {
        sm: 'max-w-sm',
        md: 'max-w-md',
        lg: 'max-w-2xl',
    };
    return sizes[props.size];
});

const mounted = ref(false);
onMounted(() => {
    setTimeout(() => {
        mounted.value = true;
    }, 50);
});
</script>

<template>
    <div
        class="auth-layout relative flex min-h-svh flex-col items-center justify-center bg-slate-50 p-6 md:p-10 dark:bg-slate-900"
    >
        <!-- Ambient Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <!-- Soft gradient orbs -->
            <div
                class="absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-blue-500 opacity-[0.03] blur-3xl transition-all duration-1000 dark:opacity-[0.05]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />
            <div
                class="absolute -right-32 -bottom-32 h-[400px] w-[400px] rounded-full bg-emerald-500 opacity-[0.02] blur-3xl transition-all delay-200 duration-1000 dark:opacity-[0.04]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />

            <!-- Subtle grid pattern -->
            <svg
                class="absolute inset-0 h-full w-full opacity-[0.015] dark:opacity-[0.03]"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <pattern
                        id="auth-grid"
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
                    fill="url(#auth-grid)"
                    class="text-slate-900 dark:text-slate-100"
                />
            </svg>
        </div>

        <!-- Content -->
        <div
            class="relative z-10 w-full transition-all duration-500"
            :class="[
                containerClass,
                mounted
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-4 opacity-0',
            ]"
        >
            <div class="flex flex-col gap-8">
                <!-- Logo & Header -->
                <div class="flex flex-col items-center gap-6">
                    <Link
                        :href="home()"
                        class="flex items-center gap-3 transition-opacity hover:opacity-80"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-500/20"
                        >
                            <svg
                                class="h-6 w-6 text-white"
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
                            class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"
                            >KasamaHR</span
                        >
                    </Link>

                    <div class="space-y-2 text-center">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"
                        >
                            {{ title }}
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ description }}
                        </p>
                    </div>
                </div>

                <!-- Form Card -->
                <div
                    class="rounded-2xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/20"
                >
                    <slot />
                </div>

                <!-- Footer -->
                <p
                    class="text-center text-xs text-slate-400 dark:text-slate-500"
                >
                    Secure login powered by KasamaHR
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.auth-layout {
    font-family:
        'DM Sans',
        system-ui,
        -apple-system,
        sans-serif;
}
</style>
