<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Announcement {
    id: number;
    title: string;
    body: string;
    published_at: string | null;
    formatted_published_at: string | null;
    is_pinned: boolean;
    creator_name: string | null;
    status: string;
}

interface PaginatedData<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{
    announcements: PaginatedData<Announcement>;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'Announcements', href: '/my/announcements' },
];

const announcements = computed(() => props.announcements?.data ?? []);

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
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Announcements
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Stay up to date with company announcements.
                </p>
            </div>

            <!-- Announcement Cards -->
            <div class="flex flex-col gap-4">
                <div
                    v-for="announcement in announcements"
                    :key="announcement.id"
                    class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900"
                    :data-test="`announcement-card-${announcement.id}`"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                                    {{ announcement.title }}
                                </h2>
                                <span v-if="announcement.is_pinned" class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                    Pinned
                                </span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ announcement.formatted_published_at }}
                                <span v-if="announcement.creator_name"> &middot; {{ announcement.creator_name }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4 text-sm leading-relaxed text-slate-700 whitespace-pre-line dark:text-slate-300">
                        {{ announcement.body }}
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="announcements.length === 0" class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900">
                <svg class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">No announcements</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">There are no announcements at this time.</p>
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
    </TenantLayout>
</template>
