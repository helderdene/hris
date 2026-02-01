<script setup lang="ts">
import { useInitials } from '@/composables/useInitials';
import { computed } from 'vue';

export interface TenantProps {
    id: number;
    name: string;
    slug: string;
    logo_path: string | null;
    primary_color: string | null;
    role?: string;
    role_label?: string;
    lastAccessed?: string | null;
}

interface Props {
    tenant: TenantProps;
    isLoading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    isLoading: false,
    disabled: false,
});

const emit = defineEmits<{
    select: [tenant: TenantProps];
}>();

const { getInitials } = useInitials();

const accentColor = computed(() => props.tenant.primary_color || '#6366f1');

/**
 * Role badge styling configuration.
 * Each role has a distinct color scheme for visual differentiation.
 */
const roleBadgeClasses = computed(() => {
    const role = props.tenant.role;

    switch (role) {
        case 'admin':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
        case 'hr_manager':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'hr_staff':
            return 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300';
        case 'hr_consultant':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300';
        case 'supervisor':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'employee':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
});

/**
 * Role icon based on role type.
 * Returns the appropriate icon path for each role.
 */
const roleIcon = computed(() => {
    const role = props.tenant.role;

    switch (role) {
        case 'admin':
            // Shield icon for admin
            return 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z';
        case 'hr_manager':
            // User group icon for HR Manager
            return 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z';
        case 'hr_staff':
            // Document icon for HR Staff
            return 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z';
        case 'hr_consultant':
            // Briefcase icon for HR Consultant
            return 'M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z';
        case 'supervisor':
            // Eye icon for Supervisor
            return 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z';
        case 'employee':
            // User icon for Employee
            return 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z';
        default:
            return 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z';
    }
});

/**
 * Display label for the role.
 * Uses the role_label from the backend if available, otherwise falls back to legacy behavior.
 */
const displayRoleLabel = computed(() => {
    // Prefer the role_label from the backend (human-readable)
    if (props.tenant.role_label) {
        return props.tenant.role_label;
    }

    // Fallback for legacy data
    if (!props.tenant.role) {
        return null;
    }

    return props.tenant.role === 'admin' ? 'Admin' : 'Member';
});

function handleClick() {
    if (!props.isLoading && !props.disabled) {
        emit('select', props.tenant);
    }
}
</script>

<template>
    <button
        type="button"
        :disabled="disabled || isLoading"
        class="group relative flex w-full items-center gap-4 rounded-xl border border-slate-200 bg-white p-4 text-left transition-all hover:border-slate-300 hover:bg-slate-50 hover:shadow-sm focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-600 dark:hover:bg-slate-800"
        :style="isLoading ? { borderColor: accentColor } : undefined"
        @click="handleClick"
    >
        <!-- Logo or Initials Avatar -->
        <div
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg text-lg font-semibold text-white shadow-sm transition-transform group-hover:scale-105"
            :style="{ backgroundColor: accentColor }"
        >
            <img
                v-if="tenant.logo_path"
                :src="tenant.logo_path"
                :alt="tenant.name"
                class="h-full w-full rounded-lg object-cover"
            />
            <span v-else class="select-none">{{
                getInitials(tenant.name)
            }}</span>
        </div>

        <!-- Tenant Info -->
        <div class="min-w-0 flex-1">
            <p class="truncate font-medium text-slate-900 dark:text-slate-100">
                {{ tenant.name }}
            </p>
            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <!-- Role Badge with Icon -->
                <span
                    v-if="displayRoleLabel"
                    class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium"
                    :class="roleBadgeClasses"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            :d="roleIcon"
                        />
                    </svg>
                    {{ displayRoleLabel }}
                </span>
                <span
                    v-if="tenant.lastAccessed"
                    class="text-xs text-slate-400 dark:text-slate-500"
                >
                    Last accessed {{ tenant.lastAccessed }}
                </span>
            </div>
        </div>

        <!-- Primary Color Accent Indicator -->
        <div
            class="absolute right-4 bottom-0 left-4 h-0.5 rounded-full opacity-0 transition-opacity group-hover:opacity-100"
            :style="{ backgroundColor: accentColor }"
        />

        <!-- Loading Overlay -->
        <div
            v-if="isLoading"
            class="absolute inset-0 flex items-center justify-center rounded-xl bg-white/80 dark:bg-slate-900/80"
        >
            <svg
                class="h-6 w-6 animate-spin"
                :style="{ color: accentColor }"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
        </div>

        <!-- Chevron Icon -->
        <svg
            v-if="!isLoading"
            class="h-5 w-5 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="2"
            stroke="currentColor"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M8.25 4.5l7.5 7.5-7.5 7.5"
            />
        </svg>
    </button>
</template>
