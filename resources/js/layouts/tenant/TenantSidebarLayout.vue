<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import TenantSidebar from '@/components/TenantSidebar.vue';
import TenantSidebarHeader from '@/components/TenantSidebarHeader.vue';
import { useTenant } from '@/composables/useTenant';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { brandingStyles } = useTenant();
</script>

<template>
    <AppShell variant="sidebar" :style="brandingStyles">
        <TenantSidebar />
        <AppContent variant="sidebar" class="relative overflow-x-hidden bg-slate-50 dark:bg-background">
            <!-- Dark mode atmospheric gradient orbs -->
            <div class="pointer-events-none fixed inset-0 hidden overflow-hidden dark:block" aria-hidden="true">
                <div class="absolute -top-40 -right-40 h-96 w-96 rounded-full bg-blue-500/[0.03] blur-3xl" />
                <div class="absolute top-1/3 -left-40 h-80 w-80 rounded-full bg-indigo-500/[0.03] blur-3xl" />
                <div class="absolute -bottom-40 right-1/4 h-72 w-72 rounded-full bg-violet-500/[0.02] blur-3xl" />
            </div>
            <TenantSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="relative p-4 lg:p-6">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
