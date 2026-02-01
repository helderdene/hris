<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { computed, ref, watch } from 'vue';

interface EnumOption {
    value: string;
    label: string;
}

interface Category {
    id: number;
    name: string;
    code: string;
}

interface Filters {
    status?: string | null;
    delivery_method?: string | null;
    level?: string | null;
    category_id?: number | null;
    search?: string | null;
}

const props = defineProps<{
    filters: Filters;
    categories: Category[];
    statusOptions?: EnumOption[];
    deliveryMethodOptions: EnumOption[];
    levelOptions?: EnumOption[];
    showStatus?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:filters', filters: Filters): void;
}>();

const localFilters = ref<Filters>({ ...props.filters });

watch(
    () => props.filters,
    (newFilters) => {
        localFilters.value = { ...newFilters };
    },
    { deep: true },
);

const hasActiveFilters = computed(() => {
    return (
        localFilters.value.status ||
        localFilters.value.delivery_method ||
        localFilters.value.level ||
        localFilters.value.category_id ||
        localFilters.value.search
    );
});

function handleFilterChange() {
    emit('update:filters', { ...localFilters.value });
}

function clearFilters() {
    localFilters.value = {
        status: null,
        delivery_method: null,
        level: null,
        category_id: null,
        search: null,
    };
    handleFilterChange();
}
</script>

<template>
    <div class="space-y-4">
        <!-- Search -->
        <div class="flex gap-4">
            <div class="flex-1">
                <Input
                    v-model="localFilters.search"
                    type="search"
                    placeholder="Search courses by title or code..."
                    @input="handleFilterChange"
                />
            </div>
        </div>

        <!-- Filter Selects -->
        <div class="flex flex-wrap gap-3">
            <!-- Status (Admin only) -->
            <select
                v-if="showStatus && statusOptions"
                v-model="localFilters.status"
                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                @change="handleFilterChange"
            >
                <option :value="null">All Statuses</option>
                <option
                    v-for="option in statusOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>

            <!-- Delivery Method -->
            <select
                v-model="localFilters.delivery_method"
                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                @change="handleFilterChange"
            >
                <option :value="null">All Delivery Methods</option>
                <option
                    v-for="option in deliveryMethodOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>

            <!-- Level -->
            <select
                v-if="levelOptions"
                v-model="localFilters.level"
                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                @change="handleFilterChange"
            >
                <option :value="null">All Levels</option>
                <option
                    v-for="option in levelOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>

            <!-- Category -->
            <select
                v-if="categories.length > 0"
                v-model="localFilters.category_id"
                class="h-9 rounded-md border border-slate-200 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-900"
                @change="handleFilterChange"
            >
                <option :value="null">All Categories</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>

            <!-- Clear Filters -->
            <Button
                v-if="hasActiveFilters"
                variant="ghost"
                size="sm"
                @click="clearFilters"
            >
                Clear Filters
            </Button>
        </div>
    </div>
</template>
