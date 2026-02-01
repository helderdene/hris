<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PlaceholderInsertMenu from '@/Components/Recruitment/PlaceholderInsertMenu.vue';
import RichTextEditor from '@/Components/Recruitment/RichTextEditor.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface TemplateData {
    id: number;
    name: string;
    content: string;
    is_default: boolean;
    is_active: boolean;
}

const props = defineProps<{
    template: TemplateData;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Offer Templates', href: '/recruitment/offer-templates' },
    { title: 'Edit', href: '#' },
];

const form = ref({
    name: props.template.name,
    content: props.template.content,
    is_default: props.template.is_default,
    is_active: props.template.is_active,
});

const errors = ref<Record<string, string>>({});
const processing = ref(false);
const editorRef = ref<InstanceType<typeof RichTextEditor> | null>(null);

function handleInsertPlaceholder(placeholder: string): void {
    editorRef.value?.insertText(placeholder);
}

function submit(): void {
    processing.value = true;
    errors.value = {};

    router.put(`/api/offer-templates/${props.template.id}`, form.value, {
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Edit Template - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-4xl">
            <div class="mb-6">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Edit Offer Template
                </h1>
            </div>

            <form @submit.prevent="submit">
                <Card>
                    <CardContent class="space-y-6 p-6">
                        <div class="space-y-2">
                            <Label for="name">Template Name</Label>
                            <Input id="name" v-model="form.name" />
                            <p v-if="errors.name" class="text-sm text-destructive">{{ errors.name }}</p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <Label>Content</Label>
                                <PlaceholderInsertMenu @insert="handleInsertPlaceholder" />
                            </div>
                            <RichTextEditor
                                ref="editorRef"
                                v-model="form.content"
                                placeholder="Write your offer letter template..."
                            />
                            <p v-if="errors.content" class="text-sm text-destructive">{{ errors.content }}</p>
                        </div>

                        <div class="flex items-center gap-6">
                            <label for="is_default" class="flex items-center gap-2 text-sm font-medium">
                                <input id="is_default" v-model="form.is_default" type="checkbox" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:border-gray-600 dark:bg-gray-800" />
                                Default template
                            </label>
                            <label for="is_active" class="flex items-center gap-2 text-sm font-medium">
                                <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-primary shadow-sm focus:ring-primary dark:border-gray-600 dark:bg-gray-800" />
                                Active
                            </label>
                        </div>
                    </CardContent>
                </Card>

                <div class="mt-6 flex justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        @click="router.visit('/recruitment/offer-templates')"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="processing">
                        {{ processing ? 'Saving...' : 'Save Changes' }}
                    </Button>
                </div>
            </form>
        </div>
    </TenantLayout>
</template>
