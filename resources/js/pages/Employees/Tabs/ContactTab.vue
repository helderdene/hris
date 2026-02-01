<script setup lang="ts">
import LabelValueList from '@/components/LabelValueList.vue';
import { computed } from 'vue';

interface Address {
    street?: string;
    barangay?: string;
    city?: string;
    province?: string;
    postal_code?: string;
}

interface EmergencyContact {
    name?: string;
    relationship?: string;
    phone?: string;
}

interface Employee {
    address: Address | null;
    emergency_contact: EmergencyContact | null;
}

const props = defineProps<{
    employee: Employee;
}>();

const emergencyContactItems = computed(() => [
    { label: 'Name', value: props.employee.emergency_contact?.name },
    {
        label: 'Relationship',
        value: props.employee.emergency_contact?.relationship,
    },
    { label: 'Phone Number', value: props.employee.emergency_contact?.phone },
]);

const addressItems = computed(() => [
    { label: 'Street', value: props.employee.address?.street },
    { label: 'Barangay', value: props.employee.address?.barangay },
    { label: 'City/Municipality', value: props.employee.address?.city },
    { label: 'Province', value: props.employee.address?.province },
    { label: 'Postal Code', value: props.employee.address?.postal_code },
]);
</script>

<template>
    <div class="space-y-8">
        <div>
            <h3
                class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"
            >
                Emergency Contact
            </h3>
            <LabelValueList :items="emergencyContactItems" />
        </div>
        <div>
            <h3
                class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"
            >
                Address
            </h3>
            <LabelValueList :items="addressItems" />
        </div>
    </div>
</template>
