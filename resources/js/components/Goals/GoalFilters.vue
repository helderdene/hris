<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { computed, ref, watch } from 'vue';

interface EnumOption {
    value: string;
    label: string;
    description?: string;
    color?: string;
}

interface Filters {
    goal_type: string | null;
    status: string | null;
}

const props = defineProps<{
    goalTypes: EnumOption[];
    goalStatuses: EnumOption[];
    filters: Filters;
}>();

const emit = defineEmits<{
    change: [filters: Filters];
}>();

const localFilters = ref<Filters>({
    goal_type: props.filters.goal_type,
    status: props.filters.status,
});

watch(
    () => props.filters,
    (newFilters) => {
        localFilters.value = { ...newFilters };
    },
    { deep: true },
);

function updateFilter(key: keyof Filters, value: string | null) {
    const actualValue = value === 'all' ? null : value;
    localFilters.value[key] = actualValue;
    emit('change', { ...localFilters.value });
}

function clearFilters() {
    localFilters.value = { goal_type: null, status: null };
    emit('change', { goal_type: null, status: null });
}

const hasActiveFilters = computed(() => {
    return localFilters.value.goal_type !== null || localFilters.value.status !== null;
});
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <!-- Goal Type Filter -->
        <Select
            :model-value="localFilters.goal_type || 'all'"
            @update:model-value="(val) => updateFilter('goal_type', val)"
        >
            <SelectTrigger class="w-[160px]">
                <SelectValue placeholder="All Types" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem
                    v-for="type in goalTypes"
                    :key="type.value"
                    :value="type.value"
                >
                    {{ type.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- Status Filter -->
        <Select
            :model-value="localFilters.status || 'all'"
            @update:model-value="(val) => updateFilter('status', val)"
        >
            <SelectTrigger class="w-[180px]">
                <SelectValue placeholder="All Statuses" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All Statuses</SelectItem>
                <SelectItem
                    v-for="status in goalStatuses"
                    :key="status.value"
                    :value="status.value"
                >
                    {{ status.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <!-- Clear Filters -->
        <Button
            v-if="hasActiveFilters"
            variant="ghost"
            size="sm"
            @click="clearFilters"
        >
            <svg
                class="mr-1 h-4 w-4"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12"
                />
            </svg>
            Clear
        </Button>
    </div>
</template>
