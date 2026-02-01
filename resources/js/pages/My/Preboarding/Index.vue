<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import PreboardingChecklistItemVue from '@/components/preboarding/PreboardingChecklistItem.vue';
import PreboardingProgressBar from '@/components/preboarding/PreboardingProgressBar.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { PreboardingChecklist, StatusOption } from '@/types/preboarding';
import { Head } from '@inertiajs/vue3';
import { ClipboardCheck } from 'lucide-vue-next';

const props = defineProps<{
    checklist: PreboardingChecklist | null;
    itemStatuses: StatusOption[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
    { title: 'Pre-boarding', href: '/my/preboarding' },
];

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}
</script>

<template>
    <Head :title="`Pre-boarding - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-3xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Pre-boarding Checklist
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Complete the following items before your start date.
                </p>
            </div>

            <!-- No checklist -->
            <Card v-if="!checklist">
                <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
                    <ClipboardCheck class="h-12 w-12 text-slate-300 dark:text-slate-600" />
                    <p class="text-slate-500 dark:text-slate-400">
                        No pre-boarding checklist has been assigned yet.
                    </p>
                </CardContent>
            </Card>

            <!-- Checklist -->
            <template v-else>
                <Card class="mb-6">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Overview</CardTitle>
                            <span
                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="badgeClasses(checklist.status_color)"
                            >
                                {{ checklist.status_label }}
                            </span>
                        </div>
                        <CardDescription v-if="checklist.deadline">
                            Deadline: {{ checklist.deadline }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <PreboardingProgressBar
                            :percentage="checklist.progress_percentage"
                        />
                    </CardContent>
                </Card>

                <div class="flex flex-col gap-3">
                    <PreboardingChecklistItemVue
                        v-for="item in checklist.items"
                        :key="item.id"
                        :item="item"
                        mode="employee"
                    />
                </div>
            </template>
        </div>
    </TenantLayout>
</template>
