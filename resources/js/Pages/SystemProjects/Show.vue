<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { formatDate } from '@/Utils/formatters.js';
import {
    ArrowLeftIcon,
    ArrowUpIcon,
    BugAntIcon,
    CheckCircleIcon,
    ChatBubbleLeftRightIcon,
    LightBulbIcon,
    PlusIcon,
    SparklesIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    project: Object,
    items: Object,
    filters: Object,
    statusCounts: Object,
    stats: Object,
    canManage: Boolean,
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);
const layout = computed(() => (isAdmin.value ? AdminLayout : ClientLayout));
const backUrl = computed(() => route(isAdmin.value ? 'admin.projects.index' : 'client.projects.index'));
const isBug = computed(() => props.project.type === 'bugs');
const showForm = ref(false);
const voting = ref(null);
const formSearch = ref(props.filters?.q || '');

const form = useForm({
    title: '',
    description: '',
    category: props.project.categories?.[0]?.value || '',
    severity: props.project.severities?.[0]?.value || '',
    steps: '',
    expected: '',
    actual: '',
    environment: '',
});

const chips = computed(() => [
    { value: '', label: 'Все', count: props.stats?.total ?? 0 },
    ...props.project.statuses.map((status) => ({
        ...status,
        count: props.statusCounts?.[status.value] ?? 0,
    })),
]);

const toneClass = {
    sky: 'border-sky/40 bg-sky/10 text-ink-soft',
    peach: 'border-warning/30 bg-warning/10 text-warning',
    violet: 'border-primary-200 bg-primary-soft text-primary-800',
    sage: 'border-sage/40 bg-sage/10 text-ink-soft',
    ink: 'border-line bg-ink/[0.04] text-muted',
    warning: 'border-warning/30 bg-warning/10 text-warning',
    danger: 'border-danger/20 bg-danger/[0.08] text-danger-ink',
};

