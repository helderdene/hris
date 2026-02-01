<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    interviewId: number;
}>();

const feedback = ref('');
const rating = ref(0);
const isProcessing = ref(false);
const submitted = ref(false);
const error = ref('');

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

async function submitFeedback() {
    if (isProcessing.value || !feedback.value || rating.value === 0) {
        return;
    }

    isProcessing.value = true;
    error.value = '';

    try {
        const response = await fetch(`/api/interviews/${props.interviewId}/feedback`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ feedback: feedback.value, rating: rating.value }),
        });

        if (response.ok) {
            submitted.value = true;
            router.reload();
        } else if (response.status === 403) {
            error.value = 'You are not a panelist for this interview.';
        } else {
            error.value = 'Failed to submit feedback.';
        }
    } catch {
        error.value = 'An error occurred.';
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-900">
        <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Submit Feedback</h2>

        <div v-if="submitted" class="text-sm text-green-600 dark:text-green-400">
            Feedback submitted successfully.
        </div>

        <form v-else @submit.prevent="submitFeedback" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Rating *</label>
                <div class="flex gap-1">
                    <button
                        v-for="star in 5"
                        :key="star"
                        type="button"
                        @click="rating = star"
                        class="text-2xl transition-colors"
                        :class="star <= rating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600'"
                    >
                        &#9733;
                    </button>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Feedback *</label>
                <textarea v-model="feedback" rows="4" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Share your assessment of the candidate..."></textarea>
            </div>

            <p v-if="error" class="text-sm text-red-500">{{ error }}</p>

            <Button type="submit" :disabled="isProcessing || !feedback || rating === 0">
                {{ isProcessing ? 'Submitting...' : 'Submit Feedback' }}
            </Button>
        </form>
    </div>
</template>
