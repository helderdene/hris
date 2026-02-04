<script setup lang="ts">
import {
    Card,
    CardContent,
    CardHeader,
} from '@/components/ui/card';
import TenantLayout from '@/layouts/TenantLayout.vue';
import HelpLayout from '@/layouts/help/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { HelpArticle, HelpCategory } from '@/types/help';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    FileText,
    Sparkles,
} from 'lucide-vue-next';

interface Props {
    categories: HelpCategory[];
    featuredArticles: HelpArticle[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Help Center', href: '/help' },
];

function getCategoryHref(category: HelpCategory): string {
    return `/help/${category.slug}`;
}

function getArticleHref(article: HelpArticle): string {
    return `/help/${article.category?.slug}/${article.slug}`;
}

// Category icons mapping (using different visual representations)
function getCategoryIcon(index: number): string {
    const colors = [
        'bg-blue-500',
        'bg-emerald-500',
        'bg-violet-500',
        'bg-amber-500',
        'bg-rose-500',
        'bg-cyan-500',
        'bg-indigo-500',
        'bg-orange-500',
        'bg-teal-500',
        'bg-pink-500',
        'bg-sky-500',
        'bg-lime-500',
    ];
    return colors[index % colors.length];
}
</script>

<template>
    <TenantLayout :breadcrumbs="breadcrumbs">
        <Head title="Help Center" />

        <HelpLayout :categories="categories">
            <!-- Featured Articles Section -->
            <section v-if="featuredArticles.length > 0" class="mb-12">
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                        <Sparkles class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                            Featured Articles
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Essential guides to get you started
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="article in featuredArticles"
                        :key="article.id"
                        :href="getArticleHref(article)"
                        class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 transition-all hover:border-blue-200 hover:shadow-lg hover:shadow-blue-100/50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-800 dark:hover:shadow-blue-900/20"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 shadow-md shadow-blue-500/20">
                                <FileText class="h-5 w-5 text-white" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                    {{ article.title }}
                                </h3>
                                <p
                                    v-if="article.excerpt"
                                    class="mt-1 text-sm text-slate-500 line-clamp-2 dark:text-slate-400"
                                >
                                    {{ article.excerpt }}
                                </p>
                                <span
                                    v-if="article.category"
                                    class="mt-3 inline-flex items-center text-xs text-slate-400 dark:text-slate-500"
                                >
                                    {{ article.category.name }}
                                </span>
                            </div>
                        </div>
                        <ArrowRight class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300 transition-all group-hover:translate-x-1 group-hover:text-blue-500 dark:text-slate-600" />
                    </Link>
                </div>
            </section>

            <!-- Categories Section -->
            <section>
                <div class="mb-6 flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <BookOpen class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                            Browse by Category
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Explore articles organized by topic
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="(category, index) in categories"
                        :key="category.id"
                        :href="getCategoryHref(category)"
                        class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                :class="[
                                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-white shadow-md',
                                    getCategoryIcon(index),
                                ]"
                            >
                                <BookOpen class="h-5 w-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-medium text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                    {{ category.name }}
                                </h3>
                                <p
                                    v-if="category.description"
                                    class="mt-1 text-sm text-slate-500 line-clamp-2 dark:text-slate-400"
                                >
                                    {{ category.description }}
                                </p>
                                <span class="mt-3 inline-flex items-center gap-1 text-xs text-slate-400 dark:text-slate-500">
                                    <FileText class="h-3 w-3" />
                                    {{ category.articles_count }} articles
                                </span>
                            </div>
                        </div>
                        <ArrowRight class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-300 transition-all group-hover:translate-x-1 group-hover:text-slate-500 dark:text-slate-600" />
                    </Link>
                </div>
            </section>
        </HelpLayout>
    </TenantLayout>
</template>
