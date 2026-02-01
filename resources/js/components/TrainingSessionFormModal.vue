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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import { computed, ref, watch } from 'vue';

interface Course {
    id: number;
    title: string;
    code: string;
    max_participants: number | null;
}

interface Session {
    id: number;
    course_id: number;
    title?: string | null;
    start_date: string;
    end_date: string;
    start_time?: string | null;
    end_time?: string | null;
    location?: string | null;
    virtual_link?: string | null;
    max_participants?: number | null;
    notes?: string | null;
    instructor_employee_id?: number | null;
}

interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    session?: Session | null;
    courses: Course[];
    instructors: { id: number; full_name: string }[];
    statusOptions: StatusOption[];
}>();

const emit = defineEmits<{
    success: [];
}>();

const open = defineModel<boolean>('open', { default: false });

const { primaryColor } = useTenant();

const isSubmitting = ref(false);
const errors = ref<Record<string, string>>({});

const form = ref({
    course_id: '',
    title: '',
    start_date: '',
    end_date: '',
    start_time: '',
    end_time: '',
    location: '',
    virtual_link: '',
    max_participants: '',
    notes: '',
    instructor_employee_id: '',
});

const isEditing = computed(() => !!props.session);

const dialogTitle = computed(() =>
    isEditing.value ? 'Edit Training Session' : 'Schedule Training Session'
);

watch(
    () => props.session,
    (session) => {
        if (session) {
            form.value = {
                course_id: String(session.course_id),
                title: session.title || '',
                start_date: session.start_date,
                end_date: session.end_date,
                start_time: session.start_time || '',
                end_time: session.end_time || '',
                location: session.location || '',
                virtual_link: session.virtual_link || '',
                max_participants: session.max_participants ? String(session.max_participants) : '',
                notes: session.notes || '',
                instructor_employee_id: session.instructor_employee_id ? String(session.instructor_employee_id) : '',
            };
        } else {
            resetForm();
        }
    },
    { immediate: true }
);

watch(open, (isOpen) => {
    if (!isOpen) {
        resetForm();
        errors.value = {};
    }
});

