<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { computed, ref } from 'vue';

interface CourseMaterial {
    id: number;
    title: string;
    description: string | null;
    file_name: string | null;
    file_size: number | null;
    formatted_file_size: string | null;
    mime_type: string | null;
    material_type: string;
    material_type_label: string;
    external_url: string | null;
    download_url: string | null;
    sort_order: number;
    uploader?: { id: number; full_name: string } | null;
    created_at: string | null;
}

interface Props {
    materials: CourseMaterial[];
    courseId: number;
    isAdmin?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    materials: () => [],
    isAdmin: false,
});

const emit = defineEmits<{
    (e: 'delete', material: CourseMaterial): void;
    (e: 'reorder', materialIds: number[]): void;
}>();

const draggedIndex = ref<number | null>(null);
const dragOverIndex = ref<number | null>(null);

const sortedMaterials = computed(() => {
    return [...props.materials].sort((a, b) => a.sort_order - b.sort_order);
});

function getMaterialIcon(type: string): string {
    switch (type) {
        case 'document':
            return 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z';
        case 'video':
            return 'M15.75 10.5l4.72-4.72a.75.75 0 011.28.53v11.38a.75.75 0 01-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 002.25-2.25v-9a2.25 2.25 0 00-2.25-2.25h-9A2.25 2.25 0 002.25 7.5v9a2.25 2.25 0 002.25 2.25z';
        case 'image':
            return 'M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z';
        case 'link':
            return 'M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244';
        default:
            return 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z';
    }
}

function getTypeColor(type: string): string {
    switch (type) {
        case 'document':
            return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
        case 'video':
            return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400';
        case 'image':
            return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        case 'link':
            return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400';
    }
}

function handleDownload(material: CourseMaterial) {
    if (material.material_type === 'link' && material.external_url) {
        window.open(material.external_url, '_blank', 'noopener,noreferrer');
    } else if (material.download_url) {
        window.location.href = material.download_url;
    }
}

function handleDragStart(event: DragEvent, index: number) {
    if (!props.isAdmin) return;
    draggedIndex.value = index;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function handleDragOver(event: DragEvent, index: number) {
    if (!props.isAdmin) return;
    event.preventDefault();
    dragOverIndex.value = index;
}

function handleDragLeave() {
    dragOverIndex.value = null;
}

function handleDrop(event: DragEvent, dropIndex: number) {
    if (!props.isAdmin) return;
    event.preventDefault();

    if (draggedIndex.value === null || draggedIndex.value === dropIndex) {
        draggedIndex.value = null;
        dragOverIndex.value = null;
        return;
    }

    const newOrder = [...sortedMaterials.value];
    const [removed] = newOrder.splice(draggedIndex.value, 1);
    newOrder.splice(dropIndex, 0, removed);

    emit('reorder', newOrder.map(m => m.id));

    draggedIndex.value = null;
    dragOverIndex.value = null;
}

function handleDragEnd() {
    draggedIndex.value = null;
    dragOverIndex.value = null;
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(material, index) in sortedMaterials"
            :key="material.id"
            class="flex items-center gap-4 rounded-lg border border-slate-200 bg-white p-4 transition-all dark:border-slate-700 dark:bg-slate-900"
            :class="{
                'cursor-move': isAdmin,
                'border-dashed border-blue-400 bg-blue-50 dark:border-blue-600 dark:bg-blue-900/20': dragOverIndex === index,
                'opacity-50': draggedIndex === index,
            }"
            :draggable="isAdmin"
            @dragstart="handleDragStart($event, index)"
            @dragover="handleDragOver($event, index)"
            @dragleave="handleDragLeave"
            @drop="handleDrop($event, index)"
            @dragend="handleDragEnd"
        >
            <!-- Drag Handle (Admin only) -->
            <div
                v-if="isAdmin"
                class="flex shrink-0 cursor-grab items-center text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                </svg>
            </div>

            <!-- Material Icon -->
            <div
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                :class="getTypeColor(material.material_type)"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="getMaterialIcon(material.material_type)" />
                </svg>
            </div>

            <!-- Material Info -->
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <h4 class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                        {{ material.title }}
                    </h4>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-xs"
                        :class="getTypeColor(material.material_type)"
                    >
                        {{ material.material_type_label }}
                    </span>
                </div>
                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                    <span v-if="material.file_name">{{ material.file_name }}</span>
                    <span v-if="material.formatted_file_size">{{ material.formatted_file_size }}</span>
                    <span v-if="material.external_url" class="truncate max-w-[200px]">{{ material.external_url }}</span>
                </div>
                <p
                    v-if="material.description"
                    class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400"
                >
                    {{ material.description }}
                </p>
            </div>

            <!-- Actions -->
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="handleDownload(material)"
                >
                    <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path
                            v-if="material.material_type === 'link'"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                        />
                        <path
                            v-else
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                        />
                    </svg>
                    {{ material.material_type === 'link' ? 'Open' : 'Download' }}
                </Button>

                <Button
                    v-if="isAdmin"
                    variant="ghost"
                    size="sm"
                    class="text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-900/20"
                    @click="emit('delete', material)"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </Button>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="materials.length === 0"
            class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center dark:border-slate-700 dark:bg-slate-800/50"
        >
            <svg class="mx-auto h-12 w-12 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
            </svg>
            <h3 class="mt-3 text-sm font-medium text-slate-900 dark:text-slate-100">
                No materials yet
            </h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ isAdmin ? 'Upload documents, videos, or add links to this course.' : 'No learning materials have been added to this course.' }}
            </p>
        </div>
    </div>
</template>
