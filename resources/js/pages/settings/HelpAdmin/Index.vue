<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type {
    HelpAdminFilters,
    HelpArticle,
    HelpCategory,
    PaginatedHelpArticles,
} from '@/types/help';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Edit2, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Props {
    categories: HelpCategory[];
    articles: PaginatedHelpArticles;
    filters: HelpAdminFilters;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/profile' },
    { title: 'Help Admin', href: '/settings/help-admin' },
];

// Category Modal State
const isCategoryModalOpen = ref(false);
const editingCategory = ref<HelpCategory | null>(null);
const categoryForm = ref({
    name: '',
    slug: '',
    description: '',
    icon: '',
    sort_order: 0,
    is_active: true,
});
const categoryErrors = ref<Record<string, string>>({});
const isCategorySaving = ref(false);

// Article Modal State
const isArticleModalOpen = ref(false);
const editingArticle = ref<HelpArticle | null>(null);
const articleForm = ref({
    help_category_id: '' as number | '',
    title: '',
    slug: '',
    excerpt: '',
    content: '',
    sort_order: 0,
    is_active: true,
    is_featured: false,
});
const articleErrors = ref<Record<string, string>>({});
const isArticleSaving = ref(false);

// Delete Confirmation State
const isDeleteDialogOpen = ref(false);
const deletingItem = ref<{ type: 'category' | 'article'; item: HelpCategory | HelpArticle } | null>(null);
const isDeleting = ref(false);

// Search State
const searchQuery = ref(props.filters.search || '');
const selectedCategoryId = ref(props.filters.category_id || '');

const articles = computed(() => props.articles.data || []);

// Generate slug from name/title
function generateSlug(text: string): string {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

// Watch for name changes and auto-generate slug
watch(() => categoryForm.value.name, (newName) => {
    if (!editingCategory.value) {
        categoryForm.value.slug = generateSlug(newName);
    }
});

watch(() => articleForm.value.title, (newTitle) => {
    if (!editingArticle.value) {
        articleForm.value.slug = generateSlug(newTitle);
    }
});

// Category Functions
function openCategoryModal(category?: HelpCategory) {
    if (category) {
        editingCategory.value = category;
        categoryForm.value = {
            name: category.name,
            slug: category.slug,
            description: category.description || '',
            icon: category.icon || '',
            sort_order: category.sort_order,
            is_active: category.is_active,
        };
    } else {
        editingCategory.value = null;
        categoryForm.value = {
            name: '',
            slug: '',
            description: '',
            icon: '',
            sort_order: props.categories.length,
            is_active: true,
        };
    }
    categoryErrors.value = {};
    isCategoryModalOpen.value = true;
}

async function saveCategory() {
    isCategorySaving.value = true;
    categoryErrors.value = {};

    try {
        if (editingCategory.value) {
            await axios.put(`/api/help/categories/${editingCategory.value.id}`, categoryForm.value);
        } else {
            await axios.post('/api/help/categories', categoryForm.value);
        }
        isCategoryModalOpen.value = false;
        router.reload();
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errors = error.response.data.errors || {};
            for (const [key, value] of Object.entries(errors)) {
                categoryErrors.value[key] = Array.isArray(value) ? value[0] : String(value);
            }
        }
    } finally {
        isCategorySaving.value = false;
    }
}

// Article Functions
function openArticleModal(article?: HelpArticle) {
    if (article) {
        editingArticle.value = article;
        articleForm.value = {
            help_category_id: article.help_category_id,
            title: article.title,
            slug: article.slug,
            excerpt: article.excerpt || '',
            content: article.content,
            sort_order: article.sort_order,
            is_active: article.is_active,
            is_featured: article.is_featured,
        };
    } else {
        editingArticle.value = null;
        const defaultCategoryId = selectedCategoryId.value
            ? parseInt(selectedCategoryId.value)
            : (props.categories[0]?.id || '');
        articleForm.value = {
            help_category_id: defaultCategoryId,
            title: '',
            slug: '',
            excerpt: '',
            content: '',
            sort_order: 0,
            is_active: true,
            is_featured: false,
        };
    }
    articleErrors.value = {};
    isArticleModalOpen.value = true;
}

