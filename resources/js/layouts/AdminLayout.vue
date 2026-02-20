<script setup lang="ts">
import AdminLayout from '@/layouts/admin/AdminSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbs">
        <slot />
    </AdminLayout>
</template>
