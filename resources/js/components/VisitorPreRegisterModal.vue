<script setup lang="ts">
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
import { Textarea } from '@/components/ui/textarea';
import axios from 'axios';
import { ref } from 'vue';

interface LocationOption {
    id: number;
    name: string;
}

const props = defineProps<{
    open: boolean;
    locations: LocationOption[];
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    saved: [];
}>();

const form = ref({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    purpose: '',
    work_location_id: '',
    host_employee_id: '',
    expected_at: '',
});

const processing = ref(false);
const errors = ref<Record<string, string>>({});

async function handleSubmit() {
    processing.value = true;
    errors.value = {};

    try {
        // Create the visitor
        const visitorResponse = await axios.post('/api/visitors', {
            first_name: form.value.first_name,
            last_name: form.value.last_name,
            email: form.value.email || undefined,
            phone: form.value.phone || undefined,
            company: form.value.company || undefined,
        });
        const visitorId = visitorResponse.data.id;

        // Create the visit
        await axios.post('/api/visitor-visits', {
            visitor_id: visitorId,
            work_location_id: form.value.work_location_id,
            purpose: form.value.purpose,
            expected_at: form.value.expected_at || undefined,
            host_employee_id: form.value.host_employee_id || undefined,
        });

        resetForm();
        emit('update:open', false);
        emit('saved');
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const validationErrors = error.response.data.errors;
            for (const key in validationErrors) {
                errors.value[key] = validationErrors[key][0];
            }
        } else if (axios.isAxiosError(error)) {
            const status = error.response?.status;
            const message = error.response?.data?.message || error.message;
            errors.value.general = `Error ${status}: ${message}`;
        } else {
            errors.value.general = 'An unexpected error occurred. Please try again.';
        }
    } finally {
        processing.value = false;
    }
}

function resetForm() {
    form.value = {
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        company: '',
        purpose: '',
        work_location_id: '',
        host_employee_id: '',
        expected_at: '',
    };
    errors.value = {};
}

function close() {
    emit('update:open', false);
    resetForm();
}
</script>

<template>
    <Dialog :open="open" @update:open="close">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Pre-Register Visitor</DialogTitle>
                <DialogDescription>
                    Register an expected visitor. They will receive a QR code for check-in.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4 py-4">
                <div
                    v-if="errors.general"
                    class="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ errors.general }}
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">First Name *</label>
                        <Input v-model="form.first_name" required />
                        <p v-if="errors.first_name" class="mt-1 text-xs text-red-600">{{ errors.first_name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Last Name *</label>
                        <Input v-model="form.last_name" required />
                        <p v-if="errors.last_name" class="mt-1 text-xs text-red-600">{{ errors.last_name }}</p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                        <Input v-model="form.email" type="email" />
                        <p v-if="errors.email" class="mt-1 text-xs text-red-600">{{ errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                        <Input v-model="form.phone" />
                        <p v-if="errors.phone" class="mt-1 text-xs text-red-600">{{ errors.phone }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Company</label>
                    <Input v-model="form.company" />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Location *</label>
                    <select
                        v-model="form.work_location_id"
                        required
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">Select location...</option>
                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                            {{ loc.name }}
                        </option>
                    </select>
                    <p v-if="errors.work_location_id" class="mt-1 text-xs text-red-600">{{ errors.work_location_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Purpose *</label>
                    <Textarea v-model="form.purpose" required rows="2" />
                    <p v-if="errors.purpose" class="mt-1 text-xs text-red-600">{{ errors.purpose }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Expected Date/Time</label>
                    <Input v-model="form.expected_at" type="datetime-local" />
                    <p v-if="errors.expected_at" class="mt-1 text-xs text-red-600">{{ errors.expected_at }}</p>
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="close">Cancel</Button>
                    <Button
                        type="submit"
                        :disabled="processing || !form.first_name || !form.last_name || !form.purpose || !form.work_location_id"
                    >
                        {{ processing ? 'Registering...' : 'Pre-Register' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
