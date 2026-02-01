<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { computed } from 'vue';

interface EnumOption {
    value: string;
    label: string;
    description?: string;
}

interface KeyResult {
    id?: number;
    title: string;
    description: string;
    metric_type: string;
    metric_unit: string;
    target_value: number;
    starting_value: number;
    weight: number;
}

const props = defineProps<{
    keyResult: KeyResult;
    index: number;
    metricTypes: EnumOption[];
}>();

const emit = defineEmits<{
    update: [keyResult: KeyResult];
    remove: [];
}>();

function updateField<K extends keyof KeyResult>(field: K, value: KeyResult[K]) {
    emit('update', { ...props.keyResult, [field]: value });
}

const showUnitField = computed(() => {
    return ['number', 'currency'].includes(props.keyResult.metric_type);
});

const unitPlaceholder = computed(() => {
    if (props.keyResult.metric_type === 'currency') return 'e.g., USD, EUR';
    return 'e.g., units, hours, tasks';
});
</script>

<template>
    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
        <div class="mb-3 flex items-center justify-between">
            <span class="text-sm font-medium text-slate-500 dark:text-slate-400">
                Key Result {{ index + 1 }}
            </span>
            <Button type="button" variant="ghost" size="sm" @click="emit('remove')">
                <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </Button>
        </div>

        <div class="space-y-3">
            <div>
                <Label :for="`kr-title-${index}`">Title</Label>
                <Input
                    :id="`kr-title-${index}`"
                    :model-value="keyResult.title"
                    @update:model-value="(val) => updateField('title', val)"
                    type="text"
                    placeholder="e.g., Increase monthly revenue"
                />
            </div>

            <div>
                <Label :for="`kr-description-${index}`">Description (optional)</Label>
                <Input
                    :id="`kr-description-${index}`"
                    :model-value="keyResult.description"
                    @update:model-value="(val) => updateField('description', val)"
                    type="text"
                    placeholder="Additional details about this key result"
                />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <Label :for="`kr-metric-type-${index}`">Metric Type</Label>
                    <Select
                        :model-value="keyResult.metric_type"
                        @update:model-value="(val) => updateField('metric_type', val)"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select metric type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="metricType in metricTypes"
                                :key="metricType.value"
                                :value="metricType.value"
                            >
                                {{ metricType.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="showUnitField">
                    <Label :for="`kr-unit-${index}`">Unit</Label>
                    <Input
                        :id="`kr-unit-${index}`"
                        :model-value="keyResult.metric_unit"
                        @update:model-value="(val) => updateField('metric_unit', val)"
                        type="text"
                        :placeholder="unitPlaceholder"
                    />
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <Label :for="`kr-start-${index}`">Starting Value</Label>
                    <Input
                        :id="`kr-start-${index}`"
                        :model-value="keyResult.starting_value"
                        @update:model-value="(val) => updateField('starting_value', Number(val))"
                        type="number"
                        step="any"
                    />
                </div>

                <div>
                    <Label :for="`kr-target-${index}`">Target Value</Label>
                    <Input
                        :id="`kr-target-${index}`"
                        :model-value="keyResult.target_value"
                        @update:model-value="(val) => updateField('target_value', Number(val))"
                        type="number"
                        step="any"
                    />
                </div>

                <div>
                    <Label :for="`kr-weight-${index}`">Weight</Label>
                    <Input
                        :id="`kr-weight-${index}`"
                        :model-value="keyResult.weight"
                        @update:model-value="(val) => updateField('weight', Number(val))"
                        type="number"
                        step="0.1"
                        min="0.1"
                        max="10"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
