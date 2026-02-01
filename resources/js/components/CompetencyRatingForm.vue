<script setup lang="ts">
import ProficiencyLevelBadge from '@/components/ProficiencyLevelBadge.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import type { ProficiencyLevel } from '@/types/competency';
import { computed, ref, watch } from 'vue';

interface Competency {
    id: number;
    name: string;
    code: string;
    description: string | null;
    category: string | null;
    category_label: string | null;
}

interface EvaluationItem {
    id: number | null;
    position_competency_id: number;
    performance_cycle_participant_id: number;
    competency: Competency;
    required_proficiency_level: number;
    required_proficiency_name: string | null;
    job_level: string;
    job_level_label: string;
    is_mandatory: boolean;
    weight: number;
    self_rating: number | null;
    self_comments: string | null;
    manager_rating: number | null;
    manager_comments: string | null;
    final_rating: number | null;
    evidence: string[];
    evaluated_at: string | null;
    is_complete: boolean;
}

const props = defineProps<{
    mode: 'self' | 'manager';
    evaluation: EvaluationItem;
    proficiencyLevels: ProficiencyLevel[];
    participantId: number;
}>();

const emit = defineEmits<{
    success: [];
    cancel: [];
}>();

const { primaryColor } = useTenant();

const selectedRating = ref<number | null>(
    props.mode === 'self' ? props.evaluation.self_rating : props.evaluation.manager_rating,
);
const comments = ref<string>(
    props.mode === 'self'
        ? props.evaluation.self_comments ?? ''
        : props.evaluation.manager_comments ?? '',
);
const finalRating = ref<number | null>(props.evaluation.final_rating);
const isSubmitting = ref(false);
const error = ref<string | null>(null);

const isManagerMode = computed(() => props.mode === 'manager');

const formTitle = computed(() =>
    props.mode === 'self' ? 'Self Assessment' : 'Manager Assessment',
);

watch(
    () => props.evaluation,
    (newEval) => {
        selectedRating.value =
            props.mode === 'self' ? newEval.self_rating : newEval.manager_rating;
        comments.value =
            props.mode === 'self'
                ? newEval.self_comments ?? ''
                : newEval.manager_comments ?? '';
        finalRating.value = newEval.final_rating;
    },
);

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function handleSubmit() {
    if (!selectedRating.value) {
        error.value = 'Please select a rating';
        return;
    }

    isSubmitting.value = true;
    error.value = null;

    try {
        let url: string;
        let body: Record<string, unknown>;

        if (props.evaluation.id) {
            // Update existing evaluation
            if (props.mode === 'self') {
                url = `/api/performance/competency-evaluations/${props.evaluation.id}/self-rating`;
                body = {
                    self_rating: selectedRating.value,
                    self_comments: comments.value || null,
                };
            } else {
                url = `/api/performance/competency-evaluations/${props.evaluation.id}/manager-rating`;
                body = {
                    manager_rating: selectedRating.value,
                    manager_comments: comments.value || null,
                    final_rating: finalRating.value,
                };
            }
        } else {
            // Create new evaluation
            url = `/api/performance/competency-evaluations`;
            body = {
                performance_cycle_participant_id: props.participantId,
                position_competency_id: props.evaluation.position_competency_id,
            };
            if (props.mode === 'self') {
                body.self_rating = selectedRating.value;
                body.self_comments = comments.value || null;
            } else {
                body.manager_rating = selectedRating.value;
                body.manager_comments = comments.value || null;
                body.final_rating = finalRating.value;
            }
        }

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        if (response.ok) {
            emit('success');
        } else {
            const data = await response.json();
            error.value = data.message || 'Failed to save rating';
        }
    } catch {
        error.value = 'An error occurred while saving';
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50">
        <h4 class="mb-4 font-medium text-slate-900 dark:text-slate-100">
            {{ formTitle }}
        </h4>

        <div v-if="error" class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">
            {{ error }}
        </div>

        <div class="space-y-4">
            <!-- Rating Selection -->
            <div>
                <Label class="mb-2 block">
                    {{ mode === 'self' ? 'Your Rating' : 'Manager Rating' }}
                    <span class="text-red-500">*</span>
                </Label>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="level in proficiencyLevels"
                        :key="level.id"
                        type="button"
                        class="group relative rounded-lg border-2 p-3 text-center transition-all hover:shadow-md"
                        :class="
                            selectedRating === level.level
                                ? 'border-blue-500 bg-blue-50 dark:border-blue-400 dark:bg-blue-900/30'
                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:hover:border-slate-500'
                        "
                        @click="selectedRating = level.level"
                    >
                        <ProficiencyLevelBadge
                            :level="level.level"
                            :name="level.name"
                            show-level
                        />
                        <!-- Tooltip on hover -->
                        <div
                            class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 hidden w-48 -translate-x-1/2 rounded-lg bg-slate-900 p-2 text-xs text-white opacity-0 shadow-lg transition-opacity group-hover:block group-hover:opacity-100 dark:bg-slate-700"
                        >
                            {{ level.description }}
                        </div>
                    </button>
                </div>
            </div>

            <!-- Comments -->
            <div>
                <Label :for="`comments-${evaluation.position_competency_id}`" class="mb-2 block">
                    {{ mode === 'self' ? 'Self Assessment Notes' : 'Manager Notes' }}
                </Label>
                <Textarea
                    :id="`comments-${evaluation.position_competency_id}`"
                    v-model="comments"
                    :placeholder="
                        mode === 'self'
                            ? 'Describe your performance and provide examples...'
                            : 'Provide feedback and observations...'
                    "
                    rows="3"
                />
            </div>

            <!-- Final Rating (Manager Only) -->
            <div v-if="isManagerMode">
                <Label class="mb-2 block">
                    Final Rating
                    <span class="text-xs text-slate-500 dark:text-slate-400">(Optional - set to complete evaluation)</span>
                </Label>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg border-2 border-dashed border-slate-300 px-3 py-2 text-sm text-slate-500 transition-all hover:border-slate-400 hover:text-slate-700 dark:border-slate-600 dark:text-slate-400 dark:hover:border-slate-500 dark:hover:text-slate-300"
                        :class="finalRating === null ? 'bg-slate-100 dark:bg-slate-800' : ''"
                        @click="finalRating = null"
                    >
                        Not Set
                    </button>
                    <button
                        v-for="level in proficiencyLevels"
                        :key="level.id"
                        type="button"
                        class="rounded-lg border-2 p-2 text-center transition-all hover:shadow-sm"
                        :class="
                            finalRating === level.level
                                ? 'border-emerald-500 bg-emerald-50 dark:border-emerald-400 dark:bg-emerald-900/30'
                                : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:hover:border-slate-500'
                        "
                        @click="finalRating = level.level"
                    >
                        <ProficiencyLevelBadge
                            :level="level.level"
                            show-level
                            size="sm"
                        />
                    </button>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-2 pt-2">
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="isSubmitting"
                    @click="emit('cancel')"
                >
                    Cancel
                </Button>
                <Button
                    size="sm"
                    :disabled="isSubmitting || !selectedRating"
                    :style="{ backgroundColor: primaryColor }"
                    @click="handleSubmit"
                >
                    <svg
                        v-if="isSubmitting"
                        class="mr-2 h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    {{ isSubmitting ? 'Saving...' : 'Save Rating' }}
                </Button>
            </div>
        </div>
    </div>
</template>
