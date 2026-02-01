<script setup lang="ts">
import AuthLayout from '@/layouts/auth/AuthSimpleLayout.vue';

defineProps<{
    title?: string;
    description?: string;
    size?: 'sm' | 'md' | 'lg';
}>();
</script>

<template>
    <AuthLayout :title="title" :description="description" :size="size">
        <slot />
    </AuthLayout>
</template>
