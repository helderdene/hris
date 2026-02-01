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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Announcement {
    id: number;
    title: string;
    body: string;
    published_at: string | null;
    formatted_published_at: string | null;
    expires_at: string | null;
    formatted_expires_at: string | null;
    is_pinned: boolean;
    created_by: number | null;
    creator_name: string | null;
    status: 'draft' | 'scheduled' | 'published' | 'expired';
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    announcements?: PaginatedData<Announcement>;
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Announcements', href: '/announcements' },
];

const isFormModalOpen = ref(false);
const editingAnnouncement = ref<Announcement | null>(null);
const isDeleteDialogOpen = ref(false);
const deletingAnnouncement = ref<Announcement | null>(null);
const isSubmitting = ref(false);
const isDeleting = ref(false);
const searchQuery = ref('');
const formErrors = ref<Record<string, string>>({});

const form = ref({
    title: '',
    body: '',
    published_at: '',
    expires_at: '',
    is_pinned: false,
});

const announcements = computed(() => props.announcements?.data ?? []);

function getStatusBadgeClasses(status: string): string {
    switch (status) {
        case 'published':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
        case 'scheduled':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'draft':
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
        case 'expired':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300';
        default:
            return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
    }
}

function getStatusLabel(status: string): string {
    return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatDatetimeLocal(isoString: string | null): string {
    if (!isoString) return '';
    const date = new Date(isoString);
    const offset = date.getTimezoneOffset();
    const local = new Date(date.getTime() - offset * 60 * 1000);
    return local.toISOString().slice(0, 16);
}

function handleAdd() {
    editingAnnouncement.value = null;
    form.value = { title: '', body: '', published_at: '', expires_at: '', is_pinned: false };
    formErrors.value = {};
    isFormModalOpen.value = true;
}

function handleEdit(announcement: Announcement) {
    editingAnnouncement.value = announcement;
    form.value = {
        title: announcement.title,
        body: announcement.body,
        published_at: formatDatetimeLocal(announcement.published_at),
        expires_at: formatDatetimeLocal(announcement.expires_at),
        is_pinned: announcement.is_pinned,
    };
    formErrors.value = {};
    isFormModalOpen.value = true;
}

function handleDeletePrompt(announcement: Announcement) {
    deletingAnnouncement.value = announcement;
    isDeleteDialogOpen.value = true;
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    isSubmitting.value = true;
    formErrors.value = {};

    const isEditing = !!editingAnnouncement.value;
    const url = isEditing
        ? `/api/announcements/${editingAnnouncement.value!.id}`
        : '/api/announcements';
    const method = isEditing ? 'PUT' : 'POST';

    const payload: Record<string, unknown> = {
        title: form.value.title,
        body: form.value.body,
        published_at: form.value.published_at || null,
        expires_at: form.value.expires_at || null,
        is_pinned: form.value.is_pinned,
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

        if (response.ok) {
            isFormModalOpen.value = false;
            editingAnnouncement.value = null;
            router.reload();
        } else if (response.status === 422) {
            const data = await response.json();
            formErrors.value = data.errors ?? {};
        } else {
            const data = await response.json();
            alert(data.message || 'An error occurred.');
        }
    } catch {
        alert('An error occurred while saving the announcement.');
    } finally {
        isSubmitting.value = false;
    }
}

async function handleDelete() {
    if (!deletingAnnouncement.value) return;
    isDeleting.value = true;

    try {
        const response = await fetch(
            `/api/announcements/${deletingAnnouncement.value.id}`,
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                credentials: 'same-origin',
            },
        );

        if (response.ok) {
            isDeleteDialogOpen.value = false;
            deletingAnnouncement.value = null;
            router.reload();
        } else {
            const data = await response.json();
            alert(data.message || 'Failed to delete announcement.');
        }
    } catch {
        alert('An error occurred while deleting the announcement.');
    } finally {
        isDeleting.value = false;
    }
}

let searchTimeout: ReturnType<typeof setTimeout>;
function handleSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.reload({
            data: { search: searchQuery.value || undefined },
        });
    }, 300);
}

function handlePageChange(url: string | null) {
    if (url) {
        router.visit(url);
    }
}
</script>

