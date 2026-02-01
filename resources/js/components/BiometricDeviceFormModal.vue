<script setup lang="ts">
import EnumSelect from '@/Components/EnumSelect.vue';
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
import { computed, ref, watch } from 'vue';

interface WorkLocation {
    id: number;
    name: string;
    code: string;
}

interface BiometricDevice {
    id: number;
    name: string;
    device_identifier: string;
    work_location_id: number;
    status: string;
    status_label: string;
    last_seen_at: string | null;
    last_seen_human: string | null;
    connection_started_at: string | null;
    is_active: boolean;
    uptime_seconds: number | null;
    uptime_human: string | null;
    work_location: WorkLocation | null;
    created_at: string;
    updated_at: string;
}

interface EnumOption {
    value: string;
    label: string;
}

const props = defineProps<{
    device: BiometricDevice | null;
    workLocations: WorkLocation[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    device_identifier: '',
    work_location_id: '',
    is_active: true,
});

const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.device);

// Work location options for dropdown
const workLocationOptions = computed((): EnumOption[] => {
    return props.workLocations.map((loc) => ({
        value: String(loc.id),
        label: `${loc.name} (${loc.code})`,
    }));
});

watch(
    () => props.device,
    (newDevice) => {
        if (newDevice) {
            form.value = {
                name: newDevice.name,
                device_identifier: newDevice.device_identifier,
                work_location_id: String(newDevice.work_location_id),
                is_active: newDevice.is_active,
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

function resetForm() {
    form.value = {
        name: '',
        device_identifier: '',
        work_location_id: '',
        is_active: true,
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
        ? `/api/organization/devices/${props.device!.id}`
        : '/api/organization/devices';

    const method = isEditing.value ? 'PUT' : 'POST';

    const payload: Record<string, unknown> = {
        name: form.value.name,
        work_location_id: parseInt(form.value.work_location_id, 10),
        is_active: form.value.is_active,
    };

    // Only include device_identifier when creating
    if (!isEditing.value) {
        payload.device_identifier = form.value.device_identifier;
    }

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
        errors.value = { general: 'An error occurred while saving the device' };
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
                    {{ isEditing ? 'Edit Device' : 'Add Device' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the biometric device details below.'
                            : 'Fill in the details to register a new biometric device.'
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
                    <Label for="name">Name *</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        type="text"
                        placeholder="e.g., Main Entrance Device"
                        :class="{ 'border-red-500': errors.name }"
                    />
                    <p v-if="errors.name" class="text-sm text-red-500">
                        {{ errors.name }}
                    </p>
                </div>

                <!-- Device Identifier -->
                <div class="space-y-2">
                    <Label for="device_identifier">
                        Device Identifier *
                        <span
                            v-if="isEditing"
                            class="ml-1 text-xs text-slate-500"
                            >(read-only)</span
                        >
                    </Label>
                    <Input
                        id="device_identifier"
                        v-model="form.device_identifier"
                        type="text"
                        placeholder="e.g., DEV-001"
                        :readonly="isEditing"
                        :disabled="isEditing"
                        :class="[
                            { 'border-red-500': errors.device_identifier },
                            isEditing
                                ? 'cursor-not-allowed bg-slate-50 text-slate-500 dark:bg-slate-800/50 dark:text-slate-400'
                                : '',
                        ]"
                    />
                    <p
                        v-if="errors.device_identifier"
                        class="text-sm text-red-500"
                    >
                        {{ errors.device_identifier }}
                    </p>
                    <p
                        v-if="isEditing"
                        class="text-xs text-slate-500 dark:text-slate-400"
                    >
                        The device identifier is hardware-configured and cannot
                        be changed.
                    </p>
                </div>

                <!-- Work Location -->
                <div class="space-y-2">
                    <Label for="work_location_id">Work Location *</Label>
                    <EnumSelect
                        id="work_location_id"
                        v-model="form.work_location_id"
                        :options="workLocationOptions"
                        placeholder="Select a work location"
                    />
                    <p
                        v-if="errors.work_location_id"
                        class="text-sm text-red-500"
                    >
                        {{ errors.work_location_id }}
                    </p>
                </div>

                <!-- Active Toggle -->
                <div class="flex items-center gap-3">
                    <input
                        id="is_active"
                        type="checkbox"
                        v-model="form.is_active"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800"
                    />
                    <div class="space-y-0.5">
                        <Label for="is_active" class="cursor-pointer"
                            >Active</Label
                        >
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Enable this device for attendance tracking
                        </p>
                    </div>
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
                    <Button type="submit" :disabled="isSubmitting">
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
                        {{ isEditing ? 'Update Device' : 'Create Device' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
