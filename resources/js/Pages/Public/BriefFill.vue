<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import BriefField from '@/Components/Briefs/BriefField.vue';
import Button from '@/Components/UI/Button.vue';
import { isAnswered, briefProgress, isVisible } from '@/Utils/briefs.js';
import { CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    closed: { type: Boolean, default: false },
    closedReason: { type: String, default: null },
    brief: { type: Object, required: true },
    schema: { type: Object, default: null },
    answers: { type: Object, default: () => ({}) },
    endpoints: { type: Object, required: true },
    successMessage: { type: String, default: '' },
    progress: { type: Number, default: 0 },
});

const answers = reactive({ ...props.answers });
const errors = reactive({});
const submitting = ref(false);
const savingState = ref('idle');
const savedAt = ref(null);
const summaryRef = ref(null);
let saveTimer = null;

const sections = computed(() => props.schema?.sections ?? []);

const visibleMap = computed(() => {
    const map = {};

    for (const section of sections.value) {
        map[section.key] = section.fields.filter((field) => isVisible(field.show_if, answers));
    }

    return map;
});

const percent = computed(() => (props.schema ? briefProgress(props.schema, answers) : 100));

const requiredLeft = computed(() => {
    let left = 0;

    for (const section of sections.value) {
        for (const field of visibleMap.value[section.key] ?? []) {
            if (field.required && !isAnswered(answers[field.key])) left += 1;
        }
    }

    return left;
});

const errorKeys = computed(() => Object.keys(errors).filter((key) => key !== 'status'));

watch(
    answers,
    () => {
        if (props.closed) return;
        savingState.value = 'idle';
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 900);
    },
    { deep: true },
);

async function save() {
    if (props.closed) return;

    savingState.value = 'saving';

    try {
        const { data } = await window.axios.patch(props.endpoints.save, { answers });
        savingState.value = 'saved';
        savedAt.value = data.saved_at;
    } catch {
        savingState.value = 'error';
    }
}

function focusField(key) {
    nextTick(() => document.getElementById(key)?.focus());
}

async function submit() {
    if (props.closed) return;

    submitting.value = true;
    clearTimeout(saveTimer);
    Object.keys(errors).forEach((key) => delete errors[key]);

    router.post(props.endpoints.submit, { answers }, {
        preserveScroll: true,
        onError: (bag) => {
            Object.entries(bag).forEach(([key, message]) => {
                if (key.startsWith('data.')) errors[key.slice(5)] = Array.isArray(message) ? message[0] : message;
                else errors[key] = Array.isArray(message) ? message[0] : message;
            });
            submitting.value = false;

            const firstKey = Object.keys(errors).find((key) => key !== 'status');
            if (firstKey) focusField(firstKey);
            summaryRef.value?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },
        onFinish: () => {
            submitting.value = false;
        },
    });
}

onBeforeUnmount(() => {
    clearTimeout(saveTimer);
    if (! props.closed && savingState.value !== 'saved') save();
});

const closedText = computed(() => matchReason(props.closedReason));

function matchReason(reason) {
    switch (reason) {
        case 'sent':
            return { title: 'Бриф уже отправлен', text: 'Спасибо! Ответы получены — я вернусь с разбором в течение рабочего дня.' };
        case 'archived':
            return { title: 'Бриф закрыт', text: 'Этот бриф больше не принимает ответы. Напишите владельцу, если нужно заполнить новый.' };
        case 'revoked':
            return { title: 'Ссылка отозвана', text: 'Владелец закрыл доступ по этой ссылке. Попросите новую.' };
        case 'expired':
            return { title: 'Ссылка истекла', text: 'Срок действия ссылки закончился. Попросите владельца выпустить новую.' };
        default:
            return { title: 'Бриф недоступен', text: 'Ссылка недействительна.' };
    }
}
</script>

