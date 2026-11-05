<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowPathIcon,
    CheckCircleIcon,
    DocumentTextIcon,
    PlayIcon,
    StopIcon,
} from '@heroicons/vue/24/outline';
import Button from '@/Components/UI/Button.vue';
import TextArea from '@/Components/Form/TextArea.vue';

const props = defineProps({
    project: { type: Object, required: true },
    studies: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.project.name,
    key: props.project.key,
    description: props.project.description || '',
    client_id: props.project.client?.id || null,
    status: props.project.status,
    priority: props.project.priority,
    color: props.project.color,
    site_url: props.project.site_url || '',
    instructions: props.project.instructions || '',
    agents_md: props.project.agents_md || '',
    starts_at: props.project.starts_at || null,
    due_at: props.project.due_at || null,
});

const focus = ref('');
const study = ref(props.studies.find((item) => ['queued', 'running'].includes(item.status)) ?? props.studies[0] ?? null);
const history = ref([...props.studies]);
const error = ref(null);
const starting = ref(false);
const preview = ref(false);
let pollTimer = null;

const running = computed(() => ['queued', 'running'].includes(study.value?.status));
const statusLabel = computed(() => {
    if (!study.value && props.project.studied_at) return 'изучен ' + props.project.studied_at;

    return (
        {
            queued: 'в очереди',
            running: 'изучаю сайт',
            completed: 'готово',
            failed: 'ошибка',
            cancelled: 'остановлено',
        }[study.value?.status] || 'не запускалось'
    );
});
const events = computed(() =>
    (study.value?.events || [])
        .filter((event) => !['status', 'session'].includes(event.type))
        .slice(-30)
        .reverse(),
);

function eventLabel(event) {
    if (event.type === 'tool_call') {
        if (event.tool === 'bash' && event.input?.command) return '$ ' + String(event.input.command).slice(0, 200);
        return event.tool || 'инструмент';
    }
    if (event.type === 'tool_result') return String(event.result || '').split('\n')[0].slice(0, 200);
    if (event.type === 'thinking') return String(event.text || '…').slice(0, 200);
    if (event.type === 'text') return String(event.text || '').slice(0, 200);
    if (event.type === 'final') return 'итог: ' + String(event.text || '').slice(0, 200);
    return event.type;
}

function tone(event) {
    if (event.type === 'tool_call') return 'text-sky';
    if (event.type === 'tool_result') return 'text-sage';
    if (event.type === 'thinking') return 'text-peach';
    return 'text-white/70';
}

