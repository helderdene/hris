<script setup lang="ts">
import NotificationBell from '@/components/NotificationBell.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useInitials } from '@/composables/useInitials';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

// Get the current page title from the last breadcrumb
const pageTitle = computed(() => {
    if (props.breadcrumbs && props.breadcrumbs.length > 0) {
        return props.breadcrumbs[props.breadcrumbs.length - 1].title;
    }
    return '';
});

const page = usePage();
const user = page.props.auth.user;
const { getInitials } = useInitials();

const showAvatar = computed(() => user?.avatar && user.avatar !== '');
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 dark:border-slate-700/50 dark:bg-card/80 dark:backdrop-blur-xl lg:px-6"
    >
        <!-- Left: Sidebar trigger + Page Title -->
        <div class="flex items-center gap-3">
            <SidebarTrigger class="-ml-1 lg:hidden" />
            <h1 v-if="pageTitle" class="text-xl font-semibold text-slate-900 dark:text-slate-100">
                {{ pageTitle }}
            </h1>
        </div>

        <!-- Right: Notifications + User Menu -->
        <div class="flex items-center gap-2">
            <NotificationBell />
            <DropdownMenu v-if="user">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800/60"
                    >
                        <!-- Avatar -->
                        <Avatar class="h-8 w-8 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="showAvatar"
                                :src="user.avatar!"
                                :alt="user.name"
                            />
                            <AvatarFallback
                                class="rounded-full bg-blue-500 text-sm font-medium text-white"
                            >
                                {{ getInitials(user.name) }}
                            </AvatarFallback>
                        </Avatar>

                        <!-- Name -->
                        <span
                            class="hidden text-sm font-medium text-slate-900 sm:block dark:text-slate-200"
                        >
                            {{ user.name }}
                        </span>

                        <!-- Chevron -->
                        <ChevronDown class="h-4 w-4 text-slate-400 dark:text-slate-500" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-56 rounded-lg"
                    align="end"
                    :side-offset="8"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
