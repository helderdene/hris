<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { computed } from 'vue';

interface Competency {
    id: number;
    required_proficiency_level: number;
    is_mandatory: boolean;
    weight: number;
    competency: {
        id: number;
        name: string;
        code: string;
        description: string;
        category: string;
        category_label: string;
    };
}

interface CompetencyRating {
    rating: number | null;
    comments: string;
}

const props = defineProps<{
    competencies: Competency[];
    modelValue: Record<number, CompetencyRating>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Record<number, CompetencyRating>): void;
    (e: 'change'): void;
}>();

// Group competencies by category
const competenciesByCategory = computed(() => {
    const grouped: Record<string, Competency[]> = {};
    for (const c of props.competencies) {
        const category = c.competency.category_label || 'Other';
        if (!grouped[category]) {
            grouped[category] = [];
        }
        grouped[category].push(c);
    }
    return grouped;
});

function setRating(competencyId: number, rating: number) {
    if (props.disabled) return;
    const updated = { ...props.modelValue };
    updated[competencyId] = { ...updated[competencyId], rating };
    emit('update:modelValue', updated);
    emit('change');
}

function updateComments(competencyId: number, comments: string) {
    if (props.disabled) return;
    const updated = { ...props.modelValue };
    updated[competencyId] = { ...updated[competencyId], comments };
    emit('update:modelValue', updated);
    emit('change');
}

const ratingLabels = ['', 'Needs Improvement', 'Below Expectations', 'Meets Expectations', 'Exceeds Expectations', 'Exceptional'];
</script>

<template>
    <div class="space-y-6">
        <div v-for="(categoryCompetencies, category) in competenciesByCategory" :key="category">
            <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">
                {{ category }}
            </h3>
            <div class="space-y-4">
                <div
                    v-for="competency in categoryCompetencies"
                    :key="competency.id"
                    class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                >
                    <div class="mb-4">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-medium text-slate-900 dark:text-slate-100">
                                    {{ competency.competency.name }}
                                    <span
                                        v-if="competency.is_mandatory"
                                        class="ml-2 text-xs text-red-500"
                                    >*</span>
                                </h4>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ competency.competency.description }}
                                </p>
                            </div>
                            <div class="ml-2 flex flex-col items-end gap-1">
                                <span class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    Required: Level {{ competency.required_proficiency_level }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    Weight: {{ competency.weight }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Rating -->
                    <div class="mb-3">
                        <Label class="mb-2 block text-sm">Your Rating</Label>
                        <div class="flex gap-2">
                            <button
                                v-for="n in 5"
                                :key="n"
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-lg border-2 text-sm font-medium transition-colors"
                                :class="modelValue[competency.id]?.rating === n
                                    ? 'border-blue-500 bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-300'
                                    : 'border-slate-200 text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:text-slate-400'"
                                :disabled="disabled"
                                @click="setRating(competency.id, n)"
                            >
                                {{ n }}
                            </button>
                        </div>
                        <p v-if="modelValue[competency.id]?.rating" class="mt-1 text-xs text-slate-500">
                            {{ ratingLabels[modelValue[competency.id].rating!] }}
                        </p>
                    </div>

                    <!-- Comments -->
                    <div>
                        <Label class="mb-2 block text-sm">Comments (Optional)</Label>
                        <Textarea
                            :model-value="modelValue[competency.id]?.comments || ''"
                            placeholder="Add any comments or examples..."
                            rows="2"
                            :disabled="disabled"
                            @update:model-value="updateComments(competency.id, $event)"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="competencies.length === 0" class="py-8 text-center text-sm text-slate-500">
            No competencies defined for this position.
        </div>
    </div>
</template>
