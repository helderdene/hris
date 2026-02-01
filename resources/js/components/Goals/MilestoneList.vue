<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import MilestoneCheckbox from '@/components/Goals/MilestoneCheckbox.vue';

interface Milestone {
    id?: number;
    title: string;
    description: string;
    due_date: string;
    is_completed?: boolean;
    completed_at?: string;
}

const props = withDefaults(
    defineProps<{
        milestones: Milestone[];
        editable?: boolean;
    }>(),
    {
        editable: false,
    },
);

const emit = defineEmits<{
    update: [index: number, milestone: Milestone];
    remove: [index: number];
    toggle: [index: number];
}>();

function updateField<K extends keyof Milestone>(index: number, field: K, value: Milestone[K]) {
    emit('update', index, { ...props.milestones[index], [field]: value });
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(milestone, index) in milestones"
            :key="index"
            class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
        >
            <template v-if="editable">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        Milestone {{ index + 1 }}
                    </span>
                    <Button type="button" variant="ghost" size="sm" @click="emit('remove', index)">
                        <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </Button>
                </div>
                <div class="space-y-2">
                    <Input
                        :model-value="milestone.title"
                        @update:model-value="(val) => updateField(index, 'title', val)"
                        type="text"
                        placeholder="Milestone title"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <Input
                            :model-value="milestone.description"
                            @update:model-value="(val) => updateField(index, 'description', val)"
                            type="text"
                            placeholder="Description (optional)"
                        />
                        <Input
                            :model-value="milestone.due_date"
                            @update:model-value="(val) => updateField(index, 'due_date', val)"
                            type="date"
                        />
                    </div>
                </div>
            </template>
            <template v-else>
                <MilestoneCheckbox
                    :milestone="milestone"
                    :index="index"
                    @toggle="emit('toggle', index)"
                />
            </template>
        </div>
    </div>
</template>
