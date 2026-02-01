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
import { Textarea } from '@/components/ui/textarea';
import { useTenant } from '@/composables/useTenant';
import { ref, watch } from 'vue';

interface CalibrationData {
    final_competency_score: number | null;
    final_kpi_score: number | null;
    final_overall_score: number | null;
    final_rating: string | null;
    calibration_notes: string;
}

interface Props {
    modelValue: CalibrationData;
    suggestedCompetencyScore?: number | null;
    suggestedKpiScore?: number | null;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    suggestedCompetencyScore: null,
    suggestedKpiScore: null,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: CalibrationData): void;
    (e: 'submit'): void;
    (e: 'recalculate'): void;
}>();

const { primaryColor } = useTenant();

const localData = ref<CalibrationData>({ ...props.modelValue });

watch(() => props.modelValue, (newVal) => {
    localData.value = { ...newVal };
}, { deep: true });

function updateField<K extends keyof CalibrationData>(field: K, value: CalibrationData[K]) {
    localData.value[field] = value;
    emit('update:modelValue', { ...localData.value });
}

function useSuggestedScores() {
    if (props.suggestedCompetencyScore !== null) {
        updateField('final_competency_score', props.suggestedCompetencyScore);
    }
    if (props.suggestedKpiScore !== null) {
        updateField('final_kpi_score', props.suggestedKpiScore);
    }
    calculateOverall();
}

function calculateOverall() {
    const competency = localData.value.final_competency_score;
    const kpi = localData.value.final_kpi_score;

    if (competency !== null && kpi !== null) {
        // 60% competency, 40% KPI achievement (converted to 5-point scale)
        const kpiOn5Scale = (kpi / 100) * 5;
        const overall = (competency * 0.6) + (kpiOn5Scale * 0.4);
        updateField('final_overall_score', Math.round(overall * 100) / 100);

        // Auto-suggest rating based on overall score
        if (overall >= 4.5) {
            updateField('final_rating', 'exceptional');
        } else if (overall >= 3.5) {
            updateField('final_rating', 'exceeds_expectations');
        } else if (overall >= 2.5) {
            updateField('final_rating', 'meets_expectations');
        } else if (overall >= 1.5) {
            updateField('final_rating', 'below_expectations');
        } else {
            updateField('final_rating', 'needs_improvement');
        }
    }
}

const ratingOptions = [
    { value: 'exceptional', label: 'Exceptional' },
    { value: 'exceeds_expectations', label: 'Exceeds Expectations' },
    { value: 'meets_expectations', label: 'Meets Expectations' },
    { value: 'below_expectations', label: 'Below Expectations' },
    { value: 'needs_improvement', label: 'Needs Improvement' },
];
</script>

<template>
    <div class="space-y-6">
        <!-- Suggested Scores -->
        <div v-if="suggestedCompetencyScore !== null || suggestedKpiScore !== null" class="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-blue-700 dark:text-blue-300">Suggested Scores</p>
                    <p class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                        Based on aggregated reviewer responses
                    </p>
                    <div class="mt-2 flex gap-4 text-sm text-blue-700 dark:text-blue-300">
                        <span v-if="suggestedCompetencyScore !== null">
                            Competency: <strong>{{ suggestedCompetencyScore.toFixed(2) }}</strong>
                        </span>
                        <span v-if="suggestedKpiScore !== null">
                            KPI: <strong>{{ suggestedKpiScore.toFixed(0) }}%</strong>
                        </span>
                    </div>
                </div>
                <Button variant="outline" size="sm" :disabled="disabled" @click="useSuggestedScores">
                    Use Suggested
                </Button>
            </div>
        </div>

        <!-- Score Inputs -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <Label for="final_competency_score" class="mb-2 block">
                    Final Competency Score
                </Label>
                <Input
                    id="final_competency_score"
                    type="number"
                    min="1"
                    max="5"
                    step="0.01"
                    :model-value="localData.final_competency_score ?? ''"
                    :disabled="disabled"
                    @update:model-value="updateField('final_competency_score', $event ? Number($event) : null)"
                    @blur="calculateOverall"
                />
                <p class="mt-1 text-xs text-slate-500">Scale: 1-5</p>
            </div>

            <div>
                <Label for="final_kpi_score" class="mb-2 block">
                    Final KPI Score
                </Label>
                <Input
                    id="final_kpi_score"
                    type="number"
                    min="0"
                    max="200"
                    step="0.1"
                    :model-value="localData.final_kpi_score ?? ''"
                    :disabled="disabled"
                    @update:model-value="updateField('final_kpi_score', $event ? Number($event) : null)"
                    @blur="calculateOverall"
                />
                <p class="mt-1 text-xs text-slate-500">Percentage: 0-200%</p>
            </div>

            <div>
                <Label for="final_overall_score" class="mb-2 block">
                    Final Overall Score
                </Label>
                <Input
                    id="final_overall_score"
                    type="number"
                    min="1"
                    max="5"
                    step="0.01"
                    :model-value="localData.final_overall_score ?? ''"
                    :disabled="disabled"
                    @update:model-value="updateField('final_overall_score', $event ? Number($event) : null)"
                />
                <p class="mt-1 text-xs text-slate-500">Scale: 1-5</p>
            </div>
        </div>

        <!-- Final Rating -->
        <div>
            <Label for="final_rating" class="mb-2 block">
                Final Rating
            </Label>
            <Select
                :model-value="localData.final_rating || ''"
                :disabled="disabled"
                @update:model-value="updateField('final_rating', $event || null)"
            >
                <SelectTrigger class="w-full sm:w-64">
                    <SelectValue placeholder="Select a rating" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem v-for="option in ratingOptions" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <!-- Calibration Notes -->
        <div>
            <Label for="calibration_notes" class="mb-2 block">
                Calibration Notes
            </Label>
            <Textarea
                id="calibration_notes"
                :model-value="localData.calibration_notes"
                placeholder="Add any notes about the calibration decision..."
                rows="4"
                :disabled="disabled"
                @update:model-value="updateField('calibration_notes', $event)"
            />
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between border-t border-slate-200 pt-4 dark:border-slate-700">
            <Button variant="outline" :disabled="disabled" @click="emit('recalculate')">
                Recalculate Averages
            </Button>
            <Button
                :style="{ backgroundColor: primaryColor }"
                :disabled="disabled || !localData.final_rating"
                @click="emit('submit')"
            >
                Save Calibration
            </Button>
        </div>
    </div>
</template>
