<script setup lang="ts">
/**
 * ParentDepartmentSelect - Hierarchical dropdown for selecting parent department.
 */
import { computed } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
    parent_id: number | null;
}

const props = defineProps<{
    id?: string;
    modelValue: number | null;
    departments: Department[];
    allDepartments: Department[];
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

/**
 * Build hierarchical structure for indented display
 */
interface DepartmentOption {
    id: number;
    name: string;
    code: string;
    depth: number;
}

const departmentOptions = computed((): DepartmentOption[] => {
    const options: DepartmentOption[] = [];
    const departmentMap = new Map(props.allDepartments.map((d) => [d.id, d]));

    // Build tree and flatten with depth
    function addDepartment(dept: Department, depth: number) {
        if (props.departments.some((d) => d.id === dept.id)) {
            options.push({
                id: dept.id,
                name: dept.name,
                code: dept.code,
                depth,
            });
        }

        // Find and add children
        const children = props.allDepartments.filter(
            (d) => d.parent_id === dept.id,
        );
        children.sort((a, b) => a.name.localeCompare(b.name));
        children.forEach((child) => addDepartment(child, depth + 1));
    }

    // Start with root departments
    const roots = props.allDepartments.filter((d) => d.parent_id === null);
    roots.sort((a, b) => a.name.localeCompare(b.name));
    roots.forEach((root) => addDepartment(root, 0));

    return options;
});

function handleChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    const value = target.value;
    emit('update:modelValue', value === '' ? null : parseInt(value, 10));
}
</script>

<template>
    <select
        :id="id"
        :value="modelValue ?? ''"
        @change="handleChange"
        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
    >
        <option value="">None (Root Department)</option>
        <option
            v-for="dept in departmentOptions"
            :key="dept.id"
            :value="dept.id"
        >
            {{ '\u00A0'.repeat(dept.depth * 4) }}{{ dept.code }} -
            {{ dept.name }}
        </option>
    </select>
</template>
