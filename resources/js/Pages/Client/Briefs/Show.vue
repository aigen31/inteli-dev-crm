<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import BriefAnswers from '@/Components/Briefs/BriefAnswers.vue';
import BriefField from '@/Components/Briefs/BriefField.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import { answerSections, briefProgress, isAnswered, visibleSections } from '@/Utils/briefs.js';
import { t } from '@/i18n';
import { formatDateTime } from '@/Utils/formatters.js';
import {
    ArrowLeftIcon,
    ArrowRightIcon,
    CheckIcon,
    CheckCircleIcon,
    CloudArrowUpIcon,
    ExclamationTriangleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    brief: Object,
    schema: Object,
    answers: { type: Object, default: () => ({}) },
    successMessage: String,
    progress: { type: Number, default: 0 },
});

const answers = reactive({ ...props.answers });
const sections = computed(() => visibleSections(props.schema, answers));
const progress = computed(() => briefProgress(props.schema, answers));
const editable = computed(() => props.brief.is_editable);
const submitted = computed(() => props.brief.status === 'submitted' || props.brief.status === 'archived');
const readOnlySections = computed(() => answerSections(props.schema, props.answers));

const errors = reactive({});
const summaryRef = ref(null);
const confirmOpen = ref(false);
const submitting = ref(false);

const savingState = ref('idle'); // idle | saving | saved | error
const savedAt = ref(null);
let saveTimer = null;

const firstIncomplete = computed(() => {
    const index = sections.value.findIndex((section) =>
        section.fields.some((field) => field.required && !isAnswered(answers[field.key])),
    );
    return index >= 0 ? index : 0;
});

const currentIndex = ref(0);
const current = computed(() => sections.value[currentIndex.value] || null);

const totalRequired = computed(() =>
    sections.value.reduce((sum, section) => sum + section.fields.filter((field) => field.required).length, 0),
);
const filledRequired = computed(() =>
    sections.value.reduce(
        (sum, section) => sum + section.fields.filter((field) => field.required && isAnswered(answers[field.key])).length,
        0,
    ),
);

function sectionDone(section) {
    return section.fields
        .filter((field) => field.required)
        .every((field) => isAnswered(answers[field.key]));
}

const isLast = computed(() => currentIndex.value >= sections.value.length - 1);

watch(
    answers,
    () => {
        if (!editable.value) return;
        savingState.value = 'idle';
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 900);
    },
    { deep: true },
);

async function save() {
    if (!editable.value) return;

    savingState.value = 'saving';

    try {
        const { data } = await window.axios.patch(route('client.briefs.save', props.brief.id), { answers });
        savingState.value = 'saved';
        savedAt.value = data.saved_at;
    } catch (error) {
        savingState.value = 'error';
    }
}

