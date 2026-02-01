<script setup lang="ts">
import { useInitials } from '@/composables/useInitials';
import { useTenant } from '@/composables/useTenant';
import { computed } from 'vue';

const { tenantName, logoUrl, hasLogo, primaryColor } = useTenant();
const { getInitials } = useInitials();

const initials = computed(() => getInitials(tenantName.value));

const logoStyle = computed(() => ({
    backgroundColor: hasLogo.value ? 'transparent' : primaryColor.value,
}));
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md shadow-sm"
        :style="logoStyle"
    >
        <!-- Custom tenant logo -->
        <img
            v-if="hasLogo && logoUrl"
            :src="logoUrl"
            :alt="tenantName"
            class="h-full w-full object-cover"
        />
        <!-- Fallback to initials with primary color background -->
        <span v-else class="text-sm font-semibold text-white select-none">
            {{ initials }}
        </span>
    </div>
</template>
