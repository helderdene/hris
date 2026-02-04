<script setup lang="ts">
import TenantLayout from '@/layouts/TenantLayout.vue';
import HelpLayout from '@/layouts/help/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { HelpArticle, HelpCategory } from '@/types/help';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    ChevronRight,
    Clock,
    Eye,
    FileText,
} from 'lucide-vue-next';

interface Props {
    article: HelpArticle;
    category: HelpCategory;
    relatedArticles: HelpArticle[];
    previousArticle: HelpArticle | null;
    nextArticle: HelpArticle | null;
    categories: HelpCategory[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Help Center', href: '/help' },
    { title: props.category.name, href: `/help/${props.category.slug}` },
    { title: props.article.title, href: `/help/${props.category.slug}/${props.article.slug}` },
];

function getArticleHref(article: HelpArticle): string {
    const categorySlug = article.category?.slug || props.category.slug;
    return `/help/${categorySlug}/${article.slug}`;
}

function getPreviousArticleHref(): string {
    if (!props.previousArticle) return '';
    return `/help/${props.category.slug}/${props.previousArticle.slug}`;
}

function getNextArticleHref(): string {
    if (!props.nextArticle) return '';
    return `/help/${props.category.slug}/${props.nextArticle.slug}`;
}

function estimateReadTime(content: string): number {
    const text = content.replace(/<[^>]*>/g, '');
    const words = text.split(/\s+/).length;
    return Math.max(1, Math.ceil(words / 200));
}
</script>

<template>
    <TenantLayout :breadcrumbs="breadcrumbs">
        <Head :title="`${article.title} - Help Center`" />

        <HelpLayout :categories="categories" :current-category-slug="category.slug">
            <article class="max-w-3xl">
                <!-- Breadcrumb Trail -->
                <nav class="mb-6 flex items-center gap-2 text-sm">
                    <Link
                        href="/help"
                        class="text-slate-500 transition-colors hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
                    >
                        Help Center
                    </Link>
                    <ChevronRight class="h-4 w-4 text-slate-300 dark:text-slate-600" />
                    <Link
                        :href="`/help/${category.slug}`"
                        class="text-slate-500 transition-colors hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400"
                    >
                        {{ category.name }}
                    </Link>
                </nav>

                <!-- Article Header -->
                <header class="mb-8">
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white lg:text-3xl">
                        {{ article.title }}
                    </h1>
                    <div class="mt-4 flex flex-wrap items-center gap-4 text-sm text-slate-500 dark:text-slate-400">
                        <span class="flex items-center gap-1.5">
                            <Eye class="h-4 w-4" />
                            {{ article.view_count.toLocaleString() }} views
                        </span>
                        <span class="flex items-center gap-1.5">
                            <Clock class="h-4 w-4" />
                            {{ estimateReadTime(article.content) }} min read
                        </span>
                    </div>
                </header>

                <!-- Article Content -->
                <div class="article-content" v-html="article.content" />

                <!-- Article Footer -->
                <footer class="mt-12 border-t border-slate-200 pt-8 dark:border-slate-700">
                    <!-- Navigation -->
                    <nav class="flex flex-col gap-4 sm:flex-row sm:items-stretch sm:justify-between">
                        <Link
                            v-if="previousArticle"
                            :href="getPreviousArticleHref()"
                            class="group flex flex-1 items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 transition-all hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-800 dark:hover:bg-blue-950/30"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 transition-colors group-hover:bg-blue-100 dark:bg-slate-700 dark:group-hover:bg-blue-900/50">
                                <ArrowLeft class="h-5 w-5 text-slate-600 transition-colors group-hover:text-blue-600 dark:text-slate-400 dark:group-hover:text-blue-400" />
                            </div>
                            <div class="min-w-0">
                                <span class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Previous
                                </span>
                                <p class="truncate font-medium text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                    {{ previousArticle.title }}
                                </p>
                            </div>
                        </Link>
                        <div v-else class="hidden flex-1 sm:block" />

                        <Link
                            v-if="nextArticle"
                            :href="getNextArticleHref()"
                            class="group flex flex-1 items-center justify-end gap-3 rounded-xl border border-slate-200 bg-white p-4 text-right transition-all hover:border-blue-200 hover:bg-blue-50/50 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-800 dark:hover:bg-blue-950/30"
                        >
                            <div class="min-w-0">
                                <span class="text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    Next
                                </span>
                                <p class="truncate font-medium text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                    {{ nextArticle.title }}
                                </p>
                            </div>
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 transition-colors group-hover:bg-blue-100 dark:bg-slate-700 dark:group-hover:bg-blue-900/50">
                                <ArrowRight class="h-5 w-5 text-slate-600 transition-colors group-hover:text-blue-600 dark:text-slate-400 dark:group-hover:text-blue-400" />
                            </div>
                        </Link>
                        <div v-else class="hidden flex-1 sm:block" />
                    </nav>

