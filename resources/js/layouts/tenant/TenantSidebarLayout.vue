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
        <AppContent variant="sidebar" class="overflow-x-hidden bg-slate-50">
            <TenantSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="p-4 lg:p-6">
                <slot />
            </div>
        </AppContent>
    </AppShell>
</template>
