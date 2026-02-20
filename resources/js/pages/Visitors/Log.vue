<script setup lang="ts">
import EnumSelect from '@/components/EnumSelect.vue';
import VisitorStatusBadge from '@/components/VisitorStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import type { BreadcrumbItem, VisitorVisitData } from '@/types';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { Download, Filter, Search } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

interface LocationOption {
    id: number;
    name: string;
}

interface EnumOption {
    value: string;
    label: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    locations: LocationOption[];
    statuses: EnumOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Visitors', href: '/visitors' },
    { title: 'Visitor Log', href: '/visitors/log' },
];

const visits = ref<VisitorVisitData[]>([]);
const links = ref<PaginationLink[]>([]);
const totalCount = ref(0);
const currentPage = ref(1);
const lastPage = ref(1);
const loading = ref(false);
const showFilters = ref(false);

// Filters
const searchQuery = ref('');
const statusFilter = ref('');
const locationFilter = ref('');
const dateFrom = ref('');
const dateTo = ref('');

async function fetchVisits(url?: string) {
    loading.value = true;
    try {
        const params: Record<string, string> = {};
        if (searchQuery.value) params.search = searchQuery.value;
        if (statusFilter.value) params.status = statusFilter.value;
        if (locationFilter.value) params.work_location_id = locationFilter.value;
        if (dateFrom.value) params.date_from = dateFrom.value;
        if (dateTo.value) params.date_to = dateTo.value;

        const response = await axios.get(url || '/api/visitor-visits', { params: url ? {} : params });
        visits.value = response.data.data;
        links.value = response.data.links || [];
        totalCount.value = response.data.meta?.total || response.data.data.length;
        currentPage.value = response.data.meta?.current_page || 1;
        lastPage.value = response.data.meta?.last_page || 1;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    fetchVisits();
}

function clearFilters() {
    searchQuery.value = '';
    statusFilter.value = '';
    locationFilter.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    fetchVisits();
}

function goToPage(url: string | null) {
    if (url) fetchVisits(url);
}

async function exportCsv() {
    const params = new URLSearchParams();
    if (statusFilter.value) params.set('status', statusFilter.value);
    if (dateFrom.value) params.set('date_from', dateFrom.value);
    if (dateTo.value) params.set('date_to', dateTo.value);

    window.location.href = `/api/visitor-visits/export?${params.toString()}`;
}

function formatDate(dateStr?: string): string {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

onMounted(() => {
    fetchVisits();
});
</script>

<template>
    <Head :title="`Visitor Log - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Visitor Log
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ totalCount }} total visit records
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" @click="exportCsv">
                        <Download class="mr-2 h-4 w-4" />
                        Export CSV
                    </Button>
                </div>
            </div>

            <!-- Filters Toggle -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <Button variant="outline" @click="showFilters = !showFilters">
                    <Filter class="mr-2 h-4 w-4" />
                    Filters
                </Button>
                <div class="relative flex-1">
                    <Search class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        v-model="searchQuery"
                        placeholder="Search by visitor name..."
                        class="pl-10"
                        @keyup.enter="applyFilters"
                    />
                </div>
            </div>

            <!-- Filter Panel -->
            <div
                v-if="showFilters"
                class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">Date From</label>
                    <Input v-model="dateFrom" type="date" @change="applyFilters" />
                </div>
                <div class="w-full sm:w-40">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">Date To</label>
                    <Input v-model="dateTo" type="date" @change="applyFilters" />
                </div>
                <div class="w-full sm:w-48">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">Status</label>
                    <select
                        v-model="statusFilter"
                        @change="applyFilters"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">All Statuses</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">
                            {{ s.label }}
                        </option>
                    </select>
                </div>
                <div class="w-full sm:w-48">
                    <label class="mb-1.5 block text-xs font-medium text-slate-600 dark:text-slate-400">Location</label>
                    <select
                        v-model="locationFilter"
                        @change="applyFilters"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800"
                    >
                        <option value="">All Locations</option>
                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                            {{ loc.name }}
                        </option>
                    </select>
                </div>
                <Button variant="ghost" size="sm" @click="clearFilters" class="text-slate-600 dark:text-slate-400">
                    Clear filters
                </Button>
            </div>

            <!-- Visits Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <!-- Loading Skeleton -->
                <div v-if="loading" class="divide-y divide-slate-200 dark:divide-slate-700">
                    <div v-for="i in 8" :key="i" class="flex items-center gap-4 px-6 py-4">
                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="h-4 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="h-4 w-16 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                        <div class="ml-auto h-4 w-20 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
                    </div>
                </div>

                <template v-else-if="visits.length > 0">
                    <!-- Desktop Table -->
                    <div class="hidden md:block">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-800/50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Visitor</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Purpose</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Host</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Location</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Check-in</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Check-out</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-slate-500 uppercase dark:text-slate-400">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-for="visit in visits" :key="visit.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div>
                                            <div class="font-medium text-slate-900 dark:text-slate-100">
                                                {{ visit.visitor?.full_name }}
                                            </div>
                                            <div class="text-sm text-slate-500 dark:text-slate-400">
                                                {{ visit.visitor?.company || visit.visitor?.email || '' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="max-w-[160px] truncate px-6 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ visit.purpose }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ visit.host_employee?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ visit.work_location?.name || '-' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ formatDate(visit.checked_in_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ formatDate(visit.checked_out_at) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm whitespace-nowrap text-slate-600 dark:text-slate-300">
                                        {{ visit.check_in_method_label || '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <VisitorStatusBadge :status="visit.status" :label="visit.status_label" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card List -->
                    <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-700">
                        <div v-for="visit in visits" :key="visit.id" class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ visit.visitor?.full_name }}
                                    </div>
                                    <div class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ visit.visitor?.company || '' }}
                                    </div>
                                </div>
                                <VisitorStatusBadge :status="visit.status" :label="visit.status_label" />
                            </div>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ visit.purpose }}</p>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-500 dark:text-slate-400">
                                <span v-if="visit.checked_in_at">In: {{ formatDate(visit.checked_in_at) }}</span>
                                <span v-if="visit.checked_out_at">Out: {{ formatDate(visit.checked_out_at) }}</span>
                                <span v-if="visit.work_location">{{ visit.work_location.name }}</span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Empty State -->
                <div v-if="!loading && visits.length === 0" class="px-6 py-12 text-center">
                    <Search class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500" />
                    <h3 class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100">
                        No visit records found
                    </h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Try adjusting your search or filters.
                    </p>
                </div>

                <!-- Pagination -->
                <div
                    v-if="links.length > 3"
                    class="flex items-center justify-between border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50 sm:px-6"
                >
                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-slate-700 dark:text-slate-300">
                                Page <span class="font-medium">{{ currentPage }}</span>
                                of <span class="font-medium">{{ lastPage }}</span>
                            </p>
                        </div>
                        <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                            <button
                                v-for="(link, index) in links"
                                :key="index"
                                :disabled="!link.url"
                                @click="goToPage(link.url)"
                                class="relative inline-flex items-center px-4 py-2 text-sm font-medium"
                                :class="[
                                    link.active
                                        ? 'z-10 bg-blue-600 text-white'
                                        : 'text-slate-900 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:text-slate-300 dark:ring-slate-600 dark:hover:bg-slate-700',
                                    !link.url ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                                    index === 0 ? 'rounded-l-md' : '',
                                    index === links.length - 1 ? 'rounded-r-md' : '',
                                ]"
                                v-html="link.label"
                            ></button>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
