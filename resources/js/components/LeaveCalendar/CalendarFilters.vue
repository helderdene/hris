<script setup lang="ts">
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
}

const props = defineProps<{
    departments: Department[];
    departmentId?: number | null;
    showPending: boolean;
}>();

const emit = defineEmits<{
    'update:departmentId': [value: number | null];
    'update:showPending': [value: boolean];
}>();

const selectedDepartment = computed({
    get: () => props.departmentId?.toString() ?? 'all',
    set: (value: string) => {
        emit('update:departmentId', value === 'all' ? null : parseInt(value, 10));
    },
});

function toggleShowPending(event: Event) {
    const target = event.target as HTMLInputElement;
    emit('update:showPending', target.checked);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex items-center gap-2">
            <Label for="department-filter" class="text-sm whitespace-nowrap">
                Department
            </Label>
            <Select v-model="selectedDepartment">
                <SelectTrigger id="department-filter" class="w-48">
                    <SelectValue placeholder="All Departments" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All Departments</SelectItem>
                    <SelectItem
                        v-for="dept in departments"
                        :key="dept.id"
                        :value="dept.id.toString()"
                    >
                        {{ dept.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div class="flex items-center gap-2">
            <input
                id="show-pending"
                type="checkbox"
                :checked="showPending"
                class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                @change="toggleShowPending"
            />
            <Label for="show-pending" class="text-sm cursor-pointer">
                Show pending requests
            </Label>
        </div>
    </div>
</template>
