<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Comment {
    id: number;
    user: {
        id: number;
        name: string;
        initials: string;
    };
    comment: string;
    is_private: boolean;
    created_at: string;
    created_at_formatted: string;
}

const props = defineProps<{
    goalId: number;
    comments: Comment[];
    canAddPrivateComments?: boolean;
}>();

const emit = defineEmits<{
    commentAdded: [];
}>();

const showForm = ref(false);

const form = useForm({
    comment: '',
    is_private: false,
});

function handleSubmit() {
    form.post(`/api/performance/goals/${props.goalId}/comments`, {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
            emit('commentAdded');
        },
    });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-slate-900 dark:text-slate-100">
                Comments ({{ comments.length }})
            </h3>
            <Button
                v-if="!showForm"
                variant="outline"
                size="sm"
                @click="showForm = true"
            >
                Add Comment
            </Button>
        </div>

        <!-- Comment Form -->
        <div v-if="showForm" class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
            <form @submit.prevent="handleSubmit" class="space-y-3">
                <Textarea
                    v-model="form.comment"
                    placeholder="Write your comment..."
                    rows="3"
                    :class="{ 'border-red-500': form.errors.comment }"
                />
                <p v-if="form.errors.comment" class="text-sm text-red-600">
                    {{ form.errors.comment }}
                </p>

                <div v-if="canAddPrivateComments" class="flex items-center gap-2">
                    <input
                        id="is_private"
                        v-model="form.is_private"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                    />
                    <label for="is_private" class="text-sm text-slate-600 dark:text-slate-400">
                        Private (only visible to managers)
                    </label>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <Button type="button" variant="ghost" size="sm" @click="showForm = false">
                        Cancel
                    </Button>
                    <Button type="submit" size="sm" :disabled="form.processing">
                        {{ form.processing ? 'Posting...' : 'Post Comment' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- Comments List -->
        <div v-if="comments.length === 0" class="py-6 text-center text-sm text-slate-500 dark:text-slate-400">
            No comments yet.
        </div>
        <div v-else class="space-y-4">
            <div
                v-for="comment in comments"
                :key="comment.id"
                class="flex gap-3"
            >
                <Avatar class="h-8 w-8">
                    <AvatarFallback class="text-xs">
                        {{ comment.user.initials }}
                    </AvatarFallback>
                </Avatar>
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-medium text-slate-900 dark:text-slate-100">
                            {{ comment.user.name }}
                        </span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">
                            {{ comment.created_at_formatted }}
                        </span>
                        <span
                            v-if="comment.is_private"
                            class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                        >
                            Private
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-700 dark:text-slate-300 whitespace-pre-wrap">
                        {{ comment.comment }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
