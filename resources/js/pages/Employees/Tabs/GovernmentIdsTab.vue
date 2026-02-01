<script setup lang="ts">
import LabelValueList from '@/Components/LabelValueList.vue';
import { computed } from 'vue';

interface Employee {
    tin: string | null;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    umid: string | null;
    passport_number: string | null;
    drivers_license: string | null;
    nbi_clearance: string | null;
    police_clearance: string | null;
    prc_license: string | null;
}

const props = defineProps<{
    employee: Employee;
}>();

const requiredIdItems = computed(() => [
    { label: 'TIN', value: props.employee.tin },
    { label: 'SSS Number', value: props.employee.sss_number },
    { label: 'PhilHealth Number', value: props.employee.philhealth_number },
    { label: 'Pag-IBIG Number', value: props.employee.pagibig_number },
]);

const optionalIdItems = computed(() => {
    const items = [];
    if (props.employee.umid) {
        items.push({ label: 'UMID', value: props.employee.umid });
    }
    if (props.employee.passport_number) {
        items.push({
            label: 'Passport Number',
            value: props.employee.passport_number,
        });
    }
    if (props.employee.drivers_license) {
        items.push({
            label: "Driver's License",
            value: props.employee.drivers_license,
        });
    }
    if (props.employee.nbi_clearance) {
        items.push({
            label: 'NBI Clearance',
            value: props.employee.nbi_clearance,
        });
    }
    if (props.employee.police_clearance) {
        items.push({
            label: 'Police Clearance',
            value: props.employee.police_clearance,
        });
    }
    if (props.employee.prc_license) {
        items.push({ label: 'PRC License', value: props.employee.prc_license });
    }
    return items;
});

const hasOptionalIds = computed(() => optionalIdItems.value.length > 0);
</script>

<template>
    <div class="space-y-8">
        <div>
            <LabelValueList :items="requiredIdItems" />
        </div>
        <div v-if="hasOptionalIds">
            <h3
                class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300"
            >
                Other IDs
            </h3>
            <LabelValueList :items="optionalIdItems" />
        </div>
    </div>
</template>
