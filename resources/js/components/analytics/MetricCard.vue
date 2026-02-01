<script setup lang="ts">
/**
 * MetricCard Component
 *
 * A reusable card for displaying KPI metrics with optional highlighting.
 */
interface Props {
    title: string;
    value: string | number;
    subtitle: string;
    backgroundColor?: string;
    isHighlighted?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    backgroundColor: undefined,
    isHighlighted: false,
});
</script>

<template>
    <div
        class="rounded-xl border p-5 transition-shadow hover:shadow-md"
        :class="[
            isHighlighted
                ? 'border-transparent text-white'
                : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900',
        ]"
        :style="isHighlighted && backgroundColor ? { backgroundColor } : {}"
        data-test="metric-card"
    >
        <div class="flex items-start justify-between">
            <div>
                <p
                    class="text-sm font-medium"
                    :class="
                        isHighlighted
                            ? 'text-white/80'
                            : 'text-slate-500 dark:text-slate-400'
                    "
                >
                    {{ title }}
                </p>
                <p
                    class="mt-2 text-4xl font-bold"
                    :class="
                        isHighlighted
                            ? 'text-white'
                            : 'text-slate-900 dark:text-slate-100'
                    "
                >
                    {{ value }}
                </p>
                <p
                    class="mt-1 text-sm"
                    :class="
                        isHighlighted
                            ? 'text-white/80'
                            : 'text-slate-500 dark:text-slate-400'
                    "
                >
                    {{ subtitle }}
                </p>
            </div>
            <div
                class="rounded-lg p-2"
                :class="
                    isHighlighted
                        ? 'bg-white/20'
                        : 'bg-slate-100 dark:bg-slate-800'
                "
            >
                <slot name="icon">
                    <svg
                        class="h-6 w-6"
                        :class="
                            isHighlighted
                                ? 'text-white'
                                : 'text-slate-600 dark:text-slate-400'
                        "
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5"
                        />
                    </svg>
                </slot>
            </div>
        </div>
    </div>
</template>