async function saveArticle() {
    isArticleSaving.value = true;
    articleErrors.value = {};

    try {
        if (editingArticle.value) {
            await axios.put(`/api/help/articles/${editingArticle.value.id}`, articleForm.value);
        } else {
            await axios.post('/api/help/articles', articleForm.value);
        }
        isArticleModalOpen.value = false;
        router.reload();
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errors = error.response.data.errors || {};
            for (const [key, value] of Object.entries(errors)) {
                articleErrors.value[key] = Array.isArray(value) ? value[0] : String(value);
            }
        }
    } finally {
        isArticleSaving.value = false;
    }
}

// Delete Functions
function confirmDelete(type: 'category' | 'article', item: HelpCategory | HelpArticle) {
    deletingItem.value = { type, item };
    isDeleteDialogOpen.value = true;
}

async function deleteItem() {
    if (!deletingItem.value) return;

    isDeleting.value = true;
    try {
        const { type, item } = deletingItem.value;
        if (type === 'category') {
            await axios.delete(`/api/help/categories/${item.id}`);
        } else {
            await axios.delete(`/api/help/articles/${item.id}`);
        }
        isDeleteDialogOpen.value = false;
        deletingItem.value = null;
        router.reload();
    } catch (error: unknown) {
        if (axios.isAxiosError(error)) {
            alert(error.response?.data?.message || 'Failed to delete');
        }
    } finally {
        isDeleting.value = false;
    }
}