<template>
    <Head :title="`Announcements - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Announcements
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage company announcements for employees.
                    </p>
                </div>
                <Button
                    @click="handleAdd"
                    class="shrink-0"
                    :style="{ backgroundColor: primaryColor }"
                    data-test="add-announcement-button"
                >
                    <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Announcement
                </Button>
            </div>

            <!-- Search -->
            <div class="max-w-sm">
                <Input
                    v-model="searchQuery"
                    placeholder="Search announcements..."
                    @input="handleSearch"
                    data-test="search-input"
                />
            </div>

            <!-- Announcements Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Desktop Table -->
                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Published At</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Expires At</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Pinned</th>
                                <th scope="col" class="relative px-6 py-3">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr
                                v-for="announcement in announcements"
                                :key="announcement.id"
                                class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                                :data-test="`announcement-row-${announcement.id}`"
                            >
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ announcement.title }}
                                    </div>
                                    <div v-if="announcement.creator_name" class="text-sm text-slate-500 dark:text-slate-400">
                                        by {{ announcement.creator_name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                        :class="getStatusBadgeClasses(announcement.status)"
                                    >
                                        {{ getStatusLabel(announcement.status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ announcement.formatted_published_at ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
                                    {{ announcement.formatted_expires_at ?? '-' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span v-if="announcement.is_pinned" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                        Pinned
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                                <span class="sr-only">Open menu</span>
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                                </svg>
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem @click="handleEdit(announcement)">
                                                <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                                @click="handleDeletePrompt(announcement)"
                                            >
                                                <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List -->
                <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                    <div
                        v-for="announcement in announcements"
                        :key="announcement.id"
                        class="p-4"
                        :data-test="`announcement-card-${announcement.id}`"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ announcement.title }}
                                </div>
                                <div class="text-sm text-slate-500 dark:text-slate-400">
                                    {{ announcement.formatted_published_at ?? 'Draft' }}
                                </div>
                            </div>
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                        <span class="sr-only">Open menu</span>
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                                        </svg>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="handleEdit(announcement)">Edit</DropdownMenuItem>
                                    <DropdownMenuItem
                                        class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                                        @click="handleDeletePrompt(announcement)"
                                    >Delete</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                :class="getStatusBadgeClasses(announcement.status)"
                            >
                                {{ getStatusLabel(announcement.status) }}
                            </span>
                            <span v-if="announcement.is_pinned" class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                Pinned
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="announcements.length === 0" class="px-6 py-12 text-center" data-test="empty-state">
                    <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                    </svg>
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No announcements found</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Get started by creating a new announcement.</p>
                    <div class="mt-6">
                        <Button @click="handleAdd" :style="{ backgroundColor: primaryColor }">
                            <svg class="mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Add Announcement
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="props.announcements && props.announcements.last_page > 1" class="flex items-center justify-center gap-2">
                <Button
                    v-for="link in props.announcements.links"
                    :key="link.label"
                    variant="outline"
                    size="sm"
                    :disabled="!link.url || link.active"
                    :class="{ 'bg-blue-500 text-white': link.active }"
                    @click="handlePageChange(link.url)"
                    v-html="link.label"
                />
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Dialog v-model:open="isFormModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editingAnnouncement ? 'Edit Announcement' : 'Create Announcement' }}</DialogTitle>
                    <DialogDescription>
                        {{ editingAnnouncement ? 'Update the announcement details.' : 'Fill in the details for the new announcement.' }}
                    </DialogDescription>
                </DialogHeader>
                <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
                    <div class="flex flex-col gap-2">
                        <Label for="title">Title</Label>
                        <Input id="title" v-model="form.title" placeholder="Announcement title" data-test="form-title" />
                        <p v-if="formErrors.title" class="text-sm text-red-500">{{ formErrors.title[0] }}</p>
                    </div>
                    <div class="flex flex-col gap-2">
                        <Label for="body">Body</Label>
                        <Textarea id="body" v-model="form.body" rows="5" placeholder="Announcement content" data-test="form-body" />
                        <p v-if="formErrors.body" class="text-sm text-red-500">{{ formErrors.body[0] }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <Label for="published_at">Publish Date</Label>
                            <Input id="published_at" type="datetime-local" v-model="form.published_at" data-test="form-published-at" />
                            <p v-if="formErrors.published_at" class="text-sm text-red-500">{{ formErrors.published_at[0] }}</p>
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label for="expires_at">Expiry Date</Label>
                            <Input id="expires_at" type="datetime-local" v-model="form.expires_at" data-test="form-expires-at" />
                            <p v-if="formErrors.expires_at" class="text-sm text-red-500">{{ formErrors.expires_at[0] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <input id="is_pinned" type="checkbox" v-model="form.is_pinned" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800" data-test="form-is-pinned" />
                        <Label for="is_pinned">Pin this announcement</Label>
                    </div>
                    <DialogFooter class="gap-2 sm:gap-0">
                        <Button variant="outline" type="button" @click="isFormModalOpen = false" :disabled="isSubmitting">Cancel</Button>
                        <Button type="submit" :disabled="isSubmitting" data-test="form-submit">
                            {{ isSubmitting ? 'Saving...' : (editingAnnouncement ? 'Update' : 'Create') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Delete Announcement</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete "{{ deletingAnnouncement?.title }}"? This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button variant="outline" @click="isDeleteDialogOpen = false" :disabled="isDeleting">Cancel</Button>
                    <Button variant="destructive" @click="handleDelete" :disabled="isDeleting" data-test="confirm-delete">
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </TenantLayout>
</template>
