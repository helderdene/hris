<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Pencil, Plus, Trash2 } from 'lucide-vue-next';

interface TemplateItem {
    id: number;
    name: string;
    is_default: boolean;
    is_active: boolean;
    created_at: string;
}

const props = defineProps<{
    templates: { data: TemplateItem[]; links: any; meta: any };
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Recruitment', href: '/recruitment/offers' },
    { title: 'Offer Templates', href: '/recruitment/offer-templates' },
];

function deleteTemplate(id: number): void {
    if (!confirm('Are you sure you want to delete this template?')) return;
    router.delete(`/api/offer-templates/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Offer Templates - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Offer Templates
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage reusable offer letter templates
                    </p>
                </div>
                <Link href="/recruitment/offer-templates/create">
                    <Button class="gap-2">
                        <Plus class="h-4 w-4" />
                        Create Template
                    </Button>
                </Link>
            </div>

            <Card v-if="templates.data.length === 0">
                <CardContent class="flex flex-col items-center justify-center py-12">
                    <FileText class="h-12 w-12 text-muted-foreground" />
                    <h3 class="mt-4 text-lg font-medium">No templates yet</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Create your first offer template to get started.
                    </p>
                    <Link href="/recruitment/offer-templates/create" class="mt-4">
                        <Button>Create Template</Button>
                    </Link>
                </CardContent>
            </Card>

            <div v-else class="space-y-3">
                <Card v-for="template in templates.data" :key="template.id">
                    <CardContent class="flex items-center justify-between p-4">
                        <div class="flex items-center gap-3">
                            <FileText class="h-5 w-5 text-muted-foreground" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">{{ template.name }}</span>
                                    <span
                                        v-if="template.is_default"
                                        class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300"
                                    >
                                        Default
                                    </span>
                                    <span
                                        v-if="!template.is_active"
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                    >
                                        Inactive
                                    </span>
                                </div>
                                <p class="text-xs text-muted-foreground">
                                    Created {{ template.created_at }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <Link :href="`/recruitment/offer-templates/${template.id}/edit`">
                                <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                                    <Pencil class="h-4 w-4" />
                                </Button>
                            </Link>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="h-8 w-8 p-0 text-destructive"
                                @click="deleteTemplate(template.id)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </TenantLayout>
</template>