function filter(extra = {}) {
    router.get(route('system-projects.show', { project: props.project.key }), {
        ...props.filters,
        ...extra,
        status: props.filters.status || undefined,
        q: props.filters.q || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function setStatus(status) {
    filter({ status: status || undefined });
}

function search() {
    filter({ q: formSearch.value || undefined });
}

function submit() {
    form.post(route('system-projects.store', { project: props.project.key }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title', 'description', 'steps', 'expected', 'actual', 'environment');
            showForm.value = false;
        },
    });
}

async function vote(item) {
    if (voting.value === item.id) return;
    voting.value = item.id;

    try {
        const { data } = await axios.post(route('system-projects.vote', { project: props.project.key, item: item.id }));
        item.votes = data.votes;
        item.voted = data.voted;
    } finally {
        voting.value = null;
    }
}

function updateStatus(item, event) {
    router.patch(route('system-projects.status', { project: props.project.key, item: item.id }), {
        status: event.target.value,
    }, { preserveScroll: true });
}
</script>

<template>
    <component :is="layout" :title="project.name">
        <template #actions>
            <Link :href="backUrl">
                <Button variant="ghost" size="sm">
                    <ArrowLeftIcon class="h-4 w-4" />
                    Проекты
                </Button>
            </Link>
            <Button size="sm" @click="showForm = !showForm">
                <PlusIcon class="h-4 w-4" />
                {{ showForm ? 'Свернуть' : 'Предложить' }}
            </Button>
        </template>

        <section class="relative overflow-hidden rounded-[28px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] p-6 sm:p-8">
            <div class="pointer-events-none absolute -right-12 -top-16 h-56 w-56 rounded-full bg-primary-300/20 blur-3xl"></div>
            <div class="relative flex flex-wrap items-start gap-4">
                <span :class="['flex h-12 w-12 shrink-0 items-center justify-center rounded-[16px]', isBug ? 'bg-warning/10 text-warning' : 'bg-primary-soft text-primary-700']">
                    <BugAntIcon v-if="isBug" class="h-6 w-6" />
                    <LightBulbIcon v-else class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-line bg-white/60 px-2.5 py-1 font-mono text-[9px] tracking-[0.1em] text-muted">{{ project.code }}</span>
                        <span class="rounded-full border border-white/70 bg-white/45 px-2.5 py-1 text-[9px] font-semibold text-ink-soft">доступно всем</span>
                    </div>
                    <h1 class="mt-3 font-display text-[28px] font-medium leading-[1.08] tracking-[-0.04em] text-ink sm:text-[34px]">{{ project.name }}</h1>
                    <p class="mt-2 max-w-[650px] text-[13px] leading-relaxed text-ink-soft">{{ project.description }}</p>
                </div>
            </div>

            <div class="relative mt-6 grid grid-cols-3 gap-2 sm:max-w-[460px]">
                <div class="rounded-[16px] border border-white/70 bg-white/50 px-3 py-2.5">
                    <p class="font-mono text-[8px] uppercase tracking-[0.12em] text-muted">Открыто</p>
                    <p class="mt-1 text-[20px] font-semibold text-ink">{{ stats.open }}</p>
                </div>
                <div class="rounded-[16px] border border-white/70 bg-white/50 px-3 py-2.5">
                    <p class="font-mono text-[8px] uppercase tracking-[0.12em] text-muted">В планах</p>
                    <p class="mt-1 text-[20px] font-semibold text-ink">{{ stats.planned }}</p>
                </div>
                <div class="rounded-[16px] border border-white/70 bg-white/50 px-3 py-2.5">
                    <p class="font-mono text-[8px] uppercase tracking-[0.12em] text-muted">Сделано</p>
                    <p class="mt-1 text-[20px] font-semibold text-ink">{{ stats.done }}</p>
                </div>
            </div>
        </section>

        <div class="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-[1.5fr_.75fr]">
            <div class="min-w-0">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <button
                        v-for="chip in chips"
                        :key="chip.value"
                        type="button"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[10px] font-semibold transition',
                            (filters.status || '') === chip.value
                                ? 'border-ink bg-ink text-canvas'
                                : 'border-line bg-white/50 text-muted hover:border-line-strong hover:text-ink',
                        ]"
                        @click="setStatus(chip.value)"
                    >
                        {{ chip.label }}
                        <span class="font-mono text-[9px] opacity-60">{{ chip.count }}</span>
                    </button>
                    <span class="ml-auto inline-flex rounded-full border border-line bg-white/50 p-1">
                        <button type="button" :class="['rounded-full px-2.5 py-1 text-[9px] font-semibold', filters.sort !== 'new' ? 'bg-ink text-canvas' : 'text-muted']" @click="filter({ sort: 'top' })">Топ</button>
                        <button type="button" :class="['rounded-full px-2.5 py-1 text-[9px] font-semibold', filters.sort === 'new' ? 'bg-ink text-canvas' : 'text-muted']" @click="filter({ sort: 'new' })">Новые</button>
                    </span>
                </div>

                <form class="relative mb-4" @submit.prevent="search">
                    <input
                        v-model="formSearch"
                        type="search"
                        placeholder="Найти предложение или баг…"
                        class="h-10 w-full rounded-[13px] border border-line bg-white/65 px-4 text-[12px] text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                    />
                </form>

                <div v-if="items.data.length" class="space-y-3">
                    <article v-for="item in items.data" :key="item.id" class="surface-card relative overflow-hidden p-4 sm:p-5">
                        <div class="flex items-start gap-3 sm:gap-4">
                            <button
                                type="button"
                                :class="[
                                    'flex h-12 w-10 shrink-0 flex-col items-center justify-center rounded-[13px] border text-[10px] font-bold transition',
                                    item.voted
                                        ? 'border-primary-300 bg-primary-soft text-primary-800'
                                        : 'border-line bg-white/60 text-muted hover:border-primary-300 hover:text-primary-700',
                                ]"
                                :aria-pressed="item.voted"
                                :title="item.voted ? 'Убрать голос' : 'Поддержать'"
                                :disabled="voting === item.id"
                                @click="vote(item)"
                            >
                                <ArrowUpIcon class="h-4 w-4" />
                                <span class="mt-0.5">{{ item.votes }}</span>
                            </button>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span :class="['rounded-full border px-2 py-0.5 text-[9px] font-semibold', toneClass[item.status_tone] || toneClass.ink]">{{ item.status_label }}</span>
                                    <span v-if="item.category" class="rounded-full border border-line bg-white/70 px-2 py-0.5 text-[9px] text-muted">{{ item.category.label }}</span>
                                    <span v-if="item.severity" :class="['rounded-full border px-2 py-0.5 text-[9px] font-semibold', toneClass[item.severity.tone] || toneClass.ink]">{{ item.severity.label }}</span>
                                    <span class="ml-auto font-mono text-[9px] text-faint">{{ formatDate(item.created_at) }}</span>
                                </div>

                                <h2 class="mt-2 text-[15px] font-semibold leading-snug text-ink">{{ item.title }}</h2>
                                <p class="mt-1 whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">{{ item.description }}</p>

                                <div v-if="isBug && (item.steps || item.expected || item.actual || item.environment)" class="mt-3 grid gap-2 rounded-[14px] border border-line/70 bg-surface-2/30 p-3 text-[11px] leading-relaxed text-ink-soft sm:grid-cols-2">
                                    <div v-if="item.steps" class="sm:col-span-2"><span class="font-mono text-[8px] uppercase tracking-[0.1em] text-faint">Шаги</span><p class="mt-1 whitespace-pre-wrap">{{ item.steps }}</p></div>
                                    <div v-if="item.expected"><span class="font-mono text-[8px] uppercase tracking-[0.1em] text-faint">Ожидали</span><p class="mt-1 whitespace-pre-wrap">{{ item.expected }}</p></div>
                                    <div v-if="item.actual"><span class="font-mono text-[8px] uppercase tracking-[0.1em] text-faint">Получили</span><p class="mt-1 whitespace-pre-wrap">{{ item.actual }}</p></div>
                                    <div v-if="item.environment" class="sm:col-span-2"><span class="font-mono text-[8px] uppercase tracking-[0.1em] text-faint">Окружение</span><p class="mt-1">{{ item.environment }}</p></div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line/60 pt-3 text-[10px] text-muted">
                                    <span>{{ item.author?.name || 'Пользователь' }}</span>
                                    <span class="text-faint">·</span>
                                    <span class="inline-flex items-center gap-1"><ChatBubbleLeftRightIcon class="h-3 w-3" /> поддержали {{ item.votes }}</span>
                                    <div v-if="canManage" class="ml-auto">
                                        <select :value="item.status" class="h-8 rounded-[9px] border border-line bg-white/70 px-2 text-[10px] font-semibold text-ink outline-none focus:border-primary-300" @change="updateStatus(item, $event)">
                                            <option v-for="status in project.statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <EmptyState
                    v-else
                    :title="isBug ? 'Багов пока нет' : 'Предложений пока нет'"
                    :text="isBug ? 'Расскажите о проблеме — это поможет сделать CRM надёжнее.' : 'Поделитесь идеей — обсудим и возьмём лучшее в работу.'"
                >
                    <Button size="sm" @click="showForm = true"><PlusIcon class="h-4 w-4" />{{ isBug ? 'Сообщить о баге' : 'Предложить идею' }}</Button>
                </EmptyState>

                <Pagination :links="items" class="mt-5" />
            </div>

            <aside class="min-w-0">
                <section :class="['surface-card overflow-hidden transition', showForm ? '' : 'p-5']">
                    <div v-if="!showForm" class="text-center">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-[16px] bg-primary-soft text-primary-700">
                            <SparklesIcon class="h-6 w-6" />
                        </span>
                        <p class="mt-3 text-[14px] font-semibold text-ink">{{ isBug ? 'Нашли баг?' : 'Есть идея?' }}</p>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ isBug ? 'Опишите, что сломалось. Чем точнее шаги — тем быстрее исправим.' : 'Расскажите, чего не хватает. Мы читаем каждое предложение.' }}</p>
                        <Button class="mt-4 w-full" size="sm" @click="showForm = true"><PlusIcon class="h-4 w-4" />{{ isBug ? 'Сообщить о баге' : 'Предложить идею' }}</Button>
                    </div>

                    <div v-else class="p-4 sm:p-5">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <div>
                                <p class="text-[14px] font-semibold text-ink">{{ isBug ? 'Новый баг' : 'Новое предложение' }}</p>
                                <p class="mt-0.5 text-[10px] text-muted">Видно всем участникам CRM</p>
                            </div>
                            <button type="button" class="rounded-full p-1.5 text-muted hover:bg-ink/[0.04] hover:text-ink" @click="showForm = false">
                                <XMarkIcon class="h-4 w-4" />
                            </button>
                        </div>

                        <form class="space-y-3" @submit.prevent="submit">
                            <TextInput v-model="form.title" label="Коротко" :error="form.errors.title" placeholder="Например: тёмная тема для таблиц" required />
                            <TextArea v-model="form.description" label="Суть" :error="form.errors.description" :rows="4" :placeholder="isBug ? 'Что произошло и что вы делали?' : 'Какую задачу это решит?'" required />

                            <SelectInput v-if="!isBug" v-model="form.category" label="Категория" :options="project.categories.map((c) => ({ value: c.value, label: c.label }))" :error="form.errors.category" />
                            <SelectInput v-if="isBug" v-model="form.severity" label="Критичность" :options="project.severities.map((s) => ({ value: s.value, label: s.label }))" :error="form.errors.severity" />

                            <template v-if="isBug">
                                <TextArea v-model="form.steps" label="Как повторить" :error="form.errors.steps" :rows="3" placeholder="1. Открыть…&#10;2. Нажать…" required />
                                <TextArea v-model="form.expected" label="Что ожидали" :error="form.errors.expected" :rows="2" required />
                                <TextArea v-model="form.actual" label="Что получили" :error="form.errors.actual" :rows="2" required />
                                <TextInput v-model="form.environment" label="Окружение" :error="form.errors.environment" placeholder="Браузер, устройство, версия" />
                            </template>

                            <Button type="submit" class="w-full" :disabled="form.processing">
                                {{ form.processing ? 'Отправляю…' : 'Отправить' }}
                            </Button>
                        </form>
                    </div>
                </section>

                <div class="mt-3 rounded-[18px] border border-line/70 bg-white/40 p-4 text-[10px] leading-relaxed text-muted">
                    <p class="font-semibold text-ink">Как это работает</p>
                    <p class="mt-1">{{ isBug ? 'Мы подтверждаем баг, ставим в работу и отмечаем исправление. Голоса помогают оценить масштаб.' : 'Голосуйте за идеи, которые важны. Статус показывает, что мы уже рассматриваем и что взяли в работу.' }}</p>
                </div>
            </aside>
        </div>

        <div class="mt-6 flex items-center gap-2 text-[10px] text-muted">
            <CheckCircleIcon class="h-4 w-4 text-sage" />
            <span>{{ project.name }} — общий проект для всех пользователей CRM</span>
        </div>
    </component>
</template>