function go(index) {
    currentIndex.value = Math.max(0, Math.min(index, sections.value.length - 1));
    Object.keys(errors).forEach((key) => delete errors[key]);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function next() {
    if (!isLast.value) {
        go(currentIndex.value + 1);
        return;
    }

    confirmOpen.value = true;
}

function focusField(key) {
    const index = sections.value.findIndex((section) => section.fields.some((field) => field.key === key));
    if (index >= 0) go(index);
    nextTick(() => document.getElementById(key)?.focus());
}

const REQUIRED_PREFIX = 'Обязательное поле: ';

function errorText(message) {
    return typeof message === 'string' && message.startsWith(REQUIRED_PREFIX)
        ? `${t('Обязательное поле')}: ${message.slice(REQUIRED_PREFIX.length)}`
        : message;
}

function fieldError(key) {
    return errors[key] || null;
}

async function submit() {
    submitting.value = true;
    clearTimeout(saveTimer);

    router.post(
        route('client.briefs.submit', props.brief.id),
        { answers },
        {
            preserveScroll: true,
            onError: async (bag) => {
                Object.keys(errors).forEach((key) => delete errors[key]);
                Object.entries(bag).forEach(([key, message]) => {
                    if (key.startsWith('data.')) errors[key.slice(5)] = Array.isArray(message) ? message[0] : message;
                    else errors[key] = Array.isArray(message) ? message[0] : message;
                });
                confirmOpen.value = false;
                submitting.value = false;

                const firstKey = Object.keys(errors).find((key) => key !== 'status');
                const target = firstKey ? sections.value.findIndex((section) => section.fields.some((field) => field.key === firstKey)) : -1;

                if (target >= 0) currentIndex.value = target;

                await nextTick();
                summaryRef.value?.focus();
                summaryRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            },
            onSuccess: () => {
                submitting.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function removeBrief() {
    if (! window.confirm('Удалить бриф? Ответы будут потеряны.')) return;

    router.delete(route('client.briefs.destroy', props.brief.id));
}

onBeforeUnmount(() => {
    clearTimeout(saveTimer);
    if (editable.value && savingState.value !== 'saved') save();
});
</script>

<template>
    <ClientLayout :title="brief.title">
        <template #actions>
            <Link :href="route('client.briefs.index')">
                <Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />К списку</Button>
            </Link>
            <Button v-if="editable" variant="ghost" size="sm" @click="removeBrief">
                <TrashIcon class="h-4 w-4" />Удалить
            </Button>
        </template>

        <!-- Отправленный бриф: читаемый вид -->
        <div v-if="submitted" class="space-y-5">
            <section class="surface-card p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ brief.template?.title }}</p>
                        <p class="mt-2 text-[11px] text-muted">
                            <template v-if="brief.submitted_at">Отправлен {{ formatDateTime(brief.submitted_at) }}</template>
                            <template v-else>Бриф закрыт</template>
                            <template v-if="brief.project"> · {{ brief.project.key }}</template>
                        </p>
                    </div>
                    <Badge :status="brief.status" type="brief">{{ brief.status_label }}</Badge>
                </div>

                <div class="mt-4 flex items-start gap-3 rounded-[14px] bg-success/10 px-4 py-3">
                    <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-success" />
                    <p class="text-[12px] leading-relaxed text-ink">{{ successMessage }}</p>
                </div>
            </section>

            <BriefAnswers :sections="readOnlySections" hide-empty />
        </div>

        <!-- Мастер заполнения -->
        <div v-else class="space-y-4">
            <section class="surface-card sticky top-2 z-10 p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ brief.template?.title }}</p>
                    </div>
                    <div class="flex items-center gap-2 font-mono text-[9px] text-faint">
                        <CloudArrowUpIcon class="h-4 w-4" :class="savingState === 'saving' ? 'animate-pulse text-primary-600' : 'text-faint'" />
                        <span v-if="savingState === 'saving'">Сохраняю…</span>
                        <span v-else-if="savingState === 'saved'">Сохранено{{ savedAt ? ` в ${new Date(savedAt).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}` : '' }}</span>
                        <span v-else-if="savingState === 'error'" class="text-danger">Не сохранилось</span>
                        <span v-else>Автосохранение</span>
                    </div>
                </div>

                <div class="mt-3 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-primary-500 transition-[width] duration-300" :style="{ width: `${progress}%` }" />
                    </div>
                    <span class="font-mono text-[10px] text-ink">{{ progress }}%</span>
                    <span class="font-mono text-[10px] text-faint">{{ filledRequired }}/{{ totalRequired }}</span>
                </div>

                <nav class="mt-3 -mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1" aria-label="Секции брифа">
                    <button
                        v-for="(section, index) in sections"
                        :key="section.key"
                        type="button"
                        :class="[
                            'inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-semibold transition',
                            index === currentIndex
                                ? 'border-ink bg-ink text-canvas'
                                : sectionDone(section)
                                  ? 'border-success/40 bg-success/10 text-success'
                                  : 'border-line bg-white/60 text-muted hover:border-line-strong hover:text-ink',
                        ]"
                        :aria-current="index === currentIndex ? 'step' : undefined"
                        @click="go(index)"
                    >
                        <CheckIcon v-if="sectionDone(section) && index !== currentIndex" class="h-3 w-3" />
                        <span v-else class="font-mono text-[9px] opacity-70">{{ index + 1 }}</span>
                        {{ section.title }}
                    </button>
                </nav>
            </section>

            <div
                v-if="Object.keys(errors).length"
                ref="summaryRef"
                tabindex="-1"
                role="alert"
                class="surface-card border-danger/40 bg-danger/5 p-4 focus:outline-none"
            >
                <div class="flex items-start gap-2">
                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0 text-danger" />
                    <div class="min-w-0">
                        <p class="text-[12px] font-bold text-danger">Проверьте обязательные поля</p>
                        <ul class="mt-1 space-y-1">
                            <li v-for="(message, key) in errors" :key="key">
                                <button type="button" class="text-left text-[11px] text-danger-ink underline decoration-danger/40 underline-offset-2 hover:decoration-danger" @click="focusField(key)">
                                    {{ errorText(message) }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <section v-if="current" class="surface-card p-5 sm:p-6">
                <header class="mb-4">
                    <p class="eyebrow">Секция {{ currentIndex + 1 }} из {{ sections.length }}</p>
                    <h2 class="mt-1 text-[19px] font-medium tracking-[-0.02em] text-ink">{{ current.title }}</h2>
                    <p v-if="current.description" class="mt-1 text-[11px] leading-relaxed text-muted">{{ current.description }}</p>
                </header>

                <div class="space-y-5">
                    <BriefField
                        v-for="field in current.fields"
                        :key="field.key"
                        v-model="answers[field.key]"
                        :field="field"
                        :error="fieldError(field.key)"
                    />
                </div>

                <footer class="mt-6 flex items-center justify-between gap-3 border-t border-line/70 pt-4">
                    <Button variant="ghost" size="sm" :disabled="currentIndex === 0" @click="go(currentIndex - 1)">
                        <ArrowLeftIcon class="h-4 w-4" />Назад
                    </Button>

                    <Button v-if="!isLast" size="sm" @click="next">
                        Далее<ArrowRightIcon class="h-4 w-4" />
                    </Button>
                    <Button v-else size="sm" :disabled="submitting" @click="next">
                        <CheckCircleIcon class="h-4 w-4" />Отправить бриф
                    </Button>
                </footer>
            </section>

            <Modal :show="confirmOpen" max-width="md" @close="confirmOpen = false">
                <div class="p-6">
                    <p class="eyebrow">Отправка брифа</p>
                    <h3 class="mt-1 text-[18px] font-medium tracking-[-0.02em] text-ink">Отправить «{{ brief.title }}»?</h3>
                    <p class="mt-2 text-[12px] leading-relaxed text-muted">
                        Заполнено {{ progress }}% — обязательных полей {{ filledRequired }} из {{ totalRequired }}.
                        После отправки бриф уходит на разбор, а ответы фиксируются в истории.
                    </p>

                    <div v-if="filledRequired < totalRequired" class="mt-3 rounded-[12px] bg-warning/10 px-3 py-2 text-[11px] text-warning">
                        Остались незаполненные обязательные поля — сервер попросит их дополнить.
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <Button variant="ghost" size="sm" @click="confirmOpen = false">Вернуться</Button>
                        <Button size="sm" :disabled="submitting" @click="submit">
                            {{ submitting ? 'Отправляю…' : 'Отправить' }}
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    </ClientLayout>
</template>