<template>
    <div class="min-h-screen bg-canvas px-4 py-8 sm:px-6 sm:py-12">
        <div class="mx-auto w-full max-w-[760px]">
            <header class="mb-6 flex items-center justify-between gap-3">
                <span class="font-mono text-[10px] uppercase tracking-[0.24em] text-faint">inteli dev</span>
                <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">бриф</span>
            </header>

            <div v-if="closed" class="surface-card p-6 sm:p-8">
                <div class="flex items-start gap-3">
                    <CheckCircleIcon v-if="closedReason === 'sent'" class="mt-0.5 h-6 w-6 shrink-0 text-success" />
                    <ExclamationTriangleIcon v-else class="mt-0.5 h-6 w-6 shrink-0 text-warning" />
                    <div class="min-w-0">
                        <p class="eyebrow">{{ brief.title }}</p>
                        <h1 class="mt-2 text-[20px] font-medium tracking-[-0.02em] text-ink">{{ closedText.title }}</h1>
                        <p class="mt-2 text-[13px] leading-relaxed text-muted">{{ closedText.text }}</p>
                        <p v-if="closedReason === 'sent'" class="mt-3 text-[12px] leading-relaxed text-muted">{{ successMessage }}</p>
                    </div>
                </div>
            </div>

            <template v-else>
                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">{{ brief.template?.title }}</p>
                    <h1 class="mt-2 text-[22px] font-medium tracking-[-0.03em] text-ink">{{ brief.title }}</h1>
                    <p v-if="brief.template?.summary" class="mt-2 text-[12px] leading-relaxed text-muted">{{ brief.template.summary }}</p>

                    <div class="mt-4 flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-primary-500 transition-[width] duration-300" :style="{ width: `${percent}%` }" />
                        </div>
                        <span class="font-mono text-[10px] text-muted">{{ percent }}%</span>
                    </div>

                    <p class="mt-3 text-[11px] text-faint">
                        <template v-if="savingState === 'saving'">Сохраняю…</template>
                        <template v-else-if="savingState === 'saved'">Сохранено автоматически</template>
                        <template v-else-if="savingState === 'error'">Не удалось сохранить — проверьте связь</template>
                        <template v-else>Ответы сохраняются автоматически</template>
                        <template v-if="brief.expires_at"> · ссылка действует до {{ new Date(brief.expires_at).toLocaleDateString('ru-RU') }}</template>
                    </p>
                </section>

                <div v-if="errorKeys.length" ref="summaryRef" class="mt-4 rounded-[14px] border border-danger/30 bg-danger/5 p-4">
                    <p class="text-[12px] font-semibold text-danger">Проверьте {{ errorKeys.length }} {{ errorKeys.length === 1 ? 'поле' : 'поля' }}</p>
                    <ul class="mt-2 space-y-1">
                        <li v-for="key in errorKeys" :key="key">
                            <button type="button" class="text-left text-[11px] text-danger underline-offset-2 hover:underline" @click="focusField(key)">
                                {{ errors[key] }}
                            </button>
                        </li>
                    </ul>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <section v-for="section in sections" :key="section.key" class="surface-card p-5 sm:p-6">
                        <h2 class="text-[15px] font-semibold text-ink">{{ section.title }}</h2>
                        <p v-if="section.description" class="mt-1 text-[11px] leading-relaxed text-muted">{{ section.description }}</p>

                        <div class="mt-4 space-y-4">
                            <BriefField
                                v-for="field in visibleMap[section.key]"
                                :key="field.key"
                                :field="field"
                                v-model="answers[field.key]"
                                :error="errors[field.key] || null"
                            />
                        </div>
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[16px] border border-line bg-surface/80 px-4 py-3">
                        <p class="text-[11px] text-muted">
                            <template v-if="requiredLeft">{{ requiredLeft }} обязательных ещё не заполнено</template>
                            <template v-else>Всё обязательное заполнено — можно отправлять</template>
                        </p>
                        <Button type="submit" :disabled="submitting">
                            {{ submitting ? 'Отправляю…' : 'Отправить бриф' }}
                        </Button>
                    </div>

                    <p class="pb-4 text-center text-[10px] text-faint">
                        Ссылка персональная: не пересылайте её и не публикуйте. Ответы видит только владелец брифа.
                    </p>
                </form>
            </template>
        </div>
    </div>
</template>
