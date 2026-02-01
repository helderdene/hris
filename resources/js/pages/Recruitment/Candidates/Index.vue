<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

interface CandidateItem {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    phone: string | null;
    applications_count: number;
    created_at: string;
}

const props = defineProps<{
    candidates: { data: CandidateItem[]; links: any; meta: any };
    filters: { search: string | null };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/candidates' },
    { title: 'Candidates', href: '/recruitment/candidates' },
];

const search = ref(props.filters.search || '');
let searchTimeout: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            '/recruitment/candidates',
            { search: value || undefined },
            { preserveState: true, replace: true },
        );
    }, 300);
});
</script>

<template>
    <Head :title="`Candidates - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Candidates</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage your candidate database</p>
                </div>
                <Link href="/recruitment/candidates/create">
                    <Button>Add Candidate</Button>
                </Link>
            </div>

            <!-- Search -->
            <div class="mb-4">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search by name or email..."
                    class="w-full max-w-sm rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder-slate-500"
                />
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800">
                        <tr>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Name</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Email</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Phone</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Applications</th>
                            <th class="px-4 py-3 font-medium text-slate-600 dark:text-slate-300">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr
                            v-for="candidate in candidates.data"
                            :key="candidate.id"
                            class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/recruitment/candidates/${candidate.id}`"
                                    class="font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    {{ candidate.full_name }}
                                </Link>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ candidate.email }}</td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">{{ candidate.phone || '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                    {{ candidate.applications_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ candidate.created_at.split(' ')[0] }}</td>
                        </tr>
                        <tr v-if="candidates.data.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">
                                No candidates found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
