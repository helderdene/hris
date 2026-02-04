<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import TenantLayout from '@/layouts/TenantLayout.vue';
import HelpLayout from '@/layouts/help/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import type { HelpArticle, HelpCategory } from '@/types/help';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileText,
    Search,
    SearchX,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    query: string;
    articles: HelpArticle[];
    categories: HelpCategory[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Help Center', href: '/help' },
    { title: 'Search', href: '/help/search' },
];

const searchInput = ref(props.query);

function handleSearch() {
    if (searchInput.value.trim()) {
        router.get('/help/search', { q: searchInput.value.trim() });
    }
}

function getArticleHref(article: HelpArticle): string {
    return `/help/${article.category?.slug}/${article.slug}`;
}

function highlightMatch(text: string, query: string): string {
    if (!query || !text) return text;
    const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return text.replace(regex, '<mark class="bg-amber-200 dark:bg-amber-800/50 px-0.5 rounded">$1</mark>');
}
</script>

<template>
    <TenantLayout :breadcrumbs="breadcrumbs">
        <Head :title="query ? `Search: ${query} - Help Center` : 'Search - Help Center'" />

        <HelpLayout :categories="categories">
            <section>
                <!-- Search Header -->
                <div class="mb-8">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-violet-600 shadow-lg shadow-violet-500/20">
                            <Search class="h-6 w-6 text-white" />
                        </div>
                        <div class="flex-1">
                            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                                Search Help Articles
                            </h1>
                            <p class="mt-1 text-slate-600 dark:text-slate-400">
                                Find answers across all help documentation
                            </p>
                        </div>
                    </div>

                    <!-- Search Form -->
                    <form @submit.prevent="handleSearch" class="mt-6">
                        <div class="flex gap-3">
                            <div class="relative flex-1">
                                <Search class="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                <Input
                                    v-model="searchInput"
                                    type="search"
                                    placeholder="Type your question..."
                                    class="h-12 rounded-xl border-slate-200 bg-white pl-12 pr-4 text-base shadow-sm transition-all focus:border-violet-300 focus:shadow-md focus:ring-violet-200 dark:border-slate-700 dark:bg-slate-800"
                                    autofocus
                                />
                            </div>
                            <Button
                                type="submit"
                                class="h-12 rounded-xl bg-violet-600 px-6 font-medium shadow-md shadow-violet-600/20 transition-all hover:bg-violet-700 hover:shadow-lg"
                            >
                                Search
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Search Results -->
                <div v-if="query">
                    <!-- Results Header -->
                    <div class="mb-6 flex items-center justify-between rounded-lg bg-slate-50 px-4 py-3 dark:bg-slate-800/50">
                        <p class="text-sm font-medium text-slate-700 dark:text-slate-300">
                            <template v-if="articles.length > 0">
                                Found <span class="text-violet-600 dark:text-violet-400">{{ articles.length }}</span>
                                result{{ articles.length === 1 ? '' : 's' }} for
                                "<span class="text-slate-900 dark:text-white">{{ query }}</span>"
                            </template>
                            <template v-else>
                                No results found for "<span class="text-slate-900 dark:text-white">{{ query }}</span>"
                            </template>
                        </p>
                    </div>

                    <!-- Results List -->
                    <div v-if="articles.length > 0" class="space-y-3">
                        <Link
                            v-for="article in articles"
                            :key="article.id"
                            :href="getArticleHref(article)"
                            class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-white p-5 transition-all hover:border-violet-200 hover:bg-violet-50/30 hover:shadow-lg hover:shadow-violet-100/50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-violet-800 dark:hover:bg-violet-950/20 dark:hover:shadow-violet-900/20"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-slate-100 transition-colors group-hover:bg-violet-100 dark:bg-slate-700 dark:group-hover:bg-violet-900/50">
                                <FileText class="h-5 w-5 text-slate-500 transition-colors group-hover:text-violet-600 dark:text-slate-400 dark:group-hover:text-violet-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="font-semibold text-slate-900 transition-colors group-hover:text-violet-600 dark:text-white dark:group-hover:text-violet-400"
                                    v-html="highlightMatch(article.title, query)"
                                />
                                <p
                                    v-if="article.category"
                                    class="mt-1 text-xs text-slate-400 dark:text-slate-500"
                                >
                                    {{ article.category.name }}
                                </p>
                                <p
                                    v-if="article.excerpt"
                                    class="mt-2 text-sm text-slate-500 line-clamp-2 dark:text-slate-400"
                                    v-html="highlightMatch(article.excerpt, query)"
                                />
                            </div>
                            <ArrowRight class="mt-0.5 h-5 w-5 shrink-0 text-slate-300 transition-all group-hover:translate-x-1 group-hover:text-violet-500 dark:text-slate-600" />
                        </Link>
                    </div>

                    <!-- No Results State -->
                    <div
                        v-else
                        class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-700 dark:bg-slate-800/50"
                    >
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                            <SearchX class="h-8 w-8 text-slate-400 dark:text-slate-500" />
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
                            No results found
                        </h3>
                        <p class="mt-2 text-slate-500 dark:text-slate-400">
                            Try searching with different keywords or browse categories.
                        </p>
                        <div class="mt-6 flex justify-center gap-3">
                            <Button
                                variant="outline"
                                @click="searchInput = ''"
                                class="rounded-lg"
                            >
                                Clear search
                            </Button>
                            <Link
                                href="/help"
                                class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-md shadow-violet-600/20 transition-all hover:bg-violet-700 hover:shadow-lg"
                            >
                                Browse categories
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Initial State (no query) -->
                <div
                    v-else
                    class="rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/50 p-12 text-center dark:border-slate-700 dark:bg-slate-800/50"
                >
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-violet-100 to-violet-200 dark:from-violet-900/50 dark:to-violet-800/50">
                        <Search class="h-8 w-8 text-violet-600 dark:text-violet-400" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
                        Search for help
                    </h3>
                    <p class="mt-2 text-slate-500 dark:text-slate-400">
                        Enter a search term above to find relevant help articles.
                    </p>
                </div>
            </section>
        </HelpLayout>
    </TenantLayout>
</template>
