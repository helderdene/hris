<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import OrgChartNode from './OrgChartNode.vue';

interface Department {
    id: number;
    name: string;
    code: string;
    status: 'active' | 'inactive';
    parent_id: number | null;
    department_head_id: number | null;
    department_head_name?: string | null;
    children?: Department[];
}

const props = defineProps<{
    departments: Department[];
    scale: number;
    translateX: number;
    translateY: number;
}>();

const emit = defineEmits<{
    (e: 'nodeClick', department: Department): void;
}>();

// Track expanded state for each department
const expandedNodes = ref<Set<number>>(new Set());

// Build tree structure from flat list
const rootDepartments = computed(() => {
    const deptMap = new Map<number, Department>();
    const roots: Department[] = [];

    // First pass: create map of all departments
    props.departments.forEach((dept) => {
        deptMap.set(dept.id, { ...dept, children: [] });
    });

    // Second pass: build tree structure
    props.departments.forEach((dept) => {
        const node = deptMap.get(dept.id)!;
        if (dept.parent_id === null) {
            roots.push(node);
        } else {
            const parent = deptMap.get(dept.parent_id);
            if (parent) {
                parent.children = parent.children || [];
                parent.children.push(node);
            } else {
                // Parent not found, treat as root
                roots.push(node);
            }
        }
    });

    // Sort children by name at each level
    function sortChildren(dept: Department): void {
        if (dept.children && dept.children.length > 0) {
            dept.children.sort((a, b) => a.name.localeCompare(b.name));
            dept.children.forEach(sortChildren);
        }
    }

    roots.sort((a, b) => a.name.localeCompare(b.name));
    roots.forEach(sortChildren);

    return roots;
});

// Initialize all nodes as expanded on first render
watch(
    () => props.departments,
    (newDepts) => {
        if (newDepts.length > 0 && expandedNodes.value.size === 0) {
            newDepts.forEach((dept) => {
                expandedNodes.value.add(dept.id);
            });
        }
    },
    { immediate: true },
);

function toggleNode(id: number) {
    if (expandedNodes.value.has(id)) {
        expandedNodes.value.delete(id);
    } else {
        expandedNodes.value.add(id);
    }
}

function isExpanded(id: number): boolean {
    return expandedNodes.value.has(id);
}
</script>

<template>
    <div
        class="org-chart-tree relative min-h-[400px] w-full overflow-hidden"
        data-testid="org-chart-tree"
    >
        <!-- Transform container for zoom/pan -->
        <div
            class="org-chart-content inline-flex origin-top-left justify-center transition-transform duration-200"
            :style="{
                transform: `translate(${translateX}px, ${translateY}px) scale(${scale})`,
                minWidth: '100%',
            }"
        >
            <!-- Render tree starting from roots -->
            <div
                v-if="rootDepartments.length > 0"
                class="flex flex-col items-center gap-8 py-8"
            >
                <!-- Root level nodes -->
                <div class="flex items-start justify-center gap-12">
                    <template v-for="root in rootDepartments" :key="root.id">
                        <div class="flex flex-col items-center">
                            <OrgChartNode
                                :department="root"
                                :is-expanded="isExpanded(root.id)"
                                @toggle="toggleNode"
                            />

                            <!-- Children container -->
                            <template
                                v-if="
                                    root.children &&
                                    root.children.length > 0 &&
                                    isExpanded(root.id)
                                "
                            >
                                <!-- Vertical connector from parent -->
                                <div
                                    class="h-8 w-px bg-slate-300 dark:bg-slate-600"
                                />

                                <!-- Horizontal connector bar -->
                                <div
                                    v-if="root.children.length > 1"
                                    class="relative h-px bg-slate-300 dark:bg-slate-600"
                                    :style="{
                                        width: `calc(${(root.children.length - 1) * 200}px + 100%)`,
                                        maxWidth: `${root.children.length * 232}px`,
                                    }"
                                />

                                <!-- Child nodes with recursive rendering -->
                                <div
                                    class="flex items-start justify-center gap-8"
                                >
                                    <template
                                        v-for="child in root.children"
                                        :key="child.id"
                                    >
                                        <div class="flex flex-col items-center">
                                            <!-- Vertical connector to child -->
                                            <div
                                                class="h-8 w-px bg-slate-300 dark:bg-slate-600"
                                            />

                                            <!-- Recursive child tree rendering -->
                                            <OrgChartTreeBranch
                                                :department="child"
                                                :is-expanded="
                                                    isExpanded(child.id)
                                                "
                                                :expanded-nodes="expandedNodes"
                                                @toggle="toggleNode"
                                            />
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Empty state -->
            <div
                v-else
                class="flex min-h-[400px] items-center justify-center text-slate-500 dark:text-slate-400"
            >
                <div class="text-center">
                    <svg
                        class="mx-auto h-12 w-12 text-slate-400"
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
                    <p class="mt-2 text-sm">No departments to display</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent, h, type PropType } from 'vue';

// Recursive component for rendering branch with children
const OrgChartTreeBranch = defineComponent({
    name: 'OrgChartTreeBranch',
    props: {
        department: {
            type: Object as PropType<Department>,
            required: true,
        },
        isExpanded: {
            type: Boolean,
            default: true,
        },
        expandedNodes: {
            type: Object as PropType<Set<number>>,
            required: true,
        },
    },
    emits: ['toggle'],
    setup(props, { emit }) {
        const hasChildren = computed(
            () =>
                props.department.children &&
                props.department.children.length > 0,
        );

        function isNodeExpanded(id: number): boolean {
            return props.expandedNodes.has(id);
        }

        return () => {
            const children = [];

            // Add the node
            children.push(
                h(OrgChartNode, {
                    department: props.department,
                    isExpanded: props.isExpanded,
                    onToggle: (id: number) => emit('toggle', id),
                }),
            );

            // Add children if expanded and they exist
            if (
                hasChildren.value &&
                props.isExpanded &&
                props.department.children
            ) {
                // Vertical connector
                children.push(
                    h('div', {
                        class: 'h-8 w-px bg-slate-300 dark:bg-slate-600',
                    }),
                );

                // Horizontal bar if multiple children
                if (props.department.children.length > 1) {
                    children.push(
                        h('div', {
                            class: 'h-px bg-slate-300 dark:bg-slate-600',
                            style: {
                                width: `calc(${(props.department.children.length - 1) * 200}px + 100%)`,
                                maxWidth: `${props.department.children.length * 232}px`,
                            },
                        }),
                    );
                }

                // Child nodes container
                const childNodes = props.department.children.map((child) =>
                    h(
                        'div',
                        {
                            key: child.id,
                            class: 'flex flex-col items-center',
                        },
                        [
                            h('div', {
                                class: 'h-8 w-px bg-slate-300 dark:bg-slate-600',
                            }),
                            h(OrgChartTreeBranch, {
                                department: child,
                                isExpanded: isNodeExpanded(child.id),
                                expandedNodes: props.expandedNodes,
                                onToggle: (id: number) => emit('toggle', id),
                            }),
                        ],
                    ),
                );

                children.push(
                    h(
                        'div',
                        { class: 'flex items-start justify-center gap-8' },
                        childNodes,
                    ),
                );
            }

            return h('div', { class: 'flex flex-col items-center' }, children);
        };
    },
});

export { OrgChartTreeBranch };
</script>
