<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

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
        class="auth-card-layout relative flex min-h-svh flex-col items-center justify-center bg-slate-100 p-6 md:p-10 dark:bg-slate-900"
    >
        <!-- Ambient Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <!-- Soft gradient orbs -->
            <div
                class="absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-blue-500 opacity-[0.04] blur-3xl transition-all duration-1000 dark:opacity-[0.06]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />
            <div
                class="absolute -right-32 -bottom-32 h-[400px] w-[400px] rounded-full bg-emerald-500 opacity-[0.03] blur-3xl transition-all delay-200 duration-1000 dark:opacity-[0.05]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />
            <div
                class="absolute top-1/2 left-1/2 h-[300px] w-[300px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-400 opacity-[0.02] blur-3xl transition-all delay-300 duration-1000 dark:opacity-[0.03]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />

            <!-- Subtle grid pattern -->
            <svg
                class="absolute inset-0 h-full w-full opacity-[0.02] dark:opacity-[0.04]"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <pattern
                        id="card-grid"
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
                    fill="url(#card-grid)"
                    class="text-slate-900 dark:text-slate-100"
                />
            </svg>
        </div>

        <!-- Content -->
        <div
            class="relative z-10 flex w-full max-w-md flex-col gap-6 transition-all duration-500"
            :class="
                mounted
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-4 opacity-0'
            "
        >
            <!-- Logo -->
            <Link
                :href="home()"
                class="flex items-center justify-center gap-3 transition-opacity hover:opacity-80"
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
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
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

            <!-- Card -->
            <Card
                class="rounded-2xl border-slate-200 bg-white shadow-2xl shadow-slate-900/5 dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/20"
            >
                <CardHeader class="px-8 pt-8 pb-0 text-center">
                    <CardTitle
                        class="text-2xl font-semibold text-slate-900 dark:text-slate-100"
                    >
                        {{ title }}
                    </CardTitle>
                    <CardDescription class="text-slate-500 dark:text-slate-400">
                        {{ description }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="px-8 py-8">
                    <slot />
                </CardContent>
            </Card>

            <!-- Footer -->
            <p class="text-center text-xs text-slate-400 dark:text-slate-500">
                Secure login powered by KasamaHR
            </p>
        </div>
    </div>
</template>

<style scoped>
.auth-card-layout {
    font-family:
        'DM Sans',
        system-ui,
        -apple-system,
        sans-serif;
}
</style>
