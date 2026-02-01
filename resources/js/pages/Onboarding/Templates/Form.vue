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
import { computed, ref } from 'vue';

interface TemplateItem {
    id?: number;
    category: string;
    category_label?: string;
    name: string;
    description: string | null;
    assigned_role: string;
    assigned_role_label?: string;
    is_required: boolean;
    sort_order: number;
    due_days_offset: number;
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
    categories: { value: string; label: string }[];
    roles: { value: string; label: string; color: string }[];
}>();

const { tenantName } = useTenant();
const isEditing = computed(() => !!props.template);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Onboarding', href: '/onboarding' },
    { title: 'Templates', href: '/onboarding-templates' },
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
        category: 'provisioning',
        name: '',
        description: null,
        assigned_role: 'it',
        is_required: true,
        sort_order: form.items.length,
        due_days_offset: 0,
    });
}

function removeItem(index: number) {
    form.items.splice(index, 1);
    // Update sort orders
    form.items.forEach((item, i) => {
        item.sort_order = i;
    });
}

function getRoleForCategory(category: string): string {
    const roleMap: Record<string, string> = {
        provisioning: 'it',
        equipment: 'admin',
        orientation: 'hr',
        training: 'hr',
    };
    return roleMap[category] ?? 'hr';
}

function onCategoryChange(index: number) {
    const item = form.items[index];
    item.assigned_role = getRoleForCategory(item.category);
}

function submit() {
    const url = isEditing.value
        ? `/api/onboarding-templates/${props.template!.id}`
        : '/api/onboarding-templates';

    const method = isEditing.value ? 'put' : 'post';

    router[method](url, form.data(), {
        onSuccess: () => {
            router.visit('/onboarding-templates');
        },
    });
}
</script>

<template>
    <Head :title="`${isEditing ? 'Edit' : 'New'} Onboarding Template - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <Button
                    variant="ghost"
                    size="sm"
                    class="mb-3"
                    @click="router.visit('/onboarding-templates')"
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
                                placeholder="e.g., Standard Onboarding"
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
                            No items yet. Add items to define the onboarding checklist.
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
                                            Category *
                                        </label>
                                        <select
                                            v-model="item.category"
                                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                            @change="onCategoryChange(index)"
                                        >
                                            <option
                                                v-for="cat in categories"
                                                :key="cat.value"
                                                :value="cat.value"
                                            >
                                                {{ cat.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Assigned Role *
                                        </label>
                                        <select
                                            v-model="item.assigned_role"
                                            class="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                        >
                                            <option
                                                v-for="role in roles"
                                                :key="role.value"
                                                :value="role.value"
                                            >
                                                {{ role.label }}
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
                                            placeholder="e.g., Set up email account"
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
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                                            Due Days Offset
                                        </label>
                                        <div class="flex items-center gap-2">
                                            <input
                                                v-model.number="item.due_days_offset"
                                                type="number"
                                                class="w-24 rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                                            />
                                            <span class="text-xs text-slate-500">
                                                {{ item.due_days_offset === 0 ? 'On start date' :
                                                   item.due_days_offset < 0 ? `${Math.abs(item.due_days_offset)} days before` :
                                                   `${item.due_days_offset} days after` }}
                                            </span>
                                        </div>
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
                        @click="router.visit('/onboarding-templates')"
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
