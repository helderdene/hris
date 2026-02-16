<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Plus, Star, Check, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface Template {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    is_active: boolean;
    items_count: number;
    created_at: string | null;
}

const props = defineProps<{
    templates: Template[];
}>();

const { tenantName } = useTenant();
const processing = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pre-boarding', href: '/preboarding' },
    { title: 'Templates', href: '/preboarding-templates' },
];

function deleteTemplate(template: Template) {
    if (!confirm(`Are you sure you want to delete "${template.name}"?`)) return;

    router.delete(`/api/preboarding-templates/${template.id}`, {
        preserveState: true,
        onSuccess: () => {
            router.reload({ only: ['templates'] });
        },
    });
}
</script>

<template>
    <Head :title="`Pre-boarding Templates - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    Pre-boarding Templates
                </h1>
                <Link href="/preboarding-templates/create">
                    <Button>
                        <Plus class="mr-1.5 h-4 w-4" />
                        New Template
                    </Button>
                </Link>
            </div>

            <div v-if="templates.length === 0" class="rounded-lg border border-slate-200 bg-white p-12 text-center dark:border-slate-700 dark:bg-slate-800">
                <ClipboardList class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" />
                <p class="mt-3 text-slate-500 dark:text-slate-400">No templates yet. Create your first pre-boarding template.</p>
                <Link href="/preboarding-templates/create" class="mt-4 inline-block">
                    <Button>
                        <Plus class="mr-1.5 h-4 w-4" />
                        Create Template
                    </Button>
                </Link>
            </div>

            <div v-else class="space-y-4">
                <Card v-for="template in templates" :key="template.id">
                    <CardContent class="flex items-center justify-between pt-6">
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-slate-900 dark:text-slate-100">
                                    {{ template.name }}
                                </h3>
                                <Star
                                    v-if="template.is_default"
                                    class="h-4 w-4 fill-amber-400 text-amber-400"
                                />
                                <span
                                    v-if="!template.is_active"
                                    class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                                >
                                    Inactive
                                </span>
                            </div>
                            <p v-if="template.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ template.description }}
                            </p>
                            <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">
                                {{ template.items_count }} items &middot; Created {{ template.created_at }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link :href="`/preboarding-templates/${template.id}/edit`">
                                <Button size="sm" variant="outline">Edit</Button>
                            </Link>
                            <Button
                                size="sm"
                                variant="outline"
                                class="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                                @click="deleteTemplate(template)"
                            >
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </TenantLayout>
</template>
