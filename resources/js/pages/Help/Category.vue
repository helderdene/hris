<script setup lang="ts">
import TenantLayout from '@/layouts/TenantLayout.vue';
import HelpLayout from '@/layouts/help/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { HelpArticle, HelpCategory } from '@/types/help';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    FileText,
    FolderOpen,
} from 'lucide-vue-next';

interface Props {
    category: HelpCategory;
    articles: HelpArticle[];
    categories: HelpCategory[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Help Center', href: '/help' },
    { title: props.category.name, href: `/help/${props.category.slug}` },
];

function getArticleHref(article: HelpArticle): string {
    return `/help/${props.category.slug}/${article.slug}`;
}
</script>

<template>
    <TenantLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${category.name} - Help Center`" />

        <HelpLayout :categories="categories" :current-category-slug="category.slug">
            <section>
                <!-- Category Header -->
                <div class="mb-8">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-blue-600 shadow-lg shadow-blue-500/20">
                            <BookOpen class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                                {{ category.name }}
                            </h1>
                            <p
                                v-if="category.description"
                                class="mt-1 text-slate-600 dark:text-slate-400"
                            >
                                {{ category.description }}
                            </p>
                            <p
                                v-else
                                class="mt-1 text-slate-500 dark:text-slate-400"
                            >
                                Browse all {{ category.name.toLowerCase() }} articles
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="articles.length === 0"
                    class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                        <FolderOpen class="h-8 w-8 text-slate-400 dark:text-slate-500" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
                        No articles yet
                    </h3>
                    <p class="mt-2 text-slate-500 dark:text-slate-400">
                        This category doesn't have any articles yet. Check back soon!
                    </p>
                    <Link
                        href="/help"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-md shadow-blue-600/20 transition-all hover:bg-blue-700 hover:shadow-lg"
                    >
                        <ArrowRight class="h-4 w-4 rotate-180" />
                        Back to Help Center
                    </Link>
                </div>

                <!-- Articles List -->
                <div v-else>
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ articles.length }} article{{ articles.length === 1 ? '' : 's' }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-for="article in articles"
                            :key="article.id"
                            :href="getArticleHref(article)"
                            class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-all hover:border-blue-200 hover:bg-blue-50/30 hover:shadow-lg hover:shadow-blue-100/50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-800 dark:hover:bg-blue-950/20 dark:hover:shadow-blue-900/20"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 transition-colors group-hover:bg-blue-100 dark:bg-slate-700 dark:group-hover:bg-blue-900/50">
                                <FileText class="h-5 w-5 text-slate-500 transition-colors group-hover:text-blue-600 dark:text-slate-400 dark:group-hover:text-blue-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-semibold text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                    {{ article.title }}
                                </h3>
                                <p
                                    v-if="article.excerpt"
                                    class="mt-1.5 text-sm text-slate-500 line-clamp-2 dark:text-slate-400"
                                >
                                    {{ article.excerpt }}
                                </p>
                            </div>
                            <ArrowRight class="mt-0.5 h-5 w-5 shrink-0 text-slate-300 transition-all group-hover:translate-x-1 group-hover:text-blue-500 dark:text-slate-600" />
                        </Link>
                    </div>
                </div>
            </section>
        </HelpLayout>
    </TenantLayout>
</template>
