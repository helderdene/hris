<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface NarrativeFeedback {
    strengths: string;
    areas_for_improvement: string;
    overall_comments: string;
    development_suggestions: string;
}

const props = defineProps<{
    modelValue: NarrativeFeedback;
    employeeName?: string;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: NarrativeFeedback): void;
    (e: 'change'): void;
}>();

function updateField(field: keyof NarrativeFeedback, value: string) {
    if (props.disabled) return;
    emit('update:modelValue', { ...props.modelValue, [field]: value });
    emit('change');
}
</script>

<template>
    <div class="space-y-6">
        <div>
            <Label for="strengths" class="mb-2 block">
                Strengths
            </Label>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                {{ employeeName ? `What are ${employeeName}'s key strengths?` : 'What are your key strengths?' }}
            </p>
            <Textarea
                id="strengths"
                :model-value="modelValue.strengths"
                :placeholder="employeeName ? `Describe ${employeeName}'s key strengths and accomplishments...` : 'Describe your key strengths and accomplishments...'"
                rows="4"
                :disabled="disabled"
                @update:model-value="updateField('strengths', $event)"
            />
        </div>

        <div>
            <Label for="areas_for_improvement" class="mb-2 block">
                Areas for Improvement
            </Label>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                {{ employeeName ? `What areas could ${employeeName} improve?` : 'What areas could you improve?' }}
            </p>
            <Textarea
                id="areas_for_improvement"
                :model-value="modelValue.areas_for_improvement"
                :placeholder="employeeName ? `Identify areas where ${employeeName} could develop further...` : 'Identify areas where you could develop further...'"
                rows="4"
                :disabled="disabled"
                @update:model-value="updateField('areas_for_improvement', $event)"
            />
        </div>

        <div>
            <Label for="overall_comments" class="mb-2 block">
                Overall Comments
            </Label>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                Any additional comments about overall performance.
            </p>
            <Textarea
                id="overall_comments"
                :model-value="modelValue.overall_comments"
                placeholder="Share any additional observations or feedback..."
                rows="4"
                :disabled="disabled"
                @update:model-value="updateField('overall_comments', $event)"
            />
        </div>

        <div>
            <Label for="development_suggestions" class="mb-2 block">
                Development Suggestions
            </Label>
            <p class="mb-2 text-xs text-slate-500 dark:text-slate-400">
                {{ employeeName ? `What training or development would help ${employeeName} grow?` : 'What training or development would help you grow?' }}
            </p>
            <Textarea
                id="development_suggestions"
                :model-value="modelValue.development_suggestions"
                placeholder="Suggest training, courses, or experiences that would support growth..."
                rows="4"
                :disabled="disabled"
                @update:model-value="updateField('development_suggestions', $event)"
            />
        </div>
    </div>
</template>
