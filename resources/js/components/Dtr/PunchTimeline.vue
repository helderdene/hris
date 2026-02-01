<script setup lang="ts">
import { Coffee, LogIn, LogOut } from 'lucide-vue-next';
import { computed } from 'vue';

interface Punch {
    id: number;
    punch_type: string;
    punch_type_label: string;
    punched_at: string;
    punched_at_full: string;
    is_valid: boolean;
}

const props = defineProps<{
    punches: Punch[];
}>();

// Sort punches by time to ensure correct order
const sortedPunches = computed(() => {
    return [...props.punches].sort((a, b) => {
        return new Date(a.punched_at_full).getTime() - new Date(b.punched_at_full).getTime();
    });
});

function getPunchStyles(punchType: string) {
    switch (punchType) {
        case 'in':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'out':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'break_out':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        case 'break_in':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400';
    }
}

function getPunchIcon(punchType: string) {
    switch (punchType) {
        case 'in':
            return LogIn;
        case 'out':
            return LogOut;
        case 'break_out':
        case 'break_in':
            return Coffee;
        default:
            return LogIn;
    }
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Punches:</span>
        <div
            v-for="(punch, index) in sortedPunches"
            :key="punch.id"
            class="flex items-center gap-1"
        >
            <div
                class="flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                :class="[
                    getPunchStyles(punch.punch_type),
                    !punch.is_valid && 'opacity-50 line-through',
                ]"
                :title="punch.punch_type_label"
            >
                <component
                    :is="getPunchIcon(punch.punch_type)"
                    class="h-3 w-3"
                />
                <span>{{ punch.punched_at }}</span>
                <span v-if="punch.punch_type === 'break_out'" class="text-[10px] opacity-75">out</span>
                <span v-if="punch.punch_type === 'break_in'" class="text-[10px] opacity-75">in</span>
            </div>
            <span
                v-if="index < sortedPunches.length - 1"
                class="text-slate-300 dark:text-slate-600"
            >
                &rarr;
            </span>
        </div>
    </div>
</template>
