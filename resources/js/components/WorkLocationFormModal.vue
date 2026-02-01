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
import { Textarea } from '@/components/ui/textarea';
import { computed, ref, watch } from 'vue';

interface WorkLocation {
    id: number;
    name: string;
    code: string;
    address: string | null;
    city: string | null;
    region: string | null;
    country: string | null;
    postal_code: string | null;
    location_type: string;
    location_type_label: string;
    timezone: string | null;
    metadata: Record<string, unknown> | null;
    status: string;
}

interface EnumOption {
    value: string;
    label: string;
}

interface MetadataEntry {
    key: string;
    value: string;
}

const props = defineProps<{
    location: WorkLocation | null;
    locationTypes: EnumOption[];
}>();

const emit = defineEmits<{
    (e: 'success'): void;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = ref({
    name: '',
    code: '',
    address: '',
    city: '',
    region: '',
    country: '',
    postal_code: '',
    location_type: '',
    timezone: '',
    status: 'active',
});

const metadataEntries = ref<MetadataEntry[]>([]);
const errors = ref<Record<string, string>>({});
const isSubmitting = ref(false);

const isEditing = computed(() => !!props.location);

const statusOptions: EnumOption[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const commonTimezones: EnumOption[] = [
    { value: '', label: 'Select timezone' },
    { value: 'Asia/Manila', label: 'Asia/Manila (GMT+8)' },
    { value: 'Asia/Singapore', label: 'Asia/Singapore (GMT+8)' },
    { value: 'Asia/Hong_Kong', label: 'Asia/Hong Kong (GMT+8)' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo (GMT+9)' },
    { value: 'Asia/Seoul', label: 'Asia/Seoul (GMT+9)' },
    { value: 'Asia/Bangkok', label: 'Asia/Bangkok (GMT+7)' },
    { value: 'Asia/Jakarta', label: 'Asia/Jakarta (GMT+7)' },
    { value: 'Asia/Dubai', label: 'Asia/Dubai (GMT+4)' },
    { value: 'Europe/London', label: 'Europe/London (GMT+0)' },
    { value: 'Europe/Paris', label: 'Europe/Paris (GMT+1)' },
    { value: 'Europe/Berlin', label: 'Europe/Berlin (GMT+1)' },
    { value: 'America/New_York', label: 'America/New York (GMT-5)' },
    { value: 'America/Chicago', label: 'America/Chicago (GMT-6)' },
    { value: 'America/Denver', label: 'America/Denver (GMT-7)' },
    { value: 'America/Los_Angeles', label: 'America/Los Angeles (GMT-8)' },
    { value: 'Australia/Sydney', label: 'Australia/Sydney (GMT+11)' },
    { value: 'Pacific/Auckland', label: 'Pacific/Auckland (GMT+13)' },
];

const metadataSuggestions = [
    'phone',
    'email',
    'capacity',
    'parking_spaces',
    'floor_count',
    'building_code',
    'emergency_contact',
];

watch(
    () => props.location,
    (newLocation) => {
        if (newLocation) {
            form.value = {
                name: newLocation.name,
                code: newLocation.code,
                address: newLocation.address || '',
                city: newLocation.city || '',
                region: newLocation.region || '',
                country: newLocation.country || '',
                postal_code: newLocation.postal_code || '',
                location_type: newLocation.location_type,
                timezone: newLocation.timezone || '',
                status: newLocation.status,
            };
            // Convert metadata object to array of entries
            if (
                newLocation.metadata &&
                typeof newLocation.metadata === 'object'
            ) {
                metadataEntries.value = Object.entries(
                    newLocation.metadata,
                ).map(([key, value]) => ({
                    key,
                    value: String(value),
                }));
            } else {
                metadataEntries.value = [];
            }
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
        code: '',
        address: '',
        city: '',
        region: '',
        country: '',
        postal_code: '',
        location_type: '',
        timezone: '',
        status: 'active',
    };
    metadataEntries.value = [];
    errors.value = {};
}

function addMetadataEntry() {
    metadataEntries.value.push({ key: '', value: '' });
}

function removeMetadataEntry(index: number) {
    metadataEntries.value.splice(index, 1);
}

function addSuggestedMetadata(suggestion: string) {
    // Check if key already exists
    if (metadataEntries.value.some((e) => e.key === suggestion)) {
        return;
    }
    metadataEntries.value.push({ key: suggestion, value: '' });
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    errors.value = {};
    isSubmitting.value = true;

    const url = isEditing.value
        ? `/api/organization/locations/${props.location!.id}`
        : '/api/organization/locations';

    const method = isEditing.value ? 'PUT' : 'POST';

    // Convert metadata entries to object
    const metadata: Record<string, unknown> = {};
    metadataEntries.value.forEach((entry) => {
        if (entry.key.trim()) {
            // Try to parse as number or boolean
            let value: unknown = entry.value;
            if (entry.value === 'true') value = true;
            else if (entry.value === 'false') value = false;
            else if (!isNaN(Number(entry.value)) && entry.value.trim() !== '')
                value = Number(entry.value);
            metadata[entry.key.trim()] = value;
        }
    });

    const payload = {
        ...form.value,
        address: form.value.address || null,
        city: form.value.city || null,
        region: form.value.region || null,
        country: form.value.country || null,
        postal_code: form.value.postal_code || null,
        timezone: form.value.timezone || null,
        metadata: Object.keys(metadata).length > 0 ? metadata : null,
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
            general: 'An error occurred while saving the location',
        };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Location' : 'Add Location' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the work location details below.'
                            : 'Fill in the details to create a new work location.'
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

                <!-- Name & Code -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="name">Name *</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            placeholder="e.g., Main Office"
                            :class="{ 'border-red-500': errors.name }"
                        />
                        <p v-if="errors.name" class="text-sm text-red-500">
                            {{ errors.name }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="code">Code *</Label>
                        <Input
                            id="code"
                            v-model="form.code"
                            type="text"
                            placeholder="e.g., MAIN-001"
                            :class="{ 'border-red-500': errors.code }"
                        />
                        <p v-if="errors.code" class="text-sm text-red-500">
                            {{ errors.code }}
                        </p>
                    </div>
                </div>

                <!-- Address -->
                <div class="space-y-2">
                    <Label for="address">Street Address</Label>
                    <Textarea
                        id="address"
                        v-model="form.address"
                        placeholder="123 Business Park, Suite 100"
                        rows="2"
                        :class="{ 'border-red-500': errors.address }"
                    />
                    <p v-if="errors.address" class="text-sm text-red-500">
                        {{ errors.address }}
                    </p>
                </div>

                <!-- City & Region -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="city">City</Label>
                        <Input
                            id="city"
                            v-model="form.city"
                            type="text"
                            placeholder="e.g., Manila"
                            :class="{ 'border-red-500': errors.city }"
                        />
                        <p v-if="errors.city" class="text-sm text-red-500">
                            {{ errors.city }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="region">Region/State</Label>
                        <Input
                            id="region"
                            v-model="form.region"
                            type="text"
                            placeholder="e.g., NCR"
                            :class="{ 'border-red-500': errors.region }"
                        />
                        <p v-if="errors.region" class="text-sm text-red-500">
                            {{ errors.region }}
                        </p>
                    </div>
                </div>

                <!-- Country & Postal Code -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="country">Country</Label>
                        <Input
                            id="country"
                            v-model="form.country"
                            type="text"
                            placeholder="e.g., Philippines"
                            :class="{ 'border-red-500': errors.country }"
                        />
                        <p v-if="errors.country" class="text-sm text-red-500">
                            {{ errors.country }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="postal_code">Postal Code</Label>
                        <Input
                            id="postal_code"
                            v-model="form.postal_code"
                            type="text"
                            placeholder="e.g., 1234"
                            :class="{ 'border-red-500': errors.postal_code }"
                        />
                        <p
                            v-if="errors.postal_code"
                            class="text-sm text-red-500"
                        >
                            {{ errors.postal_code }}
                        </p>
                    </div>
                </div>

                <!-- Location Type & Timezone -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="location_type">Location Type *</Label>
                        <EnumSelect
                            id="location_type"
                            v-model="form.location_type"
                            :options="locationTypes"
                            placeholder="Select type"
                        />
                        <p
                            v-if="errors.location_type"
                            class="text-sm text-red-500"
                        >
                            {{ errors.location_type }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="timezone">Timezone</Label>
                        <EnumSelect
                            id="timezone"
                            v-model="form.timezone"
                            :options="commonTimezones"
                            placeholder="Select timezone"
                        />
                        <p v-if="errors.timezone" class="text-sm text-red-500">
                            {{ errors.timezone }}
                        </p>
                    </div>
                </div>

                <!-- Status -->
                <div class="space-y-2">
                    <Label for="status">Status *</Label>
                    <EnumSelect
                        id="status"
                        v-model="form.status"
                        :options="statusOptions"
                        placeholder="Select status"
                    />
                    <p v-if="errors.status" class="text-sm text-red-500">
                        {{ errors.status }}
                    </p>
                </div>

                <!-- Metadata Section -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <Label class="text-base"
                                >Additional Information</Label
                            >
                            <p
                                class="text-sm text-slate-500 dark:text-slate-400"
                            >
                                Add custom metadata fields for this location.
                            </p>
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addMetadataEntry"
                        >
                            <svg
                                class="mr-1.5 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Field
                        </Button>
                    </div>

                    <!-- Suggestions -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="suggestion in metadataSuggestions"
                            :key="suggestion"
                            type="button"
                            class="inline-flex items-center rounded-md border border-slate-300 bg-white px-2 py-1 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            :class="{
                                'cursor-not-allowed opacity-50':
                                    metadataEntries.some(
                                        (e) => e.key === suggestion,
                                    ),
                            }"
                            :disabled="
                                metadataEntries.some(
                                    (e) => e.key === suggestion,
                                )
                            "
                            @click="addSuggestedMetadata(suggestion)"
                        >
                            <svg
                                class="mr-1 h-3 w-3"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            {{ suggestion.replace(/_/g, ' ') }}
                        </button>
                    </div>

                    <div
                        v-if="metadataEntries.length === 0"
                        class="rounded-lg border border-dashed border-slate-300 px-4 py-6 text-center dark:border-slate-600"
                    >
                        <svg
                            class="mx-auto h-8 w-8 text-slate-400 dark:text-slate-500"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"
                            />
                        </svg>
                        <p
                            class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                        >
                            No additional information. Click "Add Field" or use
                            a suggestion above.
                        </p>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="(entry, index) in metadataEntries"
                            :key="index"
                            class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800/50"
                        >
                            <div
                                class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2"
                            >
                                <div>
                                    <Label
                                        :for="`meta-key-${index}`"
                                        class="text-xs"
                                        >Key</Label
                                    >
                                    <Input
                                        :id="`meta-key-${index}`"
                                        v-model="entry.key"
                                        type="text"
                                        placeholder="e.g., phone"
                                        class="mt-1"
                                    />
                                </div>
                                <div>
                                    <Label
                                        :for="`meta-value-${index}`"
                                        class="text-xs"
                                        >Value</Label
                                    >
                                    <Input
                                        :id="`meta-value-${index}`"
                                        v-model="entry.value"
                                        type="text"
                                        placeholder="e.g., +63-2-8888-1234"
                                        class="mt-1"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="mt-5 h-8 w-8 shrink-0 p-0 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400"
                                @click="removeMetadataEntry(index)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M6 18 18 6M6 6l12 12"
                                    />
                                </svg>
                                <span class="sr-only">Remove field</span>
                            </Button>
                        </div>
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
                        {{ isEditing ? 'Update Location' : 'Create Location' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
