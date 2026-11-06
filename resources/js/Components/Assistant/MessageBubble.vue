<script setup>
import { computed, ref } from 'vue';
import { localeTag } from '@/i18n';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import { ArrowPathIcon, CheckIcon, ClipboardDocumentIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';
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
const images = computed(() => attachments.value.filter((item) => (item.kind ?? 'image') !== 'document'));
const documents = computed(() => attachments.value.filter((item) => (item.kind ?? 'image') === 'document'));
const trace = computed(() => props.message.trace ?? []);
const hasImages = computed(() => images.value.length > 0);

const TOOL_LABELS = {
    read_document: 'Читаю документ',
    get_price_list: 'Смотрю прайс-лист',
    propose_task_plan: 'Собираю план задач',
    create_task_from_form: 'Готовлю заявку по форме',
    list_form_templates: 'Смотрю формы заявок',
    list_task_templates: 'Смотрю шаблоны задач',
    overview: 'Смотрю сводку CRM',
    list_tasks: 'Ищу задачи',
    list_clients: 'Ищу клиентов',
    list_invoices: 'Смотрю счета',
    get_task: 'Открываю задачу',
    get_client: 'Открываю клиента',
    study_project_site: 'Запускаю изучение сайта',
    crm_help: 'Ищу раздел CRM',
};

function stepLabel(step) {
    if (step.type === 'note') return 'Заметка';
    return TOOL_LABELS[step.name] || step.name || 'Действие';
}

function stepSummary(step) {
    if (step.type === 'note') return step.text || '';
    if (step.status === 'error') return step.summary || '';
    return '';
}

const html = computed(() => {
    if (isUser.value) return '';

    return DOMPurify.sanitize(marked.parse(props.message.content ?? ''), { USE_PROFILES: { html: true } });
});

const lightboxImages = computed(() =>
    images.value.map((file) => ({
        url: file.url,
        alt: file.name || 'Изображение',
        download: file.download_url || file.url,
    })),
);

function openImage(index) {
    lightboxIndex.value = index;
    lightboxOpen.value = true;
}

function formatTime(value) {
    if (!value) return '';

    return new Date(value).toLocaleTimeString(localeTag(), { hour: '2-digit', minute: '2-digit' });
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

            <div v-if="documents.length" class="mt-2 flex flex-wrap gap-2" :class="isUser ? 'justify-end' : ''">
                <a
                    v-for="document in documents"
                    :key="document.id"
                    :href="document.download_url || undefined"
                    target="_blank"
                    rel="noreferrer"
                    class="flex max-w-full items-center gap-2 rounded-[13px] border border-line bg-surface px-2.5 py-1.5 transition hover:border-primary-300"
                >
                    <DocumentTextIcon class="h-4 w-4 shrink-0 text-primary-600" />
                    <span class="min-w-0">
                        <span class="block max-w-[220px] truncate text-[11px] font-medium text-ink">{{ document.name }}</span>
                        <span class="block font-mono text-[9px] text-faint">{{ document.chars }} символов</span>
                    </span>
                </a>
            </div>

            <div v-if="hasImages" class="mt-2 flex flex-wrap gap-2" :class="isUser ? 'justify-end' : ''">
                <button
                    v-for="(image, index) in lightboxImages"
                    :key="image.url || index"
                    type="button"
                    class="group relative overflow-hidden rounded-[14px] border border-line bg-surface transition hover:border-primary-300"
                    :title="image.alt"
                    @click="openImage(index)"
                >
                    <img :src="image.url" :alt="image.alt" class="h-[76px] w-[76px] object-cover transition group-hover:opacity-90" loading="lazy" />
                </button>
            </div>

            <details v-if="!isUser && trace.length" class="mt-2 rounded-[14px] border border-line/70 bg-surface-2/40 px-3 py-2">
                <summary class="flex cursor-pointer list-none items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.12em] text-muted transition hover:text-ink">
                    <span class="h-1.5 w-1.5 rounded-full bg-sage"></span>
                    Ход работы · {{ trace.length }}
                </summary>
                <ol class="mt-2 space-y-1.5">
                    <li v-for="(step, index) in trace" :key="index" class="flex gap-2 text-[10.5px] leading-relaxed">
                        <span
                            class="mt-[5px] h-1.5 w-1.5 shrink-0 rounded-full"
                            :class="step.status === 'error' ? 'bg-danger' : 'bg-sage'"
                        ></span>
                        <div class="min-w-0">
                            <span class="font-medium text-ink-soft">{{ stepLabel(step) }}</span>
                            <span v-if="step.duration_ms" class="ml-1 font-mono text-[8px] text-faint">{{ step.duration_ms }}ms</span>
                            <p v-if="stepSummary(step)" class="mt-0.5 break-words text-muted">{{ stepSummary(step) }}</p>
                        </div>
                    </li>
                </ol>
            </details>

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

        <ImageLightbox v-model:open="lightboxOpen" v-model:index="lightboxIndex" :images="lightboxImages" />
    </div>
</template>
