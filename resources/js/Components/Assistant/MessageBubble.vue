<script setup>
import { computed, ref } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { ArrowPathIcon, CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import ImageLightbox from '@/Components/UI/ImageLightbox.vue';

const props = defineProps({
    message: { type: Object, required: true },
    canRegenerate: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['regenerate']);

const copied = ref(false);
const lightboxOpen = ref(false);
const lightboxIndex = ref(0);

marked.setOptions({ breaks: true, gfm: true });

const isUser = computed(() => props.message.role === 'user');
const attachments = computed(() => props.message.attachments ?? []);
const hasImages = computed(() => attachments.value.length > 0);

const html = computed(() => {
    if (isUser.value) return '';

    return DOMPurify.sanitize(marked.parse(props.message.content ?? ''), { USE_PROFILES: { html: true } });
});

const images = computed(() =>
    attachments.value.map((file) => ({
        url: file.url,
        alt: file.name || 'Изображение',
        download: file.download_url || file.url,
    })),
);

function formatTime(value) {
    if (!value) return '';

    return new Date(value).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function openImage(index) {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
}

async function copy() {
    try {
        await navigator.clipboard.writeText(props.message.content ?? '');
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 1600);
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <div :class="isUser ? 'flex justify-end' : 'flex items-start gap-2.5'">
        <KikiAvatar v-if="!isUser" variant="face" fit="contain" :size="28" class="mt-0.5" />

        <div :class="['min-w-0', isUser ? 'max-w-[86%]' : 'max-w-[88%]']">
            <div
                v-if="isUser"
                class="rounded-[20px] rounded-br-md bg-primary-500 px-4 py-2.5 text-[13px] leading-relaxed text-white shadow-[0_10px_25px_rgba(109,90,230,0.18)]"
            >
                <p v-if="message.content" class="whitespace-pre-wrap break-words">{{ message.content }}</p>
            </div>

            <div v-else class="prose-kiki rounded-[20px] rounded-tl-md border border-line bg-white/60 px-4 py-2.5" v-html="html"></div>

            <div v-if="hasImages" class="mt-2 flex flex-wrap gap-2" :class="isUser ? 'justify-end' : ''">
                <button
                    v-for="(image, index) in images"
                    :key="image.url || index"
                    type="button"
                    class="group relative overflow-hidden rounded-[14px] border border-line bg-surface transition hover:border-primary-300"
                    :title="image.alt"
                    @click="openImage(index)"
                >
                    <img :src="image.url" :alt="image.alt" class="h-[76px] w-[76px] object-cover transition group-hover:opacity-90" loading="lazy" />
                </button>
            </div>

            <div v-if="!isUser" class="mt-1 flex items-center gap-2 pl-1">
                <p v-if="message.created_at" class="font-mono text-[9px] text-faint">{{ formatTime(message.created_at) }}</p>
                <button type="button" class="rounded-md p-1 text-faint transition hover:text-ink" title="Скопировать ответ" @click="copy">
                    <CheckIcon v-if="copied" class="h-3 w-3 text-success" />
                    <ClipboardDocumentIcon v-else class="h-3 w-3" />
                </button>
                <button
                    v-if="canRegenerate"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md px-1 py-1 text-[10px] font-medium text-faint transition hover:text-primary-700 disabled:opacity-40"
                    :disabled="busy"
                    title="Ответить заново"
                    @click="emit('regenerate')"
                >
                    <ArrowPathIcon class="h-3 w-3" :class="busy ? 'animate-spin' : ''" />
                    Ещё раз
                </button>
            </div>
        </div>

        <ImageLightbox v-model:open="lightboxOpen" v-model:index="lightboxIndex" :images="images" />
    </div>
</template>
