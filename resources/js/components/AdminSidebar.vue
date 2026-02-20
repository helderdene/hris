<script setup lang="ts">
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ExternalLink,
    LayoutGrid,
    Building2,
    CreditCard,
} from 'lucide-vue-next';
import { computed, type Component } from 'vue';

interface NavItem {
    title: string;
    href: string;
    icon: Component;
}

const page = usePage();
const { toggleSidebar, state } = useSidebar();

const isCollapsed = computed(() => state.value === 'collapsed');

const navItems: NavItem[] = [
    { title: 'Dashboard', href: '/admin', icon: LayoutGrid },
    { title: 'Tenants', href: '/admin/tenants', icon: Building2 },
    { title: 'Plans', href: '/admin/plans', icon: CreditCard },
];

function isActive(href: string): boolean {
    const currentPath = page.url;
    if (href === '/admin') {
        return currentPath === '/admin';
    }
    return currentPath.startsWith(href);
}
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
    >
        <SidebarHeader
            class="border-b border-slate-100 px-4 py-4 dark:border-slate-800"
        >
            <Link href="/admin" class="flex items-center gap-3">
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600"
                >
                    <span class="text-sm font-bold text-white">K</span>
                </div>
                <div v-if="!isCollapsed" class="min-w-0">
                    <span class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        KasamaHR
                    </span>
                    <p class="text-xs text-indigo-600 dark:text-indigo-400">
                        Platform Admin
                    </p>
                </div>
            </Link>
        </SidebarHeader>

        <SidebarContent class="px-2 py-4">
            <SidebarGroup>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in navItems" :key="item.href">
                            <SidebarMenuButton
                                as-child
                                :class="[
                                    'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                    isActive(item.href)
                                        ? 'bg-indigo-600 text-white hover:bg-indigo-700'
                                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                ]"
                            >
                                <Link :href="item.href" class="flex items-center gap-3">
                                    <component :is="item.icon" class="h-5 w-5 shrink-0" />
                                    <span v-if="!isCollapsed">{{ item.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="mt-auto px-2 pb-4">
            <SidebarSeparator class="mb-4" />

            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        class="w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100"
                    >
                        <Link href="/dashboard" class="flex items-center gap-3">
                            <ExternalLink class="h-5 w-5 shrink-0" />
                            <span v-if="!isCollapsed">Back to Main Site</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <SidebarMenu class="mt-2">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                        @click="toggleSidebar"
                    >
                        <ChevronLeft
                            :class="[
                                'h-4 w-4 transition-transform',
                                isCollapsed ? 'rotate-180' : '',
                            ]"
                        />
                        <span v-if="!isCollapsed">Collapse</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
