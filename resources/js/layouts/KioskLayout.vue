<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    title?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-slate-900">
        <Head :title="title ?? 'Kiosk'" />
        <slot />
    </div>
</template>
