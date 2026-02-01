<script setup lang="ts">
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Bold,
    Italic,
    List,
    ListOrdered,
    Heading1,
    Heading2,
    Undo,
    Redo,
    Code,
    Quote,
} from 'lucide-vue-next';

const props = defineProps<{
    modelValue: string;
    placeholder?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Placeholder.configure({
            placeholder: props.placeholder ?? 'Start writing...',
        }),
    ],
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (editor.value && editor.value.getHTML() !== value) {
            editor.value.commands.setContent(value, false);
        }
    },
);

function insertText(text: string): void {
    editor.value?.chain().focus().insertContent(text).run();
}

defineExpose({ insertText });
</script>

<template>
    <div class="rounded-md border border-input bg-background">
        <div
            v-if="editor"
            class="flex flex-wrap items-center gap-1 border-b border-input px-2 py-1.5"
        >
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('bold') }"
                @click="editor.chain().focus().toggleBold().run()"
            >
                <Bold class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('italic') }"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                <Italic class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('heading', { level: 1 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 1 }).run()"
            >
                <Heading1 class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('heading', { level: 2 }) }"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('bulletList') }"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                <List class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('orderedList') }"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('blockquote') }"
                @click="editor.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :class="{ 'bg-accent': editor.isActive('codeBlock') }"
                @click="editor.chain().focus().toggleCodeBlock().run()"
            >
                <Code class="h-4 w-4" />
            </Button>

            <div class="mx-1 h-5 w-px bg-border" />

            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :disabled="!editor.can().undo()"
                @click="editor.chain().focus().undo().run()"
            >
                <Undo class="h-4 w-4" />
            </Button>
            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="h-7 w-7 p-0"
                :disabled="!editor.can().redo()"
                @click="editor.chain().focus().redo().run()"
            >
                <Redo class="h-4 w-4" />
            </Button>

            <slot name="toolbar-extra" />
        </div>

        <EditorContent
            :editor="editor"
            class="prose prose-sm max-w-none px-3 py-2 focus-within:outline-none [&_.ProseMirror]:min-h-[200px] [&_.ProseMirror]:outline-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:text-muted-foreground [&_.ProseMirror_p.is-editor-empty:first-child::before]:content-[attr(data-placeholder)] [&_.ProseMirror_p.is-editor-empty:first-child::before]:float-left [&_.ProseMirror_p.is-editor-empty:first-child::before]:pointer-events-none [&_.ProseMirror_p.is-editor-empty:first-child::before]:h-0"
        />
    </div>
</template>
