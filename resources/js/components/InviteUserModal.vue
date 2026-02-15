<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { computed, ref, watch } from 'vue';

interface Role {
    value: string;
    label: string;
}

interface UnlinkedEmployee {
    id: number;
    employee_number: string;
    full_name: string;
    email: string;
}

interface Props {
    open: boolean;
    roles: Role[];
    unlinkedEmployees?: UnlinkedEmployee[];
}

const props = withDefaults(defineProps<Props>(), {
    unlinkedEmployees: () => [],
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const form = ref({
    email: '',
    name: '',
    role: 'employee',
    employee_id: null as number | null,
});

const employeeSearch = ref('');
const selectedEmployee = ref<UnlinkedEmployee | null>(null);

const filteredEmployees = computed(() => {
    if (!employeeSearch.value) {
        return props.unlinkedEmployees;
    }
    const search = employeeSearch.value.toLowerCase();
    return props.unlinkedEmployees.filter(
        (emp) =>
            emp.full_name.toLowerCase().includes(search) ||
            emp.employee_number.toLowerCase().includes(search) ||
            emp.email.toLowerCase().includes(search),
    );
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);
const recentlySuccessful = ref(false);

/**
 * Handles selecting an employee from the dropdown.
 */
const selectEmployee = (employee: UnlinkedEmployee | null) => {
    selectedEmployee.value = employee;
    employeeSearch.value = '';

    if (employee) {
        form.value.employee_id = employee.id;
        form.value.name = employee.full_name;
        form.value.email = employee.email;
    } else {
        form.value.employee_id = null;
        form.value.name = '';
        form.value.email = '';
    }
};

/**
 * Handles the invite form submission.
 */
const handleSubmit = async () => {
    errors.value = {};
    processing.value = true;

    try {
        const response = await fetch('/api/users/invite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(form.value),
        });

        if (response.status === 201 || response.ok) {
            recentlySuccessful.value = true;
            resetForm();
            emit('success');

            setTimeout(() => {
                recentlySuccessful.value = false;
            }, 2000);
        } else if (response.status === 422) {
            const data = await response.json();
            if (data.errors) {
                errors.value = Object.fromEntries(
                    Object.entries(data.errors as Record<string, string[]>).map(
                        ([key, messages]) => [key, messages[0]],
                    ),
                );
            }
        } else if (response.status === 403) {
            errors.value = {
                email: 'You do not have permission to invite users.',
            };
        } else {
            errors.value = {
                email: 'An unexpected error occurred. Please try again.',
            };
        }
    } catch {
        errors.value = {
            email: 'An unexpected error occurred. Please try again.',
        };
    } finally {
        processing.value = false;
    }
};

/**
 * Gets the CSRF token from cookies for the request header.
 */
const getCsrfToken = (): string => {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
};

/**
 * Resets the form to initial state.
 */
const resetForm = () => {
    form.value = {
        email: '',
        name: '',
        role: 'employee',
        employee_id: null,
    };
    selectedEmployee.value = null;
    employeeSearch.value = '';
    errors.value = {};
};

/**
 * Handles dialog close action.
 */
const handleCancel = () => {
    resetForm();
    emit('update:open', false);
};

/**
 * Handles open state change from the dialog.
 */
const handleOpenChange = (open: boolean) => {
    emit('update:open', open);
    if (!open) {
        resetForm();
    }
};

// Reset state when dialog opens
watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            resetForm();
        }
    },
);
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent data-test="invite-user-modal">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <DialogHeader class="space-y-3">
                    <DialogTitle>Invite Team Member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to a new team member. They will
                        receive an email with instructions to set up their
                        account.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4">
                    <!-- Link to Employee (Optional) -->
                    <div
                        v-if="unlinkedEmployees.length > 0"
                        class="grid gap-2"
                    >
                        <Label for="invite-employee">
                            Link to Employee
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                (optional)
                            </span>
                        </Label>

                        <!-- Selected employee display -->
                        <div
                            v-if="selectedEmployee"
                            class="flex items-center justify-between rounded-md border border-input bg-muted/50 px-3 py-2 text-sm"
                            data-test="selected-employee"
                        >
                            <div>
                                <span class="font-medium">{{
                                    selectedEmployee.full_name
                                }}</span>
                                <span class="ml-2 text-muted-foreground">
                                    ({{ selectedEmployee.employee_number }})
                                </span>
                            </div>
                            <button
                                type="button"
                                class="ml-2 text-muted-foreground hover:text-foreground"
                                @click="selectEmployee(null)"
                                data-test="clear-employee-button"
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
                            </button>
                        </div>

                        <!-- Search & select dropdown -->
                        <div v-else class="relative">
                            <Input
                                id="invite-employee"
                                type="text"
                                v-model="employeeSearch"
                                placeholder="Search employees by name, number, or email..."
                                autocomplete="off"
                                data-test="employee-search-input"
                            />
                            <div
                                v-if="
                                    employeeSearch ||
                                    unlinkedEmployees.length <= 10
                                "
                                class="absolute top-full right-0 left-0 z-10 mt-1 max-h-48 overflow-y-auto rounded-md border border-input bg-popover shadow-md"
                                data-test="employee-dropdown"
                            >
                                <button
                                    v-for="emp in filteredEmployees"
                                    :key="emp.id"
                                    type="button"
                                    class="flex w-full flex-col px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                                    @click="selectEmployee(emp)"
                                    :data-test="`employee-option-${emp.id}`"
                                >
                                    <span class="font-medium">{{
                                        emp.full_name
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ emp.employee_number }} &middot;
                                        {{ emp.email }}
                                    </span>
                                </button>
                                <div
                                    v-if="filteredEmployees.length === 0"
                                    class="px-3 py-2 text-sm text-muted-foreground"
                                >
                                    No matching employees found.
                                </div>
                            </div>
                        </div>
                        <InputError :message="errors.employee_id" />
                    </div>

                    <!-- Name Field -->
                    <div class="grid gap-2">
                        <Label for="invite-name">Name</Label>
                        <Input
                            id="invite-name"
                            type="text"
                            v-model="form.name"
                            placeholder="John Doe"
                            autocomplete="name"
                            required
                            :readonly="!!selectedEmployee"
                            :class="{
                                'bg-muted/50': !!selectedEmployee,
                            }"
                            data-test="invite-name-input"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <!-- Email Field -->
                    <div class="grid gap-2">
                        <Label for="invite-email">Email Address</Label>
                        <Input
                            id="invite-email"
                            type="email"
                            v-model="form.email"
                            placeholder="john@example.com"
                            autocomplete="email"
                            required
                            :readonly="!!selectedEmployee"
                            :class="{
                                'bg-muted/50': !!selectedEmployee,
                            }"
                            data-test="invite-email-input"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <!-- Role Field -->
                    <div class="grid gap-2">
                        <Label for="invite-role">Role</Label>
                        <select
                            id="invite-role"
                            v-model="form.role"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            data-test="invite-role-select"
                        >
                            <option
                                v-for="role in roles"
                                :key="role.value"
                                :value="role.value"
                            >
                                {{ role.label }}
                            </option>
                        </select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            @click="handleCancel"
                            :disabled="processing"
                            data-test="invite-cancel-button"
                        >
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        :disabled="processing || !form.email || !form.name"
                        data-test="invite-submit-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ processing ? 'Sending...' : 'Send Invitation' }}
                    </Button>
                </DialogFooter>

                <!-- Success Message -->
                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="recentlySuccessful"
                        class="text-center text-sm text-green-600 dark:text-green-400"
                    >
                        Invitation sent successfully!
                    </p>
                </Transition>
            </form>
        </DialogContent>
    </Dialog>
</template>