// Filter Functions
function handleFilter() {
    router.get('/settings/help-admin', {
        category_id: selectedCategoryId.value || undefined,
        search: searchQuery.value || undefined,
    }, { preserveState: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Help Admin" />

        <SettingsLayout>
            <div class="space-y-8">
                <!-- Categories Section -->
                <section>
                    <div class="mb-4 flex items-center justify-between">
                        <HeadingSmall
                            title="Help Categories"
                            description="Manage help center categories"
                        />
                        <Button size="sm" @click="openCategoryModal()">
                            <Plus class="mr-1 h-4 w-4" />
                            Add Category
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <Card
                            v-for="category in categories"
                            :key="category.id"
                            class="transition-shadow hover:shadow-md"
                        >
                            <CardHeader class="pb-2">
                                <div class="flex items-center justify-between">
                                    <CardTitle class="text-sm">{{ category.name }}</CardTitle>
                                    <div class="flex items-center gap-1">
                                        <Badge v-if="!category.is_active" variant="secondary">
                                            Inactive
                                        </Badge>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8"
                                            @click="openCategoryModal(category)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive"
                                            @click="confirmDelete('category', category)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <p class="text-xs text-muted-foreground">
                                    {{ category.articles_count || 0 }} articles
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <!-- Articles Section -->
                <section>
                    <div class="mb-4 flex items-center justify-between">
                        <HeadingSmall
                            title="Help Articles"
                            description="Manage help center articles"
                        />
                        <Button size="sm" @click="openArticleModal()">
                            <Plus class="mr-1 h-4 w-4" />
                            Add Article
                        </Button>
                    </div>

                    <!-- Filters -->
                    <div class="mb-4 flex flex-wrap gap-2">
                        <Select v-model="selectedCategoryId" @update:model-value="handleFilter">
                            <SelectTrigger class="w-[180px]">
                                <SelectValue placeholder="All Categories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">All Categories</SelectItem>
                                <SelectItem
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div class="relative flex-1">
                            <Search
                                class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                v-model="searchQuery"
                                placeholder="Search articles..."
                                class="pl-9"
                                @keyup.enter="handleFilter"
                            />
                        </div>
                    </div>

                    <!-- Articles List -->
                    <div v-if="articles.length === 0" class="rounded-lg border border-dashed p-8 text-center">
                        <p class="text-muted-foreground">No articles found</p>
                    </div>

                    <div v-else class="space-y-3">
                        <Card
                            v-for="article in articles"
                            :key="article.id"
                            class="transition-shadow hover:shadow-md"
                        >
                            <CardHeader class="pb-2">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <CardTitle class="text-sm">{{ article.title }}</CardTitle>
                                        <CardDescription class="text-xs">
                                            /help/{{ article.category?.slug }}/{{ article.slug }}
                                        </CardDescription>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8"
                                            @click="openArticleModal(article)"
                                        >
                                            <Edit2 class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 text-destructive"
                                            @click="confirmDelete('article', article)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge variant="outline">{{ article.category?.name }}</Badge>
                                    <Badge :variant="article.is_active ? 'default' : 'secondary'">
                                        {{ article.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                    <Badge v-if="article.is_featured" variant="outline" class="bg-yellow-50 text-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-400">
                                        Featured
                                    </Badge>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </section>
            </div>
        </SettingsLayout>

        <!-- Category Modal -->
        <Dialog v-model:open="isCategoryModalOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingCategory ? 'Edit Category' : 'Add Category' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ editingCategory ? 'Update the category details.' : 'Create a new help category.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="saveCategory" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="category-name">Name</Label>
                        <Input
                            id="category-name"
                            v-model="categoryForm.name"
                            placeholder="Getting Started"
                        />
                        <InputError :message="categoryErrors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="category-slug">Slug</Label>
                        <Input
                            id="category-slug"
                            v-model="categoryForm.slug"
                            placeholder="getting-started"
                        />
                        <InputError :message="categoryErrors.slug" />
                    </div>

                    <div class="space-y-2">
                        <Label for="category-description">Description</Label>
                        <Textarea
                            id="category-description"
                            v-model="categoryForm.description"
                            placeholder="Helpful articles to get you started..."
                            rows="2"
                        />
                        <InputError :message="categoryErrors.description" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="category-icon">Icon (optional)</Label>
                            <Input
                                id="category-icon"
                                v-model="categoryForm.icon"
                                placeholder="book-open"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="category-sort">Sort Order</Label>
                            <Input
                                id="category-sort"
                                v-model.number="categoryForm.sort_order"
                                type="number"
                                min="0"
                            />
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <Switch
                            id="category-active"
                            :checked="categoryForm.is_active"
                            @update:checked="categoryForm.is_active = $event"
                        />
                        <Label for="category-active">Active</Label>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isCategoryModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isCategorySaving">
                            {{ isCategorySaving ? 'Saving...' : 'Save' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Article Modal -->
        <Dialog v-model:open="isArticleModalOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ editingArticle ? 'Edit Article' : 'Add Article' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ editingArticle ? 'Update the article details.' : 'Create a new help article.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="saveArticle" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="article-category">Category</Label>
                        <Select
                            :model-value="String(articleForm.help_category_id)"
                            @update:model-value="articleForm.help_category_id = parseInt($event)"
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select a category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="articleErrors.help_category_id" />
                    </div>

                    <div class="space-y-2">
                        <Label for="article-title">Title</Label>
                        <Input
                            id="article-title"
                            v-model="articleForm.title"
                            placeholder="How to get started"
                        />
                        <InputError :message="articleErrors.title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="article-slug">Slug</Label>
                        <Input
                            id="article-slug"
                            v-model="articleForm.slug"
                            placeholder="how-to-get-started"
                        />
                        <InputError :message="articleErrors.slug" />
                    </div>

                    <div class="space-y-2">
                        <Label for="article-excerpt">Excerpt</Label>
                        <Textarea
                            id="article-excerpt"
                            v-model="articleForm.excerpt"
                            placeholder="A brief description of this article..."
                            rows="2"
                        />
                        <InputError :message="articleErrors.excerpt" />
                    </div>

                    <div class="space-y-2">
                        <Label for="article-content">Content (HTML)</Label>
                        <Textarea
                            id="article-content"
                            v-model="articleForm.content"
                            placeholder="<p>Article content here...</p>"
                            rows="10"
                            class="font-mono text-sm"
                        />
                        <InputError :message="articleErrors.content" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label for="article-sort">Sort Order</Label>
                            <Input
                                id="article-sort"
                                v-model.number="articleForm.sort_order"
                                type="number"
                                min="0"
                            />
                        </div>
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-center space-x-2">
                            <Switch
                                id="article-active"
                                :checked="articleForm.is_active"
                                @update:checked="articleForm.is_active = $event"
                            />
                            <Label for="article-active">Active</Label>
                        </div>
                        <div class="flex items-center space-x-2">
                            <Switch
                                id="article-featured"
                                :checked="articleForm.is_featured"
                                @update:checked="articleForm.is_featured = $event"
                            />
                            <Label for="article-featured">Featured</Label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="isArticleModalOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="isArticleSaving">
                            {{ isArticleSaving ? 'Saving...' : 'Save' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <Dialog v-model:open="isDeleteDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirm Delete</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete this {{ deletingItem?.type }}?
                        This action cannot be undone.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="isDeleteDialogOpen = false">
                        Cancel
                    </Button>
                    <Button variant="destructive" :disabled="isDeleting" @click="deleteItem">
                        {{ isDeleting ? 'Deleting...' : 'Delete' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
