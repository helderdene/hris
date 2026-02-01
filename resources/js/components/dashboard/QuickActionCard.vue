<script setup lang="ts">
/**
 * QuickActionCard Component
 *
 * A clickable action card displaying an icon, title, and description.
 * Used in the Quick Actions section of the Employee Dashboard.
 * Supports disabled state for placeholder functionality.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type ColorVariant = 'blue' | 'green' | 'amber' | 'gray';

interface Props {
    title: string;
    description: string;
    href?: string;
    disabled?: boolean;
    colorVariant?: ColorVariant;
}

const props = withDefaults(defineProps<Props>(), {
    href: '#',
    disabled: false,
    colorVariant: 'blue',
});

/**
 * Icon container background and text color classes based on color variant.
 */
const iconContainerClasses = computed(() => {
    const colorMap: Record<ColorVariant, string> = {
        blue: 'bg-blue-100 dark:bg-blue-900/30',
        green: 'bg-emerald-100 dark:bg-emerald-900/30',
        amber: 'bg-amber-100 dark:bg-amber-900/30',
        gray: 'bg-slate-100 dark:bg-slate-700',
    };

    return colorMap[props.colorVariant];
});

const iconColorClasses = computed(() => {
    const colorMap: Record<ColorVariant, string> = {
        blue: 'text-blue-600 dark:text-blue-400',
        green: 'text-emerald-600 dark:text-emerald-400',
        amber: 'text-amber-600 dark:text-amber-400',
        gray: 'text-slate-400 dark:text-slate-500',
    };

    return colorMap[props.colorVariant];
});

/**
 * Base card classes for styling.
 */
const cardClasses = computed(() => {
    const baseClasses =
        'flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800';

    if (props.disabled) {
        return `${baseClasses} cursor-not-allowed opacity-50`;
    }

    return `${baseClasses} transition-shadow hover:shadow-md`;
});
</script>

<template>
    <component
        :is="disabled ? 'div' : Link"
        :href="disabled ? undefined : href"
        :class="cardClasses"
        :data-test="`quick-action-${title.toLowerCase().replace(/\s+/g, '-')}`"
    >
        <div :class="['rounded-lg p-3', iconContainerClasses]">
            <slot name="icon">
                <!-- Default placeholder icon -->
                <svg
                    :class="['h-6 w-6', iconColorClasses]"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="2"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"
                    />
                </svg>
            </slot>
        </div>
        <div>
            <p class="font-medium text-slate-900 dark:text-slate-100">
                {{ title }}
            </p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ description }}
            </p>
        </div>
    </component>
</template>
