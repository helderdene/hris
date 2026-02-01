<script setup lang="ts">
import { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

interface Props {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
}

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="text-blue-600 underline decoration-blue-300 underline-offset-4 transition-colors duration-300 ease-out hover:text-blue-700 hover:decoration-blue-500 dark:text-blue-400 dark:decoration-blue-600 dark:hover:text-blue-300 dark:hover:decoration-blue-400"
    >
        <slot />
    </Link>
</template>