function resetForm() {
    form.value = {
        course_id: '',
        title: '',
        start_date: '',
        end_date: '',
        start_time: '',
        end_time: '',
        location: '',
        virtual_link: '',
        max_participants: '',
        notes: '',
        instructor_employee_id: '',
    };
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    isSubmitting.value = true;
    errors.value = {};

    const payload = {
        course_id: parseInt(form.value.course_id) || null,
        title: form.value.title || null,
        start_date: form.value.start_date,
        end_date: form.value.end_date,
        start_time: form.value.start_time || null,
        end_time: form.value.end_time || null,
        location: form.value.location || null,
        virtual_link: form.value.virtual_link || null,
        max_participants: form.value.max_participants ? parseInt(form.value.max_participants) : null,
        notes: form.value.notes || null,
        instructor_employee_id: form.value.instructor_employee_id ? parseInt(form.value.instructor_employee_id) : null,
    };

    try {
        const url = isEditing.value
            ? `/api/training/sessions/${props.session!.id}`
            : '/api/training/sessions';

        const method = isEditing.value ? 'PUT' : 'POST';

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

        if (response.ok) {
            open.value = false;
            emit('success');
        } else {
            const data = await response.json();
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors).map(([key, value]) => [
                        key,
                        Array.isArray(value) ? value[0] : value,
                    ])
                );
            } else if (data.message) {
                errors.value = { general: data.message };
            }
        }
    } catch (error) {
        errors.value = { general: 'An error occurred. Please try again.' };
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    {{ isEditing ? 'Update session details.' : 'Schedule a new training session for a course.' }}
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div v-if="errors.general" class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-900/30 dark:text-red-400">
                    {{ errors.general }}
                </div>

                <!-- Course -->
                <div class="space-y-2">
                    <Label for="course_id">Course *</Label>
                    <Select v-model="form.course_id" :disabled="isEditing">
                        <SelectTrigger :class="{ 'border-red-500': errors.course_id }">
                            <SelectValue placeholder="Select a course" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="course in courses"
                                :key="course.id"
                                :value="String(course.id)"
                            >
                                {{ course.title }} ({{ course.code }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.course_id" class="text-sm text-red-500">{{ errors.course_id }}</p>
                </div>

                <!-- Title Override -->
                <div class="space-y-2">
                    <Label for="title">Session Title (Optional)</Label>
                    <Input
                        id="title"
                        v-model="form.title"
                        placeholder="Leave blank to use course title"
                        :class="{ 'border-red-500': errors.title }"
                    />
                    <p v-if="errors.title" class="text-sm text-red-500">{{ errors.title }}</p>
                </div>

                <!-- Dates -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="start_date">Start Date *</Label>
                        <Input
                            id="start_date"
                            type="date"
                            v-model="form.start_date"
                            :class="{ 'border-red-500': errors.start_date }"
                        />
                        <p v-if="errors.start_date" class="text-sm text-red-500">{{ errors.start_date }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="end_date">End Date *</Label>
                        <Input
                            id="end_date"
                            type="date"
                            v-model="form.end_date"
                            :class="{ 'border-red-500': errors.end_date }"
                        />
                        <p v-if="errors.end_date" class="text-sm text-red-500">{{ errors.end_date }}</p>
                    </div>
                </div>

                <!-- Times -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="start_time">Start Time</Label>
                        <Input
                            id="start_time"
                            type="time"
                            v-model="form.start_time"
                            :class="{ 'border-red-500': errors.start_time }"
                        />
                        <p v-if="errors.start_time" class="text-sm text-red-500">{{ errors.start_time }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="end_time">End Time</Label>
                        <Input
                            id="end_time"
                            type="time"
                            v-model="form.end_time"
                            :class="{ 'border-red-500': errors.end_time }"
                        />
                        <p v-if="errors.end_time" class="text-sm text-red-500">{{ errors.end_time }}</p>
                    </div>
                </div>

                <!-- Location / Virtual -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="location">Location</Label>
                        <Input
                            id="location"
                            v-model="form.location"
                            placeholder="e.g., Training Room A"
                            :class="{ 'border-red-500': errors.location }"
                        />
                        <p v-if="errors.location" class="text-sm text-red-500">{{ errors.location }}</p>
                    </div>
                    <div class="space-y-2">
                        <Label for="virtual_link">Virtual Link</Label>
                        <Input
                            id="virtual_link"
                            v-model="form.virtual_link"
                            placeholder="e.g., https://zoom.us/..."
                            :class="{ 'border-red-500': errors.virtual_link }"
                        />
                        <p v-if="errors.virtual_link" class="text-sm text-red-500">{{ errors.virtual_link }}</p>
                    </div>
                </div>

                <!-- Instructor / Max Participants -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-2">
                        <Label for="instructor">Instructor</Label>
                        <Select v-model="form.instructor_employee_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select instructor" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="''">No instructor</SelectItem>
                                <SelectItem
                                    v-for="instructor in instructors"
                                    :key="instructor.id"
                                    :value="String(instructor.id)"
                                >
                                    {{ instructor.full_name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="max_participants">Max Participants</Label>
                        <Input
                            id="max_participants"
                            type="number"
                            v-model="form.max_participants"
                            placeholder="Leave blank for course default"
                            min="1"
                            :class="{ 'border-red-500': errors.max_participants }"
                        />
                        <p v-if="errors.max_participants" class="text-sm text-red-500">{{ errors.max_participants }}</p>
                    </div>
                </div>

                <!-- Notes -->
                <div class="space-y-2">
                    <Label for="notes">Notes</Label>
                    <Textarea
                        id="notes"
                        v-model="form.notes"
                        placeholder="Additional information about this session..."
                        rows="3"
                        :class="{ 'border-red-500': errors.notes }"
                    />
                    <p v-if="errors.notes" class="text-sm text-red-500">{{ errors.notes }}</p>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="isSubmitting"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        {{ isSubmitting ? 'Saving...' : isEditing ? 'Update Session' : 'Create Session' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
