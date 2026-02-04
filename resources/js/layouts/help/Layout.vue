<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { HelpCategory } from '@/types/help';
import { Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    ChevronRight,
    HelpCircle,
    Search,
} from 'lucide-vue-next';
import { ref } from 'vue';

interface Props {
    categories: HelpCategory[];
    currentCategorySlug?: string;
}

const props = withDefaults(defineProps<Props>(), {
    currentCategorySlug: '',
});

const searchQuery = ref('');

function handleSearch() {
    if (searchQuery.value.trim()) {
        router.get('/help/search', { q: searchQuery.value.trim() });
    }
}

function getCategoryHref(category: HelpCategory): string {
    return `/help/${category.slug}`;
}

function isCategoryActive(category: HelpCategory): boolean {
    return props.currentCategorySlug === category.slug;
}
</script>

<template>
    <div class="min-h-[calc(100vh-4rem)]">
        <!-- Hero Header -->
        <div class="border-b border-slate-200 bg-gradient-to-br from-slate-50 via-white to-blue-50/30 dark:border-slate-800 dark:from-slate-900 dark:via-slate-900 dark:to-blue-950/20">
            <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="flex flex-col items-start gap-6 md:flex-row md:items-center md:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-600/20">
                            <HelpCircle class="h-6 w-6 text-white" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
                                Help Center
                            </h1>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                Find answers and learn how to use KasamaHR
                            </p>
                        </div>
                    </div>

                    <!-- Search Bar -->
                    <form @submit.prevent="handleSearch" class="w-full md:w-96">
                        <div class="relative">
                            <Search class="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Search articles..."
                                class="h-11 w-full rounded-xl border-slate-200 bg-white pl-11 pr-4 shadow-sm transition-shadow focus:shadow-md dark:border-slate-700 dark:bg-slate-800"
                            />
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <!-- Sidebar -->
                <aside class="w-full shrink-0 lg:w-64">
                    <div class="sticky top-6">
                        <nav class="space-y-1">
                            <!-- All Categories Link -->
                            <Link
                                href="/help"
                                :class="[
                                    'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                                    !currentCategorySlug
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
                                ]"
                            >
                                <BookOpen class="h-4 w-4 shrink-0" />
                                <span class="truncate">All Categories</span>
                            </Link>

                            <!-- Category Links -->
                            <Link
                                v-for="category in categories"
                                :key="category.id"
                                :href="getCategoryHref(category)"
                                :class="[
                                    'group flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all',
                                    isCategoryActive(category)
                                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white',
                                ]"
                            >
                                <span class="truncate">{{ category.name }}</span>
                                <div class="flex items-center gap-1">
                                    <span
                                        v-if="category.active_articles_count !== undefined"
                                        class="text-xs text-slate-400 dark:text-slate-500"
                                    >
                                        {{ category.active_articles_count }}
                                    </span>
                                    <ChevronRight
                                        :class="[
                                            'h-4 w-4 transition-transform',
                                            isCategoryActive(category)
                                                ? 'text-blue-500'
                                                : 'text-slate-300 group-hover:translate-x-0.5 dark:text-slate-600',
                                        ]"
                                    />
                                </div>
                            </Link>
                        </nav>

                        <!-- Quick Links -->
                        <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Need more help?
                            </h3>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                                Contact your HR administrator for personalized assistance.
                            </p>
                        </div>
                    </div>
                </aside>

                <!-- Main Content Area -->
                <main class="min-w-0 flex-1">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
