<script setup lang="ts">
import { ChevronDown, ChevronRight, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
    status: 'active' | 'inactive';
    parent_id: number | null;
    department_head_id: number | null;
    department_head_name?: string | null;
    children?: Department[];
}

const props = defineProps<{
    department: Department;
    isExpanded?: boolean;
}>();

const emit = defineEmits<{
    (e: 'toggle', id: number): void;
}>();

const isHovered = ref(false);

const hasChildren = computed(() => {
    return props.department.children && props.department.children.length > 0;
});

const statusColors = computed(() => {
    if (props.department.status === 'active') {
        return {
            badge: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            border: 'border-green-200 dark:border-green-800',
        };
    }
    return {
        badge: 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-400',
        border: 'border-slate-200 dark:border-slate-700',
    };
});

function handleToggle() {
    if (hasChildren.value) {
        emit('toggle', props.department.id);
    }
}
</script>

<template>
    <div
        class="relative flex flex-col items-center"
        :data-department-id="department.id"
    >
        <!-- Department Card -->
        <div
            class="group relative flex max-w-[220px] min-w-[180px] cursor-pointer flex-col rounded-lg border bg-white p-3 shadow-sm transition-all duration-200 dark:bg-slate-800"
            :class="[
                statusColors.border,
                isHovered ? 'shadow-md ring-2 ring-blue-500/20' : '',
                hasChildren ? 'cursor-pointer' : 'cursor-default',
            ]"
            @mouseenter="isHovered = true"
            @mouseleave="isHovered = false"
            @click="handleToggle"
        >
            <!-- Header with Code Badge -->
            <div class="mb-2 flex items-start justify-between gap-2">
                <span
                    class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                >
                    {{ department.code }}
                </span>
                <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="statusColors.badge"
                >
                    {{ department.status }}
                </span>
            </div>

            <!-- Department Name -->
            <h3
                class="mb-2 line-clamp-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                :title="department.name"
            >
                {{ department.name }}
            </h3>

            <!-- Department Head -->
            <div
                v-if="department.department_head_name"
                class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"
            >
                <User class="h-3 w-3" />
                <span class="truncate">{{
                    department.department_head_name
                }}</span>
            </div>
            <div
                v-else
                class="flex items-center gap-1.5 text-xs text-slate-400 italic dark:text-slate-500"
            >
                <User class="h-3 w-3" />
                <span>No head assigned</span>
            </div>

            <!-- Expand/Collapse Indicator -->
            <div
                v-if="hasChildren"
                class="absolute -bottom-2 left-1/2 flex h-5 w-5 -translate-x-1/2 items-center justify-center rounded-full border bg-white shadow-sm transition-colors dark:bg-slate-800"
                :class="statusColors.border"
            >
                <ChevronDown
                    v-if="isExpanded"
                    class="h-3 w-3 text-slate-500 dark:text-slate-400"
                />
                <ChevronRight
                    v-else
                    class="h-3 w-3 text-slate-500 dark:text-slate-400"
                />
            </div>
        </div>
    </div>
</template>
