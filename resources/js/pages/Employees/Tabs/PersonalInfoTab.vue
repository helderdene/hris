<script setup lang="ts">
import LabelValueList from '@/components/LabelValueList.vue';
import { computed } from 'vue';

interface Employee {
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    full_name: string;
    date_of_birth: string | null;
    age: number | null;
    gender: string | null;
    civil_status: string | null;
    nationality: string | null;
    fathers_name: string | null;
    mothers_name: string | null;
}

const props = defineProps<{
    employee: Employee;
}>();

function formatDate(dateStr: string | null): string | null {
    if (!dateStr) return null;
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function capitalizeFirst(str: string | null): string | null {
    if (!str) return null;
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

const personalItems = computed(() => [
    { label: 'Full Name', value: props.employee.full_name },
    { label: 'First Name', value: props.employee.first_name },
    { label: 'Middle Name', value: props.employee.middle_name },
    { label: 'Last Name', value: props.employee.last_name },
    { label: 'Suffix', value: props.employee.suffix },
    { label: 'Birth Date', value: formatDate(props.employee.date_of_birth) },
    {
        label: 'Age',
        value: props.employee.age ? `${props.employee.age} years old` : null,
    },
    { label: 'Gender', value: capitalizeFirst(props.employee.gender) },
    {
        label: 'Civil Status',
        value: capitalizeFirst(props.employee.civil_status),
    },
    { label: 'Nationality', value: props.employee.nationality },
]);

const parentItems = computed(() => [
    { label: "Father's Name", value: props.employee.fathers_name },
    { label: "Mother's Name", value: props.employee.mothers_name },
]);
</script>

<template>
    <div class="space-y-8">
        <div>
            <LabelValueList :items="personalItems" />
        </div>
        <div>
            <h3
                class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"
            >
                Family Background
            </h3>
            <LabelValueList :items="parentItems" />
        </div>
    </div>
</template>
