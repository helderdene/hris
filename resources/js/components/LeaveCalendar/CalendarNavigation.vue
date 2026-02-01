<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-vue-next';

defineProps<{
    year: number;
    monthName: string;
    isLoading?: boolean;
}>();

const emit = defineEmits<{
    prev: [];
    next: [];
    today: [];
}>();
</script>

<template>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                :disabled="isLoading"
                @click="emit('prev')"
            >
                <ChevronLeftIcon class="h-4 w-4" />
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="isLoading"
                @click="emit('next')"
            >
                <ChevronRightIcon class="h-4 w-4" />
            </Button>
            <h2 class="ml-2 text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ monthName }} {{ year }}
            </h2>
        </div>
        <Button
            variant="outline"
            size="sm"
            :disabled="isLoading"
            @click="emit('today')"
        >
            Today
        </Button>
    </div>
</template>
