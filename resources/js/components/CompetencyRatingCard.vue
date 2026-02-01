<script setup lang="ts">
import CompetencyRatingForm from '@/components/CompetencyRatingForm.vue';
import ProficiencyLevelBadge from '@/components/ProficiencyLevelBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTenant } from '@/composables/useTenant';
import type { ProficiencyLevel } from '@/types/competency';
import { computed, ref } from 'vue';

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
    evaluation: EvaluationItem;
    proficiencyLevels: ProficiencyLevel[];
    participantId: number;
}>();

const emit = defineEmits<{
    updated: [];
}>();

const { tenant } = useTenant();

const isExpanded = ref(false);
const editMode = ref<'self' | 'manager' | null>(null);

const ratingGap = computed(() => {
    if (props.evaluation.self_rating === null || props.evaluation.manager_rating === null) {
        return null;
    }
    return props.evaluation.self_rating - props.evaluation.manager_rating;
});

const proficiencyGap = computed(() => {
    const finalRating = props.evaluation.final_rating ?? props.evaluation.manager_rating ?? props.evaluation.self_rating;
    if (finalRating === null) return null;
    return finalRating - props.evaluation.required_proficiency_level;
});

const completionStatus = computed(() => {
    if (props.evaluation.is_complete) return 'completed';
    if (props.evaluation.manager_rating !== null) return 'manager_rated';
    if (props.evaluation.self_rating !== null) return 'self_rated';
    return 'pending';
});

const statusBadge = computed(() => {
    switch (completionStatus.value) {
        case 'completed':
            return {
                label: 'Completed',
                classes: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
            };
        case 'manager_rated':
            return {
                label: 'Manager Rated',
                classes: 'bg-violet-100 text-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
            };
        case 'self_rated':
            return {
                label: 'Self Rated',
                classes: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
            };
        default:
            return {
                label: 'Pending',
                classes: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300',
            };
    }
});

function handleFormSuccess() {
    editMode.value = null;
    emit('updated');
}

function handleFormCancel() {
    editMode.value = null;
}
</script>

