<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { ArrowPathIcon, DocumentTextIcon, PaperAirplaneIcon, PhotoIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: String, default: '' },
    attachments: { type: Array, default: () => [] },
    documents: { type: Array, default: () => [] },
    uploading: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
    canSend: { type: Boolean, default: false },
    maxAttachments: { type: Number, default: 4 },
    maxDocuments: { type: Number, default: 3 },
    placeholder: { type: String, default: 'Спросите Кики…' },
    hint: {
        type: String,
        default: 'Enter — отправить · Shift+Enter — перенос · изображения: скрепка, вставка или перетаскивание',
    },
});

const emit = defineEmits(['update:modelValue', 'send', 'files', 'remove', 'document-files', 'remove-document']);

const textarea = ref(null);
const fileInput = ref(null);
const documentInput = ref(null);
const dragging = ref(false);

const hasAttachments = computed(() => props.attachments.length > 0);
const room = computed(() => Math.max(props.maxAttachments - props.attachments.length, 0));
const documentRoom = computed(() => Math.max(props.maxDocuments - props.documents.length, 0));

function resize() {
    const element = textarea.value;
    if (!element) return;
    element.style.height = 'auto';
    element.style.height = `${Math.min(element.scrollHeight, 160)}px`;
}

function update(value) {
    emit('update:modelValue', value);
    nextTick(resize);
}

function submit() {
    if (!props.canSend) return;
    emit('send');
    nextTick(resize);
}

function onKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

function pickFiles(event) {
    const files = event.target.files;
    if (files?.length) emit('files', files);
    event.target.value = '';
}

function pickDocuments(event) {
    const files = event.target.files;
    if (files?.length) emit('document-files', files);
    event.target.value = '';
}

function onPaste(event) {
    const items = Array.from(event.clipboardData?.files ?? []).filter((file) => String(file.type).startsWith('image/'));
    if (items.length === 0) return;
    event.preventDefault();
    emit('files', items);
}

function onDrop(event) {
    dragging.value = false;
    const files = Array.from(event.dataTransfer?.files ?? []).filter((file) => String(file.type).startsWith('image/'));
    if (files.length) emit('files', files);
}

onMounted(resize);

defineExpose({ focus: () => textarea.value?.focus() });
</script>

<template>
    <div
        class="relative"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
    >
        <div
            v-if="dragging"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-[22px] border-2 border-dashed border-primary-400 bg-primary-50/85 text-[12px] font-semibold text-primary-800"
        >
            Отпустите изображение, чтобы прикрепить
        </div>

        <div v-if="documents.length" class="mb-2 flex flex-wrap gap-2">
            <div
                v-for="document in documents"
                :key="document.id"
                class="flex max-w-full items-center gap-2 rounded-[14px] border border-line bg-surface px-2.5 py-1.5"
            >
                <DocumentTextIcon class="h-4 w-4 shrink-0 text-primary-600" />
                <span class="min-w-0">
                    <span class="block max-w-[220px] truncate text-[11px] font-medium text-ink">{{ document.name }}</span>
                    <span class="block font-mono text-[9px] text-faint">{{ document.chars }} символов</span>
                </span>
                <button
                    type="button"
                    class="rounded-md p-0.5 text-faint transition hover:text-danger"
                    title="Убрать документ"
                    @click="emit('remove-document', document.id)"
                >
                    <XMarkIcon class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>

        <div
            v-if="hasAttachments"
            class="mb-2 flex flex-wrap gap-2"
        >
            <div
                v-for="file in attachments"
                :key="file.id"
                class="group relative overflow-hidden rounded-[14px] border border-line bg-surface"
            >
                <img :src="file.url" :alt="file.name" class="h-16 w-16 object-cover" />
                <button
                    type="button"
                    class="absolute right-0.5 top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-ink/70 text-white transition hover:bg-danger"
                    title="Убрать изображение"
                    @click="emit('remove', file.id)"
                >
                    <XMarkIcon class="h-3 w-3" />
                </button>
            </div>

            <div v-if="uploading > 0" class="flex h-16 w-16 items-center justify-center rounded-[14px] border border-dashed border-line text-muted">
                <ArrowPathIcon class="h-4 w-4 animate-spin" />
            </div>
        </div>

        <div class="flex items-end gap-1.5 rounded-[22px] border border-line bg-white/70 p-1.5 pl-2 transition focus-within:border-primary-300 focus-within:ring-2 focus-within:ring-primary-500/10">
            <button
                type="button"
                class="mb-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-ink/[0.05] hover:text-ink disabled:opacity-35"
                :disabled="documentRoom === 0 || uploading > 0"
                :title="documentRoom === 0 ? 'Достигнут лимит документов' : 'Прикрепить документ'"
                @click="documentInput?.click()"
            >
                <DocumentTextIcon class="h-[18px] w-[18px]" />
            </button>
            <input
                ref="documentInput"
                type="file"
                accept=".xlsx,.docx,.pptx,.pdf,.csv,.tsv,.txt,.md,.json,.xml,.html,.yaml,.yml,.log,.rtf"
                multiple
                class="sr-only"
                @change="pickDocuments"
            />

            <button
                type="button"
                class="mb-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-ink/[0.05] hover:text-ink disabled:opacity-35"
                :disabled="room === 0 || uploading > 0"
                :title="room === 0 ? 'Достигнут лимит изображений' : 'Прикрепить изображение'"
                @click="fileInput?.click()"
            >
                <PhotoIcon class="h-[18px] w-[18px]" />
            </button>
            <input ref="fileInput" type="file" accept="image/*" multiple class="sr-only" @change="pickFiles" />

            <textarea
                ref="textarea"
                :value="modelValue"
                rows="1"
                class="max-h-40 min-h-[38px] flex-1 resize-none border-0 bg-transparent py-2 text-[13px] text-ink placeholder-faint focus:outline-none focus:ring-0"
                :placeholder="placeholder"
                @input="update($event.target.value)"
                @keydown="onKeydown"
                @paste="onPaste"
            ></textarea>

            <button
                type="button"
                :disabled="!canSend"
                class="mb-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-500 text-white transition hover:bg-primary-600 disabled:opacity-35"
                title="Отправить"
                @click="submit"
            >
                <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                <PaperAirplaneIcon v-else class="h-4 w-4 -translate-x-px" />
            </button>
        </div>

        <p class="mt-1.5 text-center font-mono text-[9px] uppercase tracking-[0.12em] text-faint">
            {{ hint }}
        </p>
    </div>
</template>
