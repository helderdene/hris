<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2, GripVertical } from 'lucide-vue-next';
import { computed } from 'vue';

interface TemplateItem {
    id?: number;
    type: string;
    type_label?: string;
    name: string;
    description: string | null;
    is_required: boolean;
    sort_order: number;
    document_category_id: number | null;
}

interface Template {
    id: number;
    name: string;
    description: string | null;
    is_default: boolean;
    is_active: boolean;
    items: TemplateItem[];
}

const props = defineProps<{
    template: Template | null;
    itemTypes: { value: string; label: string }[];
    documentCategories: { value: number; label: string }[];
}>();

const { tenantName } = useTenant();
const isEditing = computed(() => !!props.template);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Pre-boarding', href: '/preboarding' },
    { title: 'Templates', href: '/preboarding-templates' },
    { title: isEditing.value ? 'Edit Template' : 'New Template', href: '#' },
];

const form = useForm({
    name: props.template?.name ?? '',
    description: props.template?.description ?? '',
    is_default: props.template?.is_default ?? false,
    is_active: props.template?.is_active ?? true,
    items: (props.template?.items ?? []).map((item, index) => ({
        ...item,
        sort_order: item.sort_order ?? index,
    })) as TemplateItem[],
});

function addItem() {
    form.items.push({
        type: 'acknowledgment',
        name: '',
        description: null,
        is_required: true,
        sort_order: form.items.length,
        document_category_id: null,
    });
}

function removeItem(index: number) {
    form.items.splice(index, 1);
    form.items.forEach((item, i) => {
        item.sort_order = i;
    });
}

function submit() {
    const url = isEditing.value
        ? `/api/preboarding-templates/${props.template!.id}`
        : '/api/preboarding-templates';

    const method = isEditing.value ? 'put' : 'post';

    router[method](url, form.data(), {
        onSuccess: () => {
            router.visit('/preboarding-templates');
        },
    });
}
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'New'} Pre-boarding Template - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <Button
                    variant="ghost"
                    size="sm"
                    class="mb-3"
                    @click="router.visit('/preboarding-templates')"
                >
                    <ArrowLeft class="mr-1.5 h-4 w-4" />
                    Back to templates
                </Button>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {{ isEditing ? 'Edit Template' : 'New Template' }}
                </h1>
            </div>

            <form @submit.prevent="submit">
                <!-- Template Details -->
                <Card class="mb-6">
                    <CardHeader>
                        <CardTitle>Template Details</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Name *
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                placeholder="e.g., Standard Pre-boarding"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Description
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                placeholder="Template description..."
                            />
                        </div>
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.is_default"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-blue-500 focus:ring-blue-500"
                                />
                                <span class="text-sm text-slate-700 dark:text-slate-300">Set as default template</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-slate-300 text-blue-500 focus:ring-blue-500"
                                />
                                <span class="text-sm text-slate-700 dark:text-slate-300">Active</span>
                            </label>
                        </div>
                    </CardContent>
                </Card>

                <!-- Template Items -->
                <Card class="mb-6">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Template Items</CardTitle>
                        <Button type="button" size="sm" variant="outline" @click="addItem">
                            <Plus class="mr-1.5 h-4 w-4" />
                            Add Item
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div v-if="form.items.length === 0" class="py-8 text-center text-slate-500 dark:text-slate-400">
                            No items yet. Add items to define the pre-boarding checklist.
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                            >
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2 text-sm text-slate-500">
                                        <GripVertical class="h-4 w-4" />
                                        <span>Item {{ index + 1 }}</span>
                                    </div>
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        class="text-red-500 hover:text-red-600"
                                        @click="removeItem(index)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Type *
                                        </label>
                                        <select
                                            v-model="item.type"
                                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                        >
                                            <option
                                                v-for="itemType in itemTypes"
                                                :key="itemType.value"
                                                :value="itemType.value"
                                            >
                                                {{ itemType.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Document Category
                                        </label>
                                        <select
                                            v-model="item.document_category_id"
                                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                            :disabled="item.type !== 'document_upload'"
                                        >
                                            <option :value="null">None</option>
                                            <option
                                                v-for="cat in documentCategories"
                                                :key="cat.value"
                                                :value="cat.value"
                                            >
                                                {{ cat.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Name *
                                        </label>
                                        <input
                                            v-model="item.name"
                                            type="text"
                                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                            placeholder="e.g., Upload government ID"
                                        />
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Description
                                        </label>
                                        <input
                                            v-model="item.description"
                                            type="text"
                                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                            placeholder="Detailed instructions..."
                                        />
                                    </div>
                                    <div class="flex items-end">
                                        <label class="flex items-center gap-2">
                                            <input
                                                v-model="item.is_required"
                                                type="checkbox"
                                                class="rounded border-slate-300 text-blue-500 focus:ring-blue-500"
                                            />
                                            <span class="text-sm text-slate-700 dark:text-slate-300">Required</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Actions -->
                <div class="flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="router.visit('/preboarding-templates')"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Saving...' : (isEditing ? 'Update Template' : 'Create Template') }}
                    </Button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
