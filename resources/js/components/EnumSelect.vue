<script setup lang="ts">
import { computed } from 'vue';

interface EnumOption {
    value: string;
    label: string;
}

interface Props {
    options: EnumOption[];
    modelValue: string | null | undefined;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
}

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Select an option',
    disabled: false,
    id: undefined,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const currentLabel = computed(() => {
    if (!props.modelValue) return '';
    const option = (props.options ?? []).find((opt) => opt.value === props.modelValue);
    return option?.label ?? props.modelValue;
});

function handleChange(event: Event) {
    const target = event.target as HTMLSelectElement;
    emit('update:modelValue', target.value);
}
</script>

<template>
    <select
        :id="id"
        :value="modelValue ?? ''"
        :disabled="disabled"
        class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900"
        @change="handleChange"
    >
        <option value="" disabled>{{ placeholder }}</option>
        <option
            v-for="option in options ?? []"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
