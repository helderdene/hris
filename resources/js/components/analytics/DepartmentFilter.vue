<script setup lang="ts">
/**
 * DepartmentFilter Component
 *
 * Multi-select filter for departments.
 */
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Building2, ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

interface Department {
    id: number;
    name: string;
}

interface Props {
    departments: Department[];
}

const props = defineProps<Props>();

const modelValue = defineModel<number[]>({ default: () => [] });

const displayLabel = computed(() => {
    if (modelValue.value.length === 0) {
        return 'All Departments';
    }
    if (modelValue.value.length === 1) {
        const dept = props.departments.find((d) => d.id === modelValue.value[0]);
        return dept?.name || '1 Department';
    }
    return `${modelValue.value.length} Departments`;
});

function toggleDepartment(id: number) {
    const index = modelValue.value.indexOf(id);
    if (index === -1) {
        modelValue.value = [...modelValue.value, id];
    } else {
        modelValue.value = modelValue.value.filter((d) => d !== id);
    }
}

function selectAll() {
    modelValue.value = [];
}

function clearSelection() {
    modelValue.value = [];
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" class="gap-2">
                <Building2 class="h-4 w-4" />
                <span class="hidden sm:inline">{{ displayLabel }}</span>
                <ChevronDown class="h-4 w-4" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-64 max-h-80 overflow-y-auto">
            <div class="px-2 py-1.5">
                <Button
                    variant="ghost"
                    size="sm"
                    class="w-full justify-start"
                    @click="selectAll"
                >
                    All Departments
                </Button>
            </div>

            <DropdownMenuSeparator />

            <div class="p-2">
                <div
                    v-for="dept in departments"
                    :key="dept.id"
                    class="flex items-center gap-2 rounded px-2 py-1.5 hover:bg-slate-100 dark:hover:bg-slate-800"
                >
                    <Checkbox
                        :id="`dept-${dept.id}`"
                        :checked="modelValue.includes(dept.id)"
                        @update:checked="toggleDepartment(dept.id)"
                    />
                    <label
                        :for="`dept-${dept.id}`"
                        class="flex-1 cursor-pointer text-sm"
                    >
                        {{ dept.name }}
                    </label>
                </div>
            </div>

            <template v-if="modelValue.length > 0">
                <DropdownMenuSeparator />
                <div class="p-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        class="w-full"
                        @click="clearSelection"
                    >
                        Clear Selection
                    </Button>
                </div>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
