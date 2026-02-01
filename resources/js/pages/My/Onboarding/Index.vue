<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Key,
    Laptop,
    Users,
    GraduationCap,
    PartyPopper,
    AlertCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface OnboardingItem {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    status_color: string;
    due_date: string | null;
    is_overdue: boolean;
    completed_at: string | null;
    equipment_details: Record<string, unknown> | null;
}

interface CategoryGroup {
    category: string;
    category_label: string;
    category_icon: string;
    total: number;
    completed: number;
    items: OnboardingItem[];
}

interface OnboardingChecklist {
    id: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
    completed_at: string | null;
    progress_percentage: number;
    total_items: number;
    completed_items: number;
    pending_items: number;
    items: OnboardingItem[];
    items_by_category: CategoryGroup[];
}

const props = defineProps<{
    checklist: OnboardingChecklist | null;
    itemStatuses: { value: string; label: string; color: string }[];
    categories: { value: string; label: string }[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Self-Service', href: '/my/dashboard' },
    { title: 'My Onboarding', href: '/my/onboarding' },
];

const isCompleted = computed(() => props.checklist?.status === 'completed');

function getCategoryIcon(icon: string) {
    const icons: Record<string, unknown> = {
        key: Key,
        'computer-desktop': Laptop,
        'user-group': Users,
        'academic-cap': GraduationCap,
    };
    return icons[icon] ?? Key;
}

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        blue: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        gray: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    };
    return map[color] ?? map.slate;
}
</script>

<template>
    <Head :title="`My Onboarding - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <!-- No Onboarding -->
            <div v-if="!checklist" class="rounded-lg border border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-800">
                <CheckCircle2 class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />
                <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                    No Active Onboarding
                </h2>
                <p class="mt-2 text-slate-500 dark:text-slate-400">
                    You don't have an active onboarding checklist at this time.
                </p>
            </div>

            <!-- Completed Celebration -->
            <Card v-else-if="isCompleted" class="mb-6 border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/20">
                <CardContent class="pt-6 text-center">
                    <PartyPopper class="mx-auto h-12 w-12 text-green-500" />
                    <h2 class="mt-4 text-xl font-semibold text-green-700 dark:text-green-400">
                        Onboarding Complete!
                    </h2>
                    <p class="mt-2 text-green-600 dark:text-green-500">
                        Congratulations! All your onboarding tasks have been completed.
                    </p>
                    <p v-if="checklist.completed_at" class="mt-1 text-sm text-green-500 dark:text-green-600">
                        Completed on {{ checklist.completed_at }}
                    </p>
                </CardContent>
            </Card>

            <!-- Active Onboarding -->
            <template v-else>
                <!-- Welcome Header -->
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                        Welcome to the Team!
                    </h1>
                    <p class="mt-1 text-slate-500 dark:text-slate-400">
                        Track the progress of your onboarding below. Our team is working to get everything ready for you.
                    </p>
                </div>

                <!-- Progress Overview -->
                <Card class="mb-6">
                    <CardHeader>
                        <CardTitle>Onboarding Progress</CardTitle>
                        <CardDescription v-if="checklist.start_date">
                            Start Date: {{ checklist.start_date }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-4">
                            <div class="h-4 flex-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                <div
                                    class="h-full rounded-full bg-blue-500 transition-all"
                                    :style="{ width: `${checklist.progress_percentage}%` }"
                                />
                            </div>
                            <span class="text-lg font-semibold text-slate-700 dark:text-slate-300">
                                {{ checklist.progress_percentage }}%
                            </span>
                        </div>
                        <div class="mt-4 flex items-center justify-center gap-8 text-sm">
                            <div class="text-center">
                                <span class="block text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ checklist.completed_items }}
                                </span>
                                <span class="text-slate-500 dark:text-slate-400">Completed</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-2xl font-bold text-slate-700 dark:text-slate-300">
                                    {{ checklist.pending_items }}
                                </span>
                                <span class="text-slate-500 dark:text-slate-400">Pending</span>
                            </div>
                            <div class="text-center">
                                <span class="block text-2xl font-bold text-slate-700 dark:text-slate-300">
                                    {{ checklist.total_items }}
                                </span>
                                <span class="text-slate-500 dark:text-slate-400">Total</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Items by Category -->
                <div class="space-y-6">
                    <Card v-for="group in checklist.items_by_category" :key="group.category">
                        <CardHeader>
                            <CardTitle class="flex items-center justify-between">
                                <span class="flex items-center gap-2">
                                    <component :is="getCategoryIcon(group.category_icon)" class="h-5 w-5" />
                                    {{ group.category_label }}
                                </span>
                                <span class="text-sm font-normal text-slate-500">
                                    {{ group.completed }}/{{ group.total }} completed
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                v-for="item in group.items"
                                :key="item.id"
                                class="flex items-start gap-3 rounded-lg border p-4"
                                :class="[
                                    item.status === 'completed' ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-900/20' :
                                    item.status === 'skipped' ? 'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800/50' :
                                    'border-slate-200 dark:border-slate-700'
                                ]"
                            >
                                <CheckCircle2
                                    v-if="item.status === 'completed'"
                                    class="mt-0.5 h-5 w-5 shrink-0 text-green-500"
                                />
                                <Clock
                                    v-else-if="item.status === 'in_progress'"
                                    class="mt-0.5 h-5 w-5 shrink-0 text-amber-500"
                                />
                                <AlertCircle
                                    v-else-if="item.is_overdue"
                                    class="mt-0.5 h-5 w-5 shrink-0 text-red-500"
                                />
                                <div
                                    v-else
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded-full border-2 border-slate-300 dark:border-slate-600"
                                />
                                <div class="flex-1">
                                    <p class="font-medium text-slate-900 dark:text-slate-100">
                                        {{ item.name }}
                                    </p>
                                    <p v-if="item.description" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                        {{ item.description }}
                                    </p>
                                    <div class="mt-2 flex items-center gap-3 text-xs">
                                        <span
                                            class="inline-flex items-center rounded-full px-2 py-0.5 font-medium"
                                            :class="badgeClasses(item.status_color)"
                                        >
                                            {{ item.status_label }}
                                        </span>
                                        <span v-if="item.completed_at" class="text-green-600 dark:text-green-400">
                                            Completed {{ item.completed_at }}
                                        </span>
                                        <span v-else-if="item.due_date" class="text-slate-400">
                                            Due: {{ item.due_date }}
                                        </span>
                                    </div>
                                    <!-- Equipment Details for completed items -->
                                    <div
                                        v-if="item.status === 'completed' && item.equipment_details"
                                        class="mt-3 rounded-md bg-white p-3 dark:bg-slate-800"
                                    >
                                        <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Equipment Assigned:
                                        </p>
                                        <div class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                            <span v-if="item.equipment_details.model">
                                                {{ item.equipment_details.model }}
                                            </span>
                                            <span v-if="item.equipment_details.serial_number" class="ml-2">
                                                (S/N: {{ item.equipment_details.serial_number }})
                                            </span>
                                            <span v-if="item.equipment_details.asset_tag" class="ml-2">
                                                Asset: {{ item.equipment_details.asset_tag }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </template>
        </div>
    </TenantLayout>
</template>
