<script setup lang="ts">
import { Toaster as Sonner, type ToasterProps } from 'vue-sonner';

const props = withDefaults(defineProps<ToasterProps>(), {
    position: 'top-right',
    richColors: true,
    expand: false,
    closeButton: true,
});
</script>

<template>
    <Sonner
        v-bind="props"
        :class="'toaster group'"
        :toast-options="{
            classes: {
                toast: 'group toast group-[.toaster]:bg-white group-[.toaster]:text-slate-950 group-[.toaster]:border-slate-200 group-[.toaster]:shadow-lg dark:group-[.toaster]:bg-slate-950 dark:group-[.toaster]:text-slate-50 dark:group-[.toaster]:border-slate-800',
                description: 'group-[.toast]:text-slate-500 dark:group-[.toast]:text-slate-400',
                actionButton: 'group-[.toast]:bg-slate-900 group-[.toast]:text-slate-50 dark:group-[.toast]:bg-slate-50 dark:group-[.toast]:text-slate-900',
                cancelButton: 'group-[.toast]:bg-slate-100 group-[.toast]:text-slate-500 dark:group-[.toast]:bg-slate-800 dark:group-[.toast]:text-slate-400',
            },
        }"
    />
</template>
