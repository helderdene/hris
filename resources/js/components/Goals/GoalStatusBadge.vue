<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { computed } from 'vue';

const props = defineProps<{
    status: string;
    label?: string;
    color?: string;
}>();

const statusConfig = computed(() => {
    const configs: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; class: string }> = {
        draft: { variant: 'secondary', class: 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' },
        pending_approval: { variant: 'outline', class: 'border-amber-500 text-amber-600 dark:text-amber-400' },
        active: { variant: 'default', class: 'bg-blue-500 text-white' },
        completed: { variant: 'default', class: 'bg-green-500 text-white' },
        cancelled: { variant: 'destructive', class: 'bg-red-500 text-white' },
        // Approval statuses
        not_required: { variant: 'secondary', class: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' },
        pending: { variant: 'outline', class: 'border-amber-500 text-amber-600 dark:text-amber-400' },
        approved: { variant: 'default', class: 'bg-green-500 text-white' },
        rejected: { variant: 'destructive', class: 'bg-red-500 text-white' },
    };

    return configs[props.status] || { variant: 'secondary' as const, class: '' };
});

const displayLabel = computed(() => {
    if (props.label) return props.label;
    return props.status.replace(/_/g, ' ').replace(/\b\w/g, (l) => l.toUpperCase());
});
</script>

<template>
    <Badge :variant="statusConfig.variant" :class="[statusConfig.class, props.color]">
        {{ displayLabel }}
    </Badge>
</template>