                    <!-- Related Articles -->
                    <section v-if="relatedArticles.length > 0" class="mt-10">
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                <BookOpen class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                                Related Articles
                            </h2>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <Link
                                v-for="related in relatedArticles"
                                :key="related.id"
                                :href="getArticleHref(related)"
                                class="group flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 transition-all hover:border-slate-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800 dark:hover:border-slate-600"
                            >
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-700">
                                    <FileText class="h-4 w-4 text-slate-500 dark:text-slate-400" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-medium text-slate-900 transition-colors group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                                        {{ related.title }}
                                    </h3>
                                    <p
                                        v-if="related.excerpt"
                                        class="mt-1 text-sm text-slate-500 line-clamp-2 dark:text-slate-400"
                                    >
                                        {{ related.excerpt }}
                                    </p>
                                </div>
                            </Link>
                        </div>
                    </section>
                </footer>
            </article>
        </HelpLayout>
    </TenantLayout>
</template>

<style scoped>
/* Article Content Styling - Custom typography since Tailwind v4 doesn't include Typography plugin */
.article-content {
    color: #334155;
    line-height: 1.75;
}

:global(.dark) .article-content {
    color: #cbd5e1;
}

.article-content :deep(h1) {
    margin-top: 2.5rem;
    margin-bottom: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
}

:global(.dark) .article-content :deep(h1) {
    color: #f8fafc;
}

.article-content :deep(h2) {
    margin-top: 2rem;
    margin-bottom: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: #0f172a;
}

:global(.dark) .article-content :deep(h2) {
    color: #f8fafc;
}

.article-content :deep(h3) {
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: #0f172a;
}

:global(.dark) .article-content :deep(h3) {
    color: #f8fafc;
}

.article-content :deep(h4) {
    margin-top: 1rem;
    margin-bottom: 0.5rem;
    font-size: 1rem;
    font-weight: 600;
    color: #0f172a;
}

:global(.dark) .article-content :deep(h4) {
    color: #f8fafc;
}

.article-content :deep(p) {
    margin-top: 1rem;
    margin-bottom: 1rem;
}

.article-content :deep(a) {
    color: #2563eb;
    text-decoration: underline;
    text-underline-offset: 2px;
    transition: color 0.15s ease;
}

.article-content :deep(a:hover) {
    color: #1d4ed8;
}

:global(.dark) .article-content :deep(a) {
    color: #60a5fa;
}

:global(.dark) .article-content :deep(a:hover) {
    color: #93c5fd;
}

.article-content :deep(strong) {
    font-weight: 600;
    color: #0f172a;
}

:global(.dark) .article-content :deep(strong) {
    color: #f8fafc;
}

.article-content :deep(em) {
    font-style: italic;
}

.article-content :deep(ul) {
    margin-top: 1rem;
    margin-bottom: 1rem;
    margin-left: 1.5rem;
    list-style-type: disc;
}

.article-content :deep(ol) {
    margin-top: 1rem;
    margin-bottom: 1rem;
    margin-left: 1.5rem;
    list-style-type: decimal;
}

.article-content :deep(li) {
    padding-left: 0.25rem;
    margin-bottom: 0.5rem;
}

.article-content :deep(li > ul),
.article-content :deep(li > ol) {
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
}

.article-content :deep(blockquote) {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #3b82f6;
    background-color: rgba(59, 130, 246, 0.05);
    padding: 1rem;
    font-style: italic;
    color: #475569;
}

:global(.dark) .article-content :deep(blockquote) {
    border-left-color: #60a5fa;
    background-color: rgba(59, 130, 246, 0.1);
    color: #94a3b8;
}

.article-content :deep(code) {
    border-radius: 0.25rem;
    background-color: #f1f5f9;
    padding: 0.125rem 0.375rem;
    font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;
    font-size: 0.875rem;
    color: #1e293b;
}

:global(.dark) .article-content :deep(code) {
    background-color: #1e293b;
    color: #e2e8f0;
}

.article-content :deep(pre) {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
    border-radius: 0.5rem;
    background-color: #0f172a;
    padding: 1rem;
}

:global(.dark) .article-content :deep(pre) {
    background-color: #020617;
}

.article-content :deep(pre code) {
    background-color: transparent;
    padding: 0;
    color: #f1f5f9;
}

.article-content :deep(hr) {
    margin-top: 2rem;
    margin-bottom: 2rem;
    border-color: #e2e8f0;
}

:global(.dark) .article-content :deep(hr) {
    border-color: #334155;
}

.article-content :deep(table) {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    width: 100%;
    border-collapse: collapse;
}

.article-content :deep(th) {
    border-bottom: 2px solid #e2e8f0;
    background-color: #f8fafc;
    padding: 0.5rem 1rem;
    text-align: left;
    font-weight: 600;
    color: #0f172a;
}

:global(.dark) .article-content :deep(th) {
    border-bottom-color: #334155;
    background-color: #1e293b;
    color: #f8fafc;
}

.article-content :deep(td) {
    border-bottom: 1px solid #e2e8f0;
    padding: 0.5rem 1rem;
}

:global(.dark) .article-content :deep(td) {
    border-bottom-color: #334155;
}

.article-content :deep(tr:hover td) {
    background-color: #f8fafc;
}

:global(.dark) .article-content :deep(tr:hover td) {
    background-color: rgba(30, 41, 59, 0.5);
}

.article-content :deep(img) {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
    max-width: 100%;
    border-radius: 0.5rem;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
}

.article-content :deep(figure) {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
}

.article-content :deep(figcaption) {
    margin-top: 0.5rem;
    text-align: center;
    font-size: 0.875rem;
    color: #64748b;
}

:global(.dark) .article-content :deep(figcaption) {
    color: #94a3b8;
}
</style>