async function poll() {
    if (!study.value?.id) return;

    try {
        const { data } = await axios.get(route('admin.projects.study.status', [props.project.id, study.value.id]));
        study.value = data.study;

        if (data.study?.status === 'completed' && data.study?.agents_md) {
            form.agents_md = data.study.agents_md;
            form.instructions = form.instructions || '';
        }

        if (!running.value) {
            window.clearInterval(pollTimer);
            pollTimer = null;
            history.value = [data.study, ...history.value.filter((item) => item.id !== data.study.id)];
        }
    } catch (exception) {
        error.value = exception?.response?.data?.message || 'Не удалось получить статус изучения.';
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

async function start() {
    if (starting.value) return;

    starting.value = true;
    error.value = null;
    window.clearInterval(pollTimer);

    try {
        const { data } = await axios.post(route('admin.projects.study', props.project.id), { focus: focus.value || null });
        study.value = data.study;
        pollTimer = window.setInterval(poll, 2500);
        await poll();
    } catch (exception) {
        error.value = exception?.response?.data?.message || 'Не удалось запустить изучение.';
    } finally {
        starting.value = false;
    }
}

async function cancel() {
    if (!study.value?.id) return;

    const { data } = await axios.post(route('admin.projects.study.cancel', [props.project.id, study.value.id]));
    study.value = data.study;
    window.clearInterval(pollTimer);
    pollTimer = null;
}

function save() {
    form.put(route('admin.projects.update', props.project.id), { preserveScroll: true });
}

onMounted(() => {
    if (running.value) {
        pollTimer = window.setInterval(poll, 2500);
        poll();
    }
});

onBeforeUnmount(() => window.clearInterval(pollTimer));
</script>

<template>
    <div class="space-y-4">
        <section class="overflow-hidden rounded-[24px] border border-line bg-surface shadow-[0_16px_36px_rgba(28,24,35,0.05)]">
            <div class="flex flex-wrap items-center gap-2 border-b border-line/80 px-5 py-4">
                <span :class="['h-2 w-2 rounded-full', running ? 'animate-pulse bg-warning' : study?.status === 'completed' || project.studied_at ? 'bg-success' : 'bg-faint']"></span>
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-muted">{{ statusLabel }}</p>
                <Button v-if="!running" class="ml-auto" size="sm" :disabled="starting || !form.site_url" @click="start">
                    <PlayIcon class="h-3.5 w-3.5" />
                    {{ starting ? 'Запускаю…' : 'Изучить сайт' }}
                </Button>
                <Button v-else class="ml-auto" size="sm" variant="secondary" @click="cancel">
                    <StopIcon class="h-3.5 w-3.5" />
                    Остановить
                </Button>
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-[minmax(0,1fr)_150px]">
                <div class="flex min-w-0 items-center gap-2 text-[11px] text-muted">
                    <DocumentTextIcon class="h-4 w-4 text-primary-500" />
                    <span class="truncate">{{ form.site_url || 'сайт не задан' }}</span>
                </div>
                <input
                    v-model="focus"
                    class="h-9 rounded-[12px] border border-line bg-white/70 px-3 text-[11px] text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                    placeholder="фокус: услуги и цены"
                />
            </div>

            <p v-if="error" class="mx-5 mb-4 rounded-xl border border-danger/20 bg-danger/[0.06] px-3 py-2 text-[11px] text-danger-ink">
                {{ error }}
            </p>
        </section>

        <section v-if="study" class="overflow-hidden rounded-[22px] border border-ink/15 bg-ink text-canvas shadow-[0_18px_40px_rgba(28,24,35,0.22)]">
            <header class="flex items-center gap-2 border-b border-white/10 px-4 py-2">
                <span class="flex gap-1">
                    <span class="h-1.5 w-1.5 rounded-full bg-danger/70"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-warning/70"></span>
                    <span class="h-1.5 w-1.5 rounded-full bg-success/70"></span>
                </span>
                <p class="ml-1 font-mono text-[9px] uppercase tracking-[0.16em] text-white/50">study · harness</p>
                <span class="ml-auto font-mono text-[9px] text-white/35">{{ study.id?.slice(0, 8) }}</span>
            </header>
            <div class="max-h-[240px] overflow-y-auto px-4 py-2 font-mono text-[9px] leading-relaxed">
                <p v-if="events.length === 0" class="text-white/40">Агент готовит план изучения…</p>
                <div v-for="(event, index) in events" :key="index" class="flex gap-2 py-0.5">
                    <span class="w-[64px] shrink-0 text-white/25">{{ event.type }}</span>
                    <span :class="['min-w-0 break-words', tone(event)]">{{ eventLabel(event) }}</span>
                </div>
                <p v-if="running" class="mt-1 animate-pulse text-white/40">▍</p>
            </div>
            <footer class="flex items-center gap-2 border-t border-white/10 px-4 py-2">
                <span v-if="study.status === 'completed'" class="inline-flex items-center gap-1.5 text-[10px] text-sage">
                    <CheckCircleIcon class="h-3.5 w-3.5" />
                    AGENTS.md обновлён — проверьте и сохраните
                </span>
                <span v-else-if="study.status === 'failed'" class="text-[10px] text-danger">{{ study.error || 'Не удалось' }}</span>
                <span v-else-if="running" class="text-[10px] text-white/50">Читаю страницы и пишу инструкцию…</span>
                <span class="ml-auto font-mono text-[9px] text-white/30">≤ 6 запросов · ≤ 120 строк</span>
            </footer>
        </section>

        <section class="surface-card p-5">
            <div class="flex flex-wrap items-center gap-3">
                <DocumentTextIcon class="h-5 w-5 text-primary-600" />
                <div>
                    <p class="text-[12px] font-bold text-ink">AGENTS.md</p>
                    <p class="text-[10px] text-muted">Что Кики знает об этом проекте</p>
                </div>
                <button
                    type="button"
                    class="ml-auto rounded-full border border-line px-2.5 py-1 text-[10px] font-semibold text-muted"
                    @click="preview = !preview"
                >
                    {{ preview ? 'Редактор' : 'Превью' }}
                </button>
            </div>

            <textarea
                v-if="!preview"
                v-model="form.agents_md"
                rows="12"
                class="mt-4 w-full rounded-[16px] border border-line bg-white/70 px-4 py-3 font-mono text-[11px] leading-relaxed text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                placeholder="# Проект&#10;&#10;## О проекте&#10;…"
            ></textarea>
            <pre v-else class="mt-4 max-h-[320px] overflow-auto whitespace-pre-wrap rounded-[16px] border border-line bg-white/60 px-4 py-3 font-mono text-[11px] leading-relaxed text-ink-soft">{{ form.agents_md || 'Пока пусто. Изучите сайт или вставьте инструкцию вручную.' }}</pre>

            <div class="mt-4">
                <TextArea v-model="form.instructions" label="Постоянные указания" :rows="3" placeholder="Что важно помнить в этом проекте" />
            </div>

            <div class="mt-4 flex items-center gap-2">
                <Button size="sm" :disabled="form.processing" @click="save">
                    <ArrowPathIcon v-if="form.processing" class="h-3.5 w-3.5 animate-spin" />
                    Сохранить контекст
                </Button>
                <p class="text-[10px] text-muted">Действует на все задачи и диалоги проекта.</p>
            </div>
        </section>

        <section v-if="history.length" class="surface-card p-5">
            <p class="eyebrow">История изучения</p>
            <div class="mt-3 space-y-2">
                <div v-for="item in history.slice(0, 5)" :key="item.id" class="flex items-center gap-2 rounded-xl border border-line/70 px-3 py-2">
                    <span :class="['h-1.5 w-1.5 rounded-full', item.status === 'completed' ? 'bg-success' : item.status === 'failed' ? 'bg-danger' : 'bg-warning']"></span>
                    <span class="text-[10px] text-ink-soft">{{ item.actor?.name || 'Кики' }}</span>
                    <span class="ml-auto font-mono text-[9px] text-faint">{{ item.started_at?.slice(0, 16).replace('T', ' ') }}</span>
                </div>
            </div>
        </section>
    </div>
</template>
