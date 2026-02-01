<script setup lang="ts">
defineProps<{
    content: string;
}>();
</script>

<template>
    <div
        class="prose prose-sm max-w-none rounded-md border border-input bg-background p-6"
        v-html="content"
    />
</template>