<template>
    <div class="px-6 py-4">
        <Collapsible v-model:open="isExpanded">
            <!-- Main Row -->
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h4 class="font-medium text-slate-900 dark:text-slate-100">
                            {{ evaluation.competency.name }}
                        </h4>
                        <span
                            v-if="evaluation.is_mandatory"
                            class="inline-flex items-center rounded bg-red-50 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-300"
                        >
                            Required
                        </span>
                        <span
                            class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-medium"
                            :class="statusBadge.classes"
                        >
                            {{ statusBadge.label }}
                        </span>
                    </div>
                    <p
                        v-if="evaluation.competency.description"
                        class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{ evaluation.competency.description }}
                    </p>
                    <div class="mt-2 flex flex-wrap items-center gap-4 text-sm">
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-500 dark:text-slate-400">Required:</span>
                            <ProficiencyLevelBadge
                                :level="evaluation.required_proficiency_level"
                                :name="evaluation.required_proficiency_name ?? undefined"
                                show-level
                                size="sm"
                            />
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-slate-500 dark:text-slate-400">Weight:</span>
                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                {{ evaluation.weight }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Ratings Summary -->
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-4">
                        <!-- Self Rating -->
                        <div class="text-center">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Self</p>
                            <ProficiencyLevelBadge
                                v-if="evaluation.self_rating"
                                :level="evaluation.self_rating"
                                show-level
                            />
                            <span
                                v-else
                                class="inline-flex h-6 items-center text-sm text-slate-400"
                            >
                                -
                            </span>
                        </div>

                        <!-- Manager Rating -->
                        <div class="text-center">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Manager</p>
                            <ProficiencyLevelBadge
                                v-if="evaluation.manager_rating"
                                :level="evaluation.manager_rating"
                                show-level
                            />
                            <span
                                v-else
                                class="inline-flex h-6 items-center text-sm text-slate-400"
                            >
                                -
                            </span>
                        </div>

                        <!-- Final Rating -->
                        <div class="text-center">
                            <p class="text-xs text-slate-500 dark:text-slate-400">Final</p>
                            <ProficiencyLevelBadge
                                v-if="evaluation.final_rating"
                                :level="evaluation.final_rating"
                                show-level
                            />
                            <span
                                v-else
                                class="inline-flex h-6 items-center text-sm text-slate-400"
                            >
                                -
                            </span>
                        </div>
                    </div>

                    <CollapsibleTrigger as-child>
                        <Button variant="ghost" size="sm" class="h-8 w-8 p-0">
                            <svg
                                class="h-4 w-4 transition-transform"
                                :class="{ 'rotate-180': isExpanded }"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="m19.5 8.25-7.5 7.5-7.5-7.5"
                                />
                            </svg>
                        </Button>
                    </CollapsibleTrigger>
                </div>
            </div>

            <!-- Expanded Content -->
            <CollapsibleContent>
                <div class="mt-4 border-t border-slate-200 pt-4 dark:border-slate-700">
                    <!-- Gap Analysis -->
                    <div
                        v-if="ratingGap !== null || proficiencyGap !== null"
                        class="mb-4 flex flex-wrap gap-4"
                    >
                        <div
                            v-if="ratingGap !== null"
                            class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800"
                        >
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Self vs Manager Gap
                            </p>
                            <p
                                class="font-semibold"
                                :class="
                                    ratingGap > 0
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : ratingGap < 0
                                          ? 'text-blue-600 dark:text-blue-400'
                                          : 'text-emerald-600 dark:text-emerald-400'
                                "
                            >
                                {{ ratingGap > 0 ? '+' : '' }}{{ ratingGap }}
                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400">
                                    {{ ratingGap > 0 ? '(Self rated higher)' : ratingGap < 0 ? '(Manager rated higher)' : '(Aligned)' }}
                                </span>
                            </p>
                        </div>
                        <div
                            v-if="proficiencyGap !== null"
                            class="rounded-lg bg-slate-50 px-3 py-2 dark:bg-slate-800"
                        >
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                vs Required Level
                            </p>
                            <p
                                class="font-semibold"
                                :class="
                                    proficiencyGap >= 0
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-red-600 dark:text-red-400'
                                "
                            >
                                {{ proficiencyGap >= 0 ? '+' : '' }}{{ proficiencyGap }}
                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400">
                                    {{ proficiencyGap >= 0 ? '(Meets/Exceeds)' : '(Below required)' }}
                                </span>
                            </p>
                        </div>
                    </div>

                    <!-- Comments Display -->
                    <div
                        v-if="(evaluation.self_comments || evaluation.manager_comments) && editMode === null"
                        class="mb-4 grid gap-4 md:grid-cols-2"
                    >
                        <div v-if="evaluation.self_comments">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Self Assessment Comments
                            </p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                {{ evaluation.self_comments }}
                            </p>
                        </div>
                        <div v-if="evaluation.manager_comments">
                            <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                Manager Comments
                            </p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-300">
                                {{ evaluation.manager_comments }}
                            </p>
                        </div>
                    </div>

                    <!-- Rating Forms -->
                    <div v-if="editMode === 'self'">
                        <CompetencyRatingForm
                            mode="self"
                            :evaluation="evaluation"
                            :proficiency-levels="proficiencyLevels"
                            :participant-id="participantId"
                            @success="handleFormSuccess"
                            @cancel="handleFormCancel"
                        />
                    </div>
                    <div v-else-if="editMode === 'manager'">
                        <CompetencyRatingForm
                            mode="manager"
                            :evaluation="evaluation"
                            :proficiency-levels="proficiencyLevels"
                            :participant-id="participantId"
                            @success="handleFormSuccess"
                            @cancel="handleFormCancel"
                        />
                    </div>
                    <div v-else class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="editMode = 'self'"
                        >
                            <svg
                                class="mr-1.5 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                                />
                            </svg>
                            {{ evaluation.self_rating ? 'Edit Self Rating' : 'Add Self Rating' }}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="editMode = 'manager'"
                        >
                            <svg
                                class="mr-1.5 h-4 w-4"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z"
                                />
                            </svg>
                            {{ evaluation.manager_rating ? 'Edit Manager Rating' : 'Add Manager Rating' }}
                        </Button>
                    </div>
                </div>
            </CollapsibleContent>
        </Collapsible>
    </div>
</template>
