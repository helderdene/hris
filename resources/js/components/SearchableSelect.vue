<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface Option {
    value: string;
    label: string;
}

interface Props {
    options: Option[];
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

const isOpen = ref(false);
const searchQuery = ref('');
const highlightedIndex = ref(0);
const containerRef = ref<HTMLElement | null>(null);
const inputRef = ref<HTMLInputElement | null>(null);
const listRef = ref<HTMLElement | null>(null);

const selectedLabel = computed(() => {
    if (!props.modelValue) return '';
    const option = props.options.find((opt) => opt.value === props.modelValue);
    return option?.label ?? '';
});

const filteredOptions = computed(() => {
    if (!searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase();
    return props.options.filter((opt) => opt.label.toLowerCase().includes(query));
});

function open() {
    if (props.disabled) return;
    isOpen.value = true;
    searchQuery.value = '';
    highlightedIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
}

function close() {
    isOpen.value = false;
    searchQuery.value = '';
}

function selectOption(option: Option) {
    emit('update:modelValue', option.value);
    close();
}

function handleKeydown(event: KeyboardEvent) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1);
        scrollToHighlighted();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0);
        scrollToHighlighted();
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (filteredOptions.value[highlightedIndex.value]) {
            selectOption(filteredOptions.value[highlightedIndex.value]);
        }
    } else if (event.key === 'Escape') {
        close();
    }
}

function scrollToHighlighted() {
    nextTick(() => {
        const list = listRef.value;
        if (!list) return;
        const item = list.children[highlightedIndex.value] as HTMLElement;
        if (item) {
            item.scrollIntoView({ block: 'nearest' });
        }
    });
}

function handleClickOutside(event: MouseEvent) {
    if (containerRef.value && !containerRef.value.contains(event.target as Node)) {
        close();
    }
}

watch(searchQuery, () => {
    highlightedIndex.value = 0;
});

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
    <div ref="containerRef" class="relative" :id="id">
        <!-- Trigger button -->
        <button
            type="button"
            :disabled="disabled"
            class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900"
            @click="open"
        >
            <span :class="selectedLabel ? 'text-foreground' : 'text-muted-foreground'">
                {{ selectedLabel || placeholder }}
            </span>
            <svg
                class="h-4 w-4 shrink-0 opacity-50"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m7 15 5 5 5-5" />
                <path d="m7 9 5-5 5 5" />
            </svg>
        </button>

        <!-- Dropdown -->
        <div
            v-if="isOpen"
            class="absolute z-50 mt-1 w-full rounded-md border border-input bg-background shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
            <!-- Search input -->
            <div class="border-b border-input p-2 dark:border-slate-700">
                <input
                    ref="inputRef"
                    v-model="searchQuery"
                    type="text"
                    class="h-8 w-full rounded-sm border-0 bg-transparent px-2 text-sm outline-none placeholder:text-muted-foreground"
                    placeholder="Search..."
                    @keydown="handleKeydown"
                />
            </div>

            <!-- Options list -->
            <div ref="listRef" class="max-h-60 overflow-y-auto p-1">
                <div
                    v-for="(option, index) in filteredOptions"
                    :key="option.value"
                    class="flex cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm transition-colors"
                    :class="[
                        index === highlightedIndex
                            ? 'bg-accent text-accent-foreground'
                            : 'hover:bg-accent/50',
                        option.value === modelValue ? 'font-medium' : '',
                    ]"
                    @click="selectOption(option)"
                    @mouseenter="highlightedIndex = index"
                >
                    <svg
                        v-if="option.value === modelValue"
                        class="mr-2 h-4 w-4 shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span v-else class="mr-2 inline-block w-4"></span>
                    {{ option.label }}
                </div>
                <div v-if="filteredOptions.length === 0" class="px-2 py-4 text-center text-sm text-muted-foreground">
                    No results found.
                </div>
            </div>
        </div>
    </div>
</template>
