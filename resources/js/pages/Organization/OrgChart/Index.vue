<script setup lang="ts">
import OrgChartTree from '@/Components/OrgChartTree.vue';
import { Button } from '@/components/ui/button';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Minus, Move, Plus, RotateCcw } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Department {
    id: number;
    name: string;
    code: string;
    status: 'active' | 'inactive';
    parent_id: number | null;
    department_head_id: number | null;
    department_head_name?: string | null;
    children_count?: number;
}

const props = defineProps<{
    departments: Department[];
}>();

const { primaryColor, tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Organization',
        href: '/organization/departments',
    },
    {
        title: 'Org Chart',
        href: '/organization/org-chart',
    },
];

// Zoom and pan state
const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);
const isPanning = ref(false);
const lastMouseX = ref(0);
const lastMouseY = ref(0);

// Zoom limits
const MIN_SCALE = 0.25;
const MAX_SCALE = 2;
const ZOOM_STEP = 0.1;

// Container ref for mouse events
const chartContainer = ref<HTMLElement | null>(null);

// Computed values for display
const zoomPercentage = computed(() => Math.round(scale.value * 100));

// Zoom functions
function zoomIn() {
    scale.value = Math.min(MAX_SCALE, scale.value + ZOOM_STEP);
}

function zoomOut() {
    scale.value = Math.max(MIN_SCALE, scale.value - ZOOM_STEP);
}

function resetView() {
    scale.value = 1;
    translateX.value = 0;
    translateY.value = 0;
}

// Mouse wheel zoom
function handleWheel(event: WheelEvent) {
    event.preventDefault();

    const delta = event.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
    const newScale = Math.max(
        MIN_SCALE,
        Math.min(MAX_SCALE, scale.value + delta),
    );

    // Zoom towards mouse position
    if (chartContainer.value) {
        const rect = chartContainer.value.getBoundingClientRect();
        const mouseX = event.clientX - rect.left;
        const mouseY = event.clientY - rect.top;

        // Calculate new translate values to zoom towards mouse
        const scaleDiff = newScale / scale.value;
        translateX.value = mouseX - (mouseX - translateX.value) * scaleDiff;
        translateY.value = mouseY - (mouseY - translateY.value) * scaleDiff;
    }

    scale.value = newScale;
}

// Pan functions
function startPan(event: MouseEvent) {
    if (event.button === 0) {
        // Left mouse button
        isPanning.value = true;
        lastMouseX.value = event.clientX;
        lastMouseY.value = event.clientY;
        event.preventDefault();
    }
}

function doPan(event: MouseEvent) {
    if (isPanning.value) {
        const deltaX = event.clientX - lastMouseX.value;
        const deltaY = event.clientY - lastMouseY.value;
        translateX.value += deltaX;
        translateY.value += deltaY;
        lastMouseX.value = event.clientX;
        lastMouseY.value = event.clientY;
    }
}

function endPan() {
    isPanning.value = false;
}

// Global mouse up listener to handle pan end even outside container
onMounted(() => {
    window.addEventListener('mouseup', endPan);
    window.addEventListener('mouseleave', endPan);
});

onUnmounted(() => {
    window.removeEventListener('mouseup', endPan);
    window.removeEventListener('mouseleave', endPan);
});
</script>

<template>
    <Head :title="`Org Chart - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Page Header -->
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Organization Chart
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Visualize your organization's department hierarchy.
                    </p>
                </div>
            </div>

            <!-- Org Chart Container -->
            <div
                class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
            >
                <!-- Controls Bar -->
                <div
                    class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 px-4 py-3 dark:border-slate-700"
                >
                    <!-- Zoom Controls -->
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="zoomOut"
                            :disabled="scale <= MIN_SCALE"
                            title="Zoom out"
                        >
                            <Minus class="h-4 w-4" />
                        </Button>
                        <span
                            class="min-w-[4rem] text-center text-sm font-medium text-slate-600 dark:text-slate-400"
                        >
                            {{ zoomPercentage }}%
                        </span>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="zoomIn"
                            :disabled="scale >= MAX_SCALE"
                            title="Zoom in"
                        >
                            <Plus class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="resetView"
                            title="Reset view"
                            class="ml-2"
                        >
                            <RotateCcw class="mr-1 h-4 w-4" />
                            Reset
                        </Button>
                    </div>

                    <!-- Legend -->
                    <div class="flex items-center gap-4 text-xs">
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded-full bg-green-500" />
                            <span class="text-slate-600 dark:text-slate-400"
                                >Active</span
                            >
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="h-3 w-3 rounded-full bg-slate-400" />
                            <span class="text-slate-600 dark:text-slate-400"
                                >Inactive</span
                            >
                        </div>
                        <div class="hidden items-center gap-1.5 sm:flex">
                            <Move class="h-3.5 w-3.5 text-slate-400" />
                            <span class="text-slate-600 dark:text-slate-400">
                                Drag to pan, scroll to zoom
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Chart Area with Horizontal Scroll on Mobile -->
                <div
                    ref="chartContainer"
                    class="relative min-h-[500px] overflow-auto bg-slate-50/50 dark:bg-slate-800/20"
                    :class="{
                        'cursor-grab': !isPanning,
                        'cursor-grabbing': isPanning,
                    }"
                    @wheel="handleWheel"
                    @mousedown="startPan"
                    @mousemove="doPan"
                >
                    <OrgChartTree
                        :departments="departments"
                        :scale="scale"
                        :translate-x="translateX"
                        :translate-y="translateY"
                    />
                </div>

                <!-- Mobile Hint -->
                <div
                    class="border-t border-slate-200 px-4 py-2 text-center text-xs text-slate-500 sm:hidden dark:border-slate-700 dark:text-slate-400"
                >
                    Scroll horizontally to view the full chart
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-if="departments.length === 0"
                class="rounded-xl border border-slate-200 bg-white px-6 py-12 text-center dark:border-slate-700 dark:bg-slate-900"
            >
                <svg
                    class="mx-auto h-12 w-12 text-slate-400 dark:text-slate-500"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"
                    />
                </svg>
                <h3
                    class="mt-2 text-sm font-semibold text-slate-900 dark:text-slate-100"
                >
                    No departments
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Create departments to see them visualized here.
                </p>
                <div class="mt-6">
                    <Button
                        as="a"
                        href="/organization/departments"
                        :style="{ backgroundColor: primaryColor }"
                    >
                        <svg
                            class="mr-2 h-4 w-4"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4.5v15m7.5-7.5h-15"
                            />
                        </svg>
                        Go to Departments
                    </Button>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>

<style scoped>
/* Prevent text selection while panning */
.cursor-grab,
.cursor-grabbing {
    user-select: none;
}
</style>
