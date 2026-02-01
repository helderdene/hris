<script setup lang="ts">
import { computed, ref } from 'vue';

interface Props {
    firstName: string;
    lastName: string;
    photoUrl?: string | null;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    photoUrl: null,
});

const imageError = ref(false);

function handleImageError() {
    imageError.value = true;
}

const showImage = computed(() => props.photoUrl && !imageError.value);

const initials = computed(() => {
    const firstInitial = props.firstName
        ? props.firstName.charAt(0).toUpperCase()
        : '';
    const lastInitial = props.lastName
        ? props.lastName.charAt(0).toUpperCase()
        : '';
    return firstInitial + lastInitial;
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-8 w-8 text-xs';
        case 'md':
            return 'h-10 w-10 text-sm';
        case 'lg':
            return 'h-20 w-20 text-2xl';
        default:
            return 'h-10 w-10 text-sm';
    }
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg bg-blue-500 font-semibold text-white"
        :class="sizeClasses"
    >
        <img
            v-if="showImage"
            :src="photoUrl!"
            :alt="`${firstName} ${lastName}`"
            class="h-full w-full object-cover"
            @error="handleImageError"
        />
        <span v-else>{{ initials }}</span>
    </div>
</template>
