<script setup lang="ts">
import InviteUserModal from '@/components/InviteUserModal.vue';
import PasswordConfirmationModal from '@/components/PasswordConfirmationModal.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserRoleSelect from '@/components/UserRoleSelect.vue';
import { usePasswordConfirmation } from '@/composables/usePasswordConfirmation';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Role {
    value: string;
    label: string;
}

interface TenantUser {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    invited_at: string | null;
    invitation_accepted_at: string | null;
}

const props = defineProps<{
    users: TenantUser[];
    roles: Role[];
}>();

const { primaryColor, tenantName } = useTenant();
const {
    isOpen: isPasswordModalOpen,
    confirmPassword,
    onConfirmed,
    onCancelled,
} = usePasswordConfirmation();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Users',
        href: '/users',
    },
];

interface UnlinkedEmployee {
    id: number;
    employee_number: string;
    full_name: string;
    email: string;
}

const isInviteModalOpen = ref(false);
const removingUserId = ref<number | null>(null);
const unlinkedEmployees = ref<UnlinkedEmployee[]>([]);
const loadingEmployees = ref(false);

/**
 * Role badge styling configuration.
 * Each role has a distinct color scheme for visual differentiation.
 */
function getRoleBadgeClasses(role: string): string {
    switch (role) {
        case 'admin':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300';
        case 'hr_manager':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
        case 'hr_staff':
            return 'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300';
        case 'hr_consultant':
            return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300';
        case 'supervisor':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300';
        case 'employee':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

/**
 * Get the status label for a user.
 */
function getUserStatus(user: TenantUser): {
    label: string;
    isPending: boolean;
} {
    if (!user.invitation_accepted_at) {
        return { label: 'Pending', isPending: true };
    }
    return { label: 'Active', isPending: false };
}

/**
 * Format a date string for display.
 */
function formatDate(dateString: string | null): string {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Fetches employees that are not linked to a user account.
 */
async function fetchUnlinkedEmployees() {
    loadingEmployees.value = true;
    try {
        const response = await fetch('/api/employees/unlinked', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (response.ok) {
            unlinkedEmployees.value = await response.json();
        }
    } catch {
        unlinkedEmployees.value = [];
    } finally {
        loadingEmployees.value = false;
    }
}

/**
 * Opens the invite modal and fetches unlinked employees.
 */
function openInviteModal() {
    isInviteModalOpen.value = true;
    fetchUnlinkedEmployees();
}

/**
 * Handle user invitation success.
 */
function handleInviteSuccess() {
    isInviteModalOpen.value = false;
    router.reload({ only: ['users'] });
}

/**
 * Handle role change with password confirmation.
 */
async function handleRoleChange(userId: number, newRole: string) {
    const confirmed = await confirmPassword();
    if (!confirmed) return;

    router.patch(
        `/api/users/${userId}`,
        { role: newRole },
        {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['users'] });
            },
        },
    );
}

/**
 * Handle user removal from tenant.
 */
async function handleRemoveUser(user: TenantUser) {
    if (
        !confirm(
            `Are you sure you want to remove ${user.name ?? 'this user'} from this organization?`,
        )
    ) {
        return;
    }

    const confirmed = await confirmPassword();
    if (!confirmed) return;

    removingUserId.value = user.id;

    router.delete(`/api/users/${user.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            router.reload({ only: ['users'] });
        },
        onFinish: () => {
            removingUserId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="`Users - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Team Members
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your organization's team members and their roles.
                    </p>
                </div>
                <Button
                    @click="openInviteModal"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="invite-user-button"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                        />
                    </svg>
                    Invite User
                </Button>
            </div>

            <!-- Users Table -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table
                        class="min-w-full divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    User
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Role
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Status
                                </th>
                                <th
                                    scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Joined
                                </th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-700"
                        >
                            <tr
                                v-for="user in users"
                                :key="user.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`user-row-${user.id}`"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                        >
                                            {{
                                                user.name
                                                    ?.charAt(0)
                                                    ?.toUpperCase() ?? '?'
                                            }}
                                        </div>
                                        <div class="ml-4">
                                            <div
                                                class="font-medium text-slate-900 dark:text-slate-100"
                                            >
                                                {{ user.name ?? 'Unknown' }}
                                            </div>
                                            <div
                                                class="text-sm text-slate-500 dark:text-slate-400"
                                            >
                                                {{ user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <UserRoleSelect
                                        :user-id="user.id"
                                        :current-role="user.role"
                                        :roles="roles"
                                        @change="
                                            (newRole) =>
                                                handleRoleChange(
                                                    user.id,
                                                    newRole,
                                                )
                                        "
                                    />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="
                                            getUserStatus(user).isPending
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                                : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                        "
                                    >
                                        {{ getUserStatus(user).label }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm whitespace-nowrap text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        formatDate(
                                            user.invitation_accepted_at ||
                                                user.invited_at,
                                        )
                                    }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                >
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="h-8 w-8 p-0"
                                            >
                                                <span class="sr-only"
                                                    >Open menu</span
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
                                                        d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                                    />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel
                                                >Actions</DropdownMenuLabel
                                            >
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                :disabled="
                                                    removingUserId === user.id
                                                "
                                                @click="handleRemoveUser(user)"
                                            >
                                                <svg
                                                    class="mr-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="none"
                                                    viewBox="0 0 24 24"
                                                    stroke-width="2"
                                                    stroke="currentColor"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                                                    />
                                                </svg>
                                                Remove from organization
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div
                    class="divide-y divide-slate-200 md:hidden dark:divide-slate-700"
                >
                    <div
                        v-for="user in users"
                        :key="user.id"
                        class="p-4"
                        :data-test="`user-card-${user.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        user.name?.charAt(0)?.toUpperCase() ??
                                        '?'
                                    }}
                                </div>
                                <div>
                                    <div
                                        class="font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ user.name ?? 'Unknown' }}
                                    </div>
                                    <div
                                        class="text-sm text-slate-500 dark:text-slate-400"
                                    >
                                        {{ user.email }}
                                    </div>
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-8 w-8 p-0"
                                    >
                                        <span class="sr-only">Open menu</span>
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
                                                d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                            />
                                        </svg>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel
                                        >Actions</DropdownMenuLabel
                                    >
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        :disabled="removingUserId === user.id"
                                        @click="handleRemoveUser(user)"
                                    >
                                        <svg
                                            class="mr-2 h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke-width="2"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                                            />
                                        </svg>
                                        Remove from organization
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getRoleBadgeClasses(user.role)"
                            >
                                {{ user.role_label }}
                            </span>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="
                                    getUserStatus(user).isPending
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                        : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                "
                            >
                                {{ getUserStatus(user).label }}
                            </span>
                            <span
                                class="text-xs text-slate-400 dark:text-slate-500"
                            >
                                {{
                                    formatDate(
                                        user.invitation_accepted_at ||
                                            user.invited_at,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="mt-3">
                            <UserRoleSelect
                                :user-id="user.id"
                                :current-role="user.role"
                                :roles="roles"
                                @change="
                                    (newRole) =>
                                        handleRoleChange(user.id, newRole)
                                "
                            />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="users.length === 0" class="px-6 py-12 text-center">
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
                        />
                    </svg>
                    <h3
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                    >
                        No team members
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Get started by inviting a new team member.
                    </p>
                    <div class="mt-6">
                        <Button
                            @click="openInviteModal"
                            :style="{ backgroundColor: primaryColor }"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"
                                />
                            </svg>
                            Invite User
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invite User Modal -->
        <InviteUserModal
            v-model:open="isInviteModalOpen"
            :roles="roles"
            :unlinked-employees="unlinkedEmployees"
            @success="handleInviteSuccess"
        />

        <!-- Password Confirmation Modal -->
        <PasswordConfirmationModal
            v-model:open="isPasswordModalOpen"
            title="Confirm Role Change"
            description="For security, please confirm your password to change this user's role."
            @confirmed="onConfirmed"
            @cancelled="onCancelled"
        />
    </TenantLayout>
</template>
