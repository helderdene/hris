<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { computed, ref } from 'vue';

interface DepartmentTreeItem {
    id: number;
    name: string;
    code: string;
    status: string;
    parent_id: number | null;
    department_head_id: number | null;
    children?: DepartmentTreeItem[];
}

const props = defineProps<{
    department: DepartmentTreeItem;
    depth: number;
}>();

const emit = defineEmits<{
    (e: 'edit', department: DepartmentTreeItem): void;
    (e: 'add-child', parentId: number): void;
    (e: 'delete', department: DepartmentTreeItem): void;
}>();

const isExpanded = ref(true);

const hasChildren = computed(() => {
    return props.department.children && props.department.children.length > 0;
});

const indentStyle = computed(() => {
    return {
        paddingLeft: `${props.depth * 24}px`,
    };
});

/**
 * Get status badge styling
 */
function getStatusBadgeClasses(status: string): string {
    if (status === 'active') {
        return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300';
    }
    return 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
}

/**
 * Handle edit action
 */
function handleEdit() {
    emit('edit', props.department);
}

/**
 * Handle add child action
 */
function handleAddChild() {
    emit('add-child', props.department.id);
}

/**
 * Handle delete action
 */
function handleDelete() {
    emit('delete', props.department);
}
</script>

<template>
    <div
        class="department-tree-node"
        :data-test="`department-node-${department.id}`"
    >
        <Collapsible v-model:open="isExpanded">
            <!-- Department Row -->
            <div
                class="flex items-center gap-3 rounded-lg p-2 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"
                :style="indentStyle"
            >
                <!-- Expand/Collapse Toggle -->
                <CollapsibleTrigger v-if="hasChildren" as-child>
                    <button
                        class="flex h-6 w-6 items-center justify-center rounded text-slate-400 transition-colors hover:bg-slate-200 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                        :data-test="`expand-toggle-${department.id}`"
                    >
                        <svg
                            class="h-4 w-4 transition-transform"
                            :class="{ 'rotate-90': isExpanded }"
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8.25 4.5l7.5 7.5-7.5 7.5"
                            />
                        </svg>
                    </button>
                </CollapsibleTrigger>

                <!-- Spacer for nodes without children -->
                <div v-else class="h-6 w-6" />

                <!-- Parent Relationship Indicator -->
                <div
                    v-if="depth > 0"
                    class="flex items-center text-slate-400 dark:text-slate-500"
                >
                    <svg
                        class="h-4 w-4"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3"
                        />
                    </svg>
                </div>

                <!-- Department Icon -->
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                >
                    <svg
                        class="h-4 w-4"
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
                </div>

                <!-- Department Info -->
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <!-- Code Badge -->
                    <span
                        class="inline-flex items-center rounded bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                    >
                        {{ department.code }}
                    </span>

                    <!-- Name -->
                    <span
                        class="truncate font-medium text-slate-900 dark:text-slate-100"
                    >
                        {{ department.name }}
                    </span>

                    <!-- Status Badge -->
                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="getStatusBadgeClasses(department.status)"
                    >
                        {{
                            department.status === 'active'
                                ? 'Active'
                                : 'Inactive'
                        }}
                    </span>

                    <!-- Children Count -->
                    <span
                        v-if="hasChildren"
                        class="text-xs text-slate-500 dark:text-slate-400"
                    >
                        ({{ department.children?.length }}
                        {{
                            department.children?.length === 1
                                ? 'child'
                                : 'children'
                        }})
                    </span>
                </div>

                <!-- Actions Dropdown -->
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-8 w-8 p-0"
                            :data-test="`department-actions-${department.id}`"
                        >
                            <span class="sr-only">Open menu</span>
                            <svg
                                class="h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"
                                />
                            </svg>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            @click="handleEdit"
                            :data-test="`edit-department-${department.id}`"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                                />
                            </svg>
                            Edit
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            @click="handleAddChild"
                            :data-test="`add-child-${department.id}`"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M12 4.5v15m7.5-7.5h-15"
                                />
                            </svg>
                            Add Child
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-red-600 focus:text-red-600 dark:text-red-400 dark:focus:text-red-400"
                            @click="handleDelete"
                            :data-test="`delete-department-${department.id}`"
                        >
                            <svg
                                class="mr-2 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"
                                />
                            </svg>
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <!-- Children (Recursive) -->
            <CollapsibleContent v-if="hasChildren">
                <DepartmentTreeNode
                    v-for="child in department.children"
                    :key="child.id"
                    :department="child"
                    :depth="depth + 1"
                    @edit="$emit('edit', $event)"
                    @add-child="$emit('add-child', $event)"
                    @delete="$emit('delete', $event)"
                />
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
