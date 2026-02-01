<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import OfferStatusBadge from '@/Components/Recruitment/OfferStatusBadge.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Plus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface StatusOption {
    value: string;
    label: string;
    color: string;
}

interface OfferItem {
    id: number;
    candidate_name: string;
    job_title: string;
    position_title: string;
    salary: string;
    salary_currency: string;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    expiry_date: string | null;
    sent_at: string | null;
    created_at: string;
}

const props = defineProps<{
    offers: { data: OfferItem[]; links: any; meta: any };
    statuses: StatusOption[];
    filters: { status: string | null };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/offers' },
    { title: 'Offers', href: '/recruitment/offers' },
];

const statusFilter = ref(props.filters.status || 'all');

watch(statusFilter, (value) => {
    router.get(
        '/recruitment/offers',
        { status: value === 'all' ? undefined : value },
        { preserveState: true, replace: true },
    );
});
</script>

<template>
    <Head :title="`Offers - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Offers
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage job offers sent to candidates
                    </p>
                </div>
                <Link href="/recruitment/offers/create">
                    <Button class="gap-2">
                        <Plus class="h-4 w-4" />
                        Create Offer
                    </Button>
                </Link>
            </div>

            <div class="mb-4">
                <Select v-model="statusFilter">
                    <SelectTrigger class="w-48">
                        <SelectValue placeholder="Filter by status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Statuses</SelectItem>
                        <SelectItem v-for="s in statuses" :key="s.value" :value="s.value">
                            {{ s.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <Card v-if="offers.data.length === 0">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <FileText class="h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-medium">No offers yet</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create your first offer to get started.
                    </p>
                </CardContent>
            </Card>

            <div v-else class="overflow-hidden rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="border-b bg-slate-50 dark:bg-slate-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-slate-500">Candidate</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-500">Position</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-500">Salary</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-500">Status</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-500">Start Date</th>
                            <th class="px-4 py-3 text-left font-medium text-slate-500">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="offer in offers.data"
                            :key="offer.id"
                            class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/30"
                            @click="router.visit(`/recruitment/offers/${offer.id}`)"
                        >
                            <td class="px-4 py-3 font-medium">{{ offer.candidate_name }}</td>
                            <td class="px-4 py-3">{{ offer.position_title }}</td>
                            <td class="px-4 py-3">
                                {{ offer.salary_currency }}
                                {{ Number(offer.salary).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                            </td>
                            <td class="px-4 py-3">
                                <OfferStatusBadge
                                    :status="offer.status"
                                    :label="offer.status_label"
                                    :color="offer.status_color"
                                />
                            </td>
                            <td class="px-4 py-3">{{ offer.start_date ?? '-' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ offer.created_at }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </TenantLayout>
</template>
