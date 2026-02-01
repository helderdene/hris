<script setup lang="ts">
import { computed } from 'vue';

type SyncStatus = 'pending' | 'syncing' | 'synced' | 'failed';

interface Props {
    status: SyncStatus;
}

const props = defineProps<Props>();

const badgeConfig = computed(() => {
    switch (props.status) {
        case 'synced':
            return {
                label: 'Synced',
                classes:
                    'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/30 dark:text-green-300 dark:border-green-800',
                showPulse: false,
            };
        case 'syncing':
            return {
                label: 'Syncing',
                classes:
                    'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
                showPulse: true,
            };
        case 'pending':
            return {
                label: 'Pending',
                classes:
                    'bg-yellow-50 text-yellow-700 border-yellow-300 dark:bg-yellow-900/20 dark:text-yellow-300 dark:border-yellow-700',
                showPulse: false,
            };
        case 'failed':
            return {
                label: 'Failed',
                classes:
                    'bg-red-50 text-red-700 border-red-300 dark:bg-red-900/20 dark:text-red-300 dark:border-red-700',
                showPulse: false,
            };
        default:
            return {
                label: props.status || 'Unknown',
                classes:
                    'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:border-slate-700',
                showPulse: false,
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="badgeConfig.classes"
    >
        <span
            v-if="badgeConfig.showPulse"
            class="relative flex h-2 w-2"
        >
            <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-400 opacity-75"
            ></span>
            <span
                class="relative inline-flex h-2 w-2 rounded-full bg-blue-500"
            ></span>
        </span>
        {{ badgeConfig.label }}
    </span>
</template>
