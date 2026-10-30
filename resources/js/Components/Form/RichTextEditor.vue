<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Placeholder from '@tiptap/extension-placeholder';
import {
    ArrowUturnLeftIcon,
    ArrowUturnRightIcon,
    LinkIcon,
    ListBulletIcon,
    NumberedListIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Опишите задачу так, чтобы её можно было выполнить без догадок…' },
    minHeight: { type: String, default: '190px' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue || '',
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: { openOnClick: false, autolink: true },
        }),
        Placeholder.configure({ placeholder: props.placeholder }),
    ],
    editorProps: {
        attributes: {
            class: 'focus:outline-none',
        },
    },
    onUpdate: ({ editor: instance }) => {
        const html = instance.getHTML();
        emit('update:modelValue', html === '<p></p>' ? '' : html);
    },
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value) return;
        if (value !== editor.value.getHTML()) {
            editor.value.commands.setContent(value || '', false);
        }
    },
);

onBeforeUnmount(() => editor.value?.destroy());

function setLink() {
    const previous = editor.value?.getAttributes('link').href || '';
    const url = window.prompt('Ссылка', previous);

    if (url === null) return;

    if (url === '') {
        editor.value?.chain().focus().unsetLink().run();

        return;
    }

    editor.value?.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
}

const tools = [
    { title: 'Жирный', label: 'B', action: () => editor.value?.chain().focus().toggleBold().run(), active: () => editor.value?.isActive('bold'), class: 'font-bold' },
    { title: 'Курсив', label: 'I', action: () => editor.value?.chain().focus().toggleItalic().run(), active: () => editor.value?.isActive('italic'), class: 'italic' },
    { title: 'Подчёркнутый', label: 'U', action: () => editor.value?.chain().focus().toggleUnderline().run(), active: () => editor.value?.isActive('underline'), class: 'underline' },
    { title: 'Зачёркнутый', label: 'S', action: () => editor.value?.chain().focus().toggleStrike().run(), active: () => editor.value?.isActive('strike'), class: 'line-through' },
    { title: 'Заголовок', label: 'H2', action: () => editor.value?.chain().focus().toggleHeading({ level: 2 }).run(), active: () => editor.value?.isActive('heading', { level: 2 }) },
    { title: 'Подзаголовок', label: 'H3', action: () => editor.value?.chain().focus().toggleHeading({ level: 3 }).run(), active: () => editor.value?.isActive('heading', { level: 3 }) },
];
</script>

<template>
    <div class="rich-editor overflow-hidden rounded-[18px] border border-line bg-white/70 transition focus-within:border-primary-300 focus-within:ring-2 focus-within:ring-primary-500/10">
        <div class="flex flex-wrap items-center gap-0.5 border-b border-line/80 bg-surface-2/50 px-2 py-1.5">
            <button
                v-for="tool in tools"
                :key="tool.title"
                type="button"
                :title="tool.title"
                :class="[
                    'flex h-7 min-w-7 items-center justify-center rounded-lg px-1.5 text-[11px] text-ink-soft transition hover:bg-ink/[0.06]',
                    tool.class,
                    tool.active() ? 'bg-primary-soft text-primary-800' : '',
                ]"
                @click="tool.action"
            >
                {{ tool.label }}
            </button>
            <span class="mx-1 h-4 w-px bg-line"></span>
            <button type="button" title="Список" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-soft hover:bg-ink/[0.06]" @click="editor?.chain().focus().toggleBulletList().run()">
                <ListBulletIcon class="h-4 w-4" />
            </button>
            <button type="button" title="Нумерованный список" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-soft hover:bg-ink/[0.06]" @click="editor?.chain().focus().toggleOrderedList().run()">
                <NumberedListIcon class="h-4 w-4" />
            </button>
            <button type="button" title="Цитата" class="flex h-7 w-7 items-center justify-center rounded-lg text-[14px] text-ink-soft hover:bg-ink/[0.06]" @click="editor?.chain().focus().toggleBlockquote().run()">
                ❝
            </button>
            <button type="button" title="Ссылка" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-soft hover:bg-ink/[0.06]" @click="setLink">
                <LinkIcon class="h-3.5 w-3.5" />
            </button>
            <span class="mx-1 h-4 w-px bg-line"></span>
            <button type="button" title="Отменить" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-soft hover:bg-ink/[0.06]" @click="editor?.chain().focus().undo().run()">
                <ArrowUturnLeftIcon class="h-3.5 w-3.5" />
            </button>
            <button type="button" title="Повторить" class="flex h-7 w-7 items-center justify-center rounded-lg text-ink-soft hover:bg-ink/[0.06]" @click="editor?.chain().focus().redo().run()">
                <ArrowUturnRightIcon class="h-3.5 w-3.5" />
            </button>
        </div>
        <EditorContent :editor="editor" :style="{ '--editor-min-height': minHeight }" />
    </div>
</template>
