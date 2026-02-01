<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface WorkLocation {
    id: number;
    name: string;
    code: string;
}

interface Holiday {
    id: number;
    name: string;
    date: string;
    formatted_date: string;
    holiday_type: string;
    holiday_type_label: string;
    description: string | null;
    is_national: boolean;
    year: number;
    work_location_id: number | null;
    work_location: WorkLocation | null;
    scope_label: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    holiday: Holiday | null;
    holidayTypes: EnumOption[];
    workLocations: WorkLocation[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    date: '',
    holiday_type: '',
    description: '',
    is_national: true,
    work_location_id: '',
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.holiday);

const workLocationOptions = computed<EnumOption[]>(() => {
    return props.workLocations.map((location) => ({
        value: String(location.id),
        label: `${location.name} (${location.code})`,
    }));
});

const showLocationSelect = computed(() => !form.value.is_national);

watch(
    () => props.holiday,
    (newHoliday) => {
        if (newHoliday) {
            form.value = {
                name: newHoliday.name,
                date: newHoliday.date,
                holiday_type: newHoliday.holiday_type,
                description: newHoliday.description || '',
                is_national: newHoliday.is_national,
                work_location_id: newHoliday.work_location_id
                    ? String(newHoliday.work_location_id)
                    : '',
            };
        } else {
            resetForm();
        }
        errors.value = {};
    },
    { immediate: true },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        errors.value = {};
    }
});

// When is_national changes to true, clear the work_location_id
watch(
    () => form.value.is_national,
    (isNational) => {
        if (isNational) {
            form.value.work_location_id = '';
        }
    },
);

function resetForm() {
    form.value = {
        name: '',
        date: '',
        holiday_type: '',
        description: '',
        is_national: true,
        work_location_id: '',
    };
    errors.value = {};
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/holidays/${props.holiday!.id}`
        : '/api/organization/holidays';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload = {
        name: form.value.name,
        date: form.value.date,
        holiday_type: form.value.holiday_type,
        description: form.value.description || null,
        is_national: form.value.is_national,
        work_location_id: form.value.is_national
            ? null
            : form.value.work_location_id
              ? Number(form.value.work_location_id)
              : null,
    };

    try {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (response.ok) {
            emit('success');
        } else if (response.status === 422 && data.errors) {
            errors.value = Object.fromEntries(
                Object.entries(data.errors).map(([key, value]) => [
                    key,
                    (value as string[])[0],
                ]),
            );
        } else {
            errors.value = { general: data.message || 'An error occurred' };
        }
    } catch (error) {
        errors.value = {
            general: 'An error occurred while saving the holiday',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Holiday' : 'Add Holiday' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the holiday details below.'
                            : 'Fill in the details to create a new holiday.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <!-- General Error -->
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <!-- Name -->
                <div class="space-y-2">
                    <Label for="name">Holiday Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Christmas Day"
                        :class="{ 'border-red-500': errors.name }"
                        data-test="holiday-name-input"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Date & Type -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="date">Date *</Label>
                        <Input
                            id="date"
                            v-model="form.date"
                            type="date"
                            :class="{ 'border-red-500': errors.date }"
                            data-test="holiday-date-input"
                        />
                        <p v-if="errors.date" class="text-sm text-red-500">
                            {{ errors.date }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="holiday_type">Holiday Type *</Label>
                        <EnumSelect
                            id="holiday_type"
                            v-model="form.holiday_type"
                            :options="holidayTypes"
                            placeholder="Select type"
                            data-test="holiday-type-select"
                        />
                        <p
                            v-if="errors.holiday_type"
                            class="text-sm text-red-500"
                        >
                            {{ errors.holiday_type }}
                        </p>
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-2">
                    <Label for="description">Description</Label>
                    <Textarea
                        id="description"
                        v-model="form.description"
                        placeholder="Optional description of the holiday"
                        rows="2"
                        :class="{ 'border-red-500': errors.description }"
                        data-test="holiday-description-input"
                    />
                    <p v-if="errors.description" class="text-sm text-red-500">
                        {{ errors.description }}
                    </p>
                </div>

                <!-- National Holiday Checkbox -->
                <div class="space-y-4">
                    <div class="flex items-center gap-3">
                        <input
                            id="is_national"
                            type="checkbox"
                            v-model="form.is_national"
                            class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                            data-test="is-national-checkbox"
                        />
                        <div>
                            <Label for="is_national" class="cursor-pointer"
                                >National Holiday</Label
                            >
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                National holidays apply to all employees
                                regardless of location.
                            </p>
                        </div>
                    </div>
                    <p v-if="errors.is_national" class="text-sm text-red-500">
                        {{ errors.is_national }}
                    </p>
                </div>

                <!-- Work Location (shown when not national) -->
                <div v-if="showLocationSelect" class="space-y-2">
                    <Label for="work_location_id">Work Location</Label>
                    <EnumSelect
                        id="work_location_id"
                        v-model="form.work_location_id"
                        :options="workLocationOptions"
                        placeholder="Select location (optional)"
                        data-test="work-location-select"
                    />
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Select the work location this holiday applies to.
                    </p>
                    <p
                        v-if="errors.work_location_id"
                        class="text-sm text-red-500"
                    >
                        {{ errors.work_location_id }}
                    </p>
                </div>

                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                        :disabled="isSubmitting"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isSubmitting"
                        data-test="submit-holiday-button"
                    >
                        <svg
                            v-if="isSubmitting"
                            class="mr-2 h-4 w-4 animate-spin"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>
                        {{ isEditing ? 'Update Holiday' : 'Create Holiday' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
