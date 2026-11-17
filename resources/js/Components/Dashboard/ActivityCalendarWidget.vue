<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRightIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate } from '@/Utils/formatters.js';

const props = defineProps({
    compact: { type: Boolean, default: false },
    assigneeId: { type: String, default: null },
});
const root = ref(null);
const view = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const counts = ref({});
const loading = ref(false);
const selectedDay = ref(null);
const selectedTasks = ref([]);
const tasksLoading = ref(false);
const closeButton = ref(null);
let previousOverflow = '';
const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const monthKey = computed(() => `${view.value.getFullYear()}-${String(view.value.getMonth() + 1).padStart(2, '0')}`);
const monthLabel = computed(() => new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric' }).format(view.value));

const weekCells = computed(() => {
    const today = new Date();
    const offset = (today.getDay() + 6) % 7;
    const start = new Date(today);
    start.setDate(today.getDate() - offset);
    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        const key = dateKey(date);
        return { date, key, day: date.getDate(), today: key === dateKey(new Date()), load: counts.value[key] || 0 };
    });
});

const cells = computed(() => {
    const first = new Date(view.value.getFullYear(), view.value.getMonth(), 1);
    const offset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(start.getDate() - offset);
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        const key = dateKey(date);
        return { date, key, day: date.getDate(), current: date.getMonth() === view.value.getMonth(), today: key === dateKey(new Date()), load: counts.value[key] || 0 };
    });
});

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get(route('calendar.activity'), {
            params: { month: monthKey.value, assignee_id: props.assigneeId || undefined },
        });
        counts.value = data.days || {};
    } catch {
        counts.value = {};
    } finally {
        loading.value = false;
    }
}

async function selectDay(cell) {
    if (!cell.load) return;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    selectedDay.value = cell.key;
    await nextTick();
    closeButton.value?.focus();
    selectedTasks.value = [];
    tasksLoading.value = true;
    try {
        const { data } = await axios.get(route('calendar.tasks'), {
            params: { date: cell.key, assignee_id: props.assigneeId || undefined },
        });
        selectedTasks.value = data.tasks || [];
    } catch {
        selectedTasks.value = [];
    } finally {
        tasksLoading.value = false;
    }
}

function closeTasks() {
    selectedDay.value = null;
    selectedTasks.value = [];
    document.body.style.overflow = previousOverflow;
}
const selectedLoad = computed(() => counts.value[selectedDay.value] || selectedTasks.value.length);
const workloadLabel = computed(() => {
    if (selectedLoad.value <= 1) return 'спокойный день';
    if (selectedLoad.value === 2) return 'умеренная нагрузка';
    if (selectedLoad.value === 3) return 'плотный день';
    return 'пиковый день';
});
function formatWeekday(key) {
    const [y, m, d] = key.split('-').map(Number);
    return new Intl.DateTimeFormat('ru-RU', { weekday: 'long' }).format(new Date(y, m - 1, d));
}
function taskPeriod(task) {
    if (task.starts_at && task.deadline) return `${formatDate(task.starts_at)} — ${formatDate(task.deadline)}`;
    if (task.deadline) return `до ${formatDate(task.deadline)}`;
    if (task.starts_at) return `с ${formatDate(task.starts_at)}`;
    return 'Без срока';
}
function statusTone(status) {
    if (status === 'in_progress') return 'bg-primary-soft text-primary-800';
    if (status === 'awaiting_review') return 'bg-warning/10 text-warning';
    if (status === 'completed') return 'bg-success/10 text-success';
    if (status === 'rejected_by_client' || status === 'rejected_by_admin') return 'bg-danger/10 text-danger-ink';
    return 'bg-surface-2 text-muted';
}
function onKeydown(event) {
    if (event.key === 'Escape' && selectedDay.value) closeTasks();
}
function moveMonth(delta) { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + delta, 1); load(); }
watch(() => props.assigneeId, () => { counts.value = {}; closeTasks(); load(); });

function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
const isDarkTheme = () => typeof document !== 'undefined' && document.documentElement.dataset.theme === 'dark';

const LOAD_TONES = {
    light: [
        { background: 'transparent', color: 'inherit' },
        { background: '#E8F3EC', color: '#2F8F62' },
        { background: '#FBF0D9', color: '#B7791F' },
        { background: '#F6D8C4', color: '#A96120' },
        { background: '#F2C7C1', color: '#A33A33' },
    ],
    dark: [
        { background: 'transparent', color: 'inherit' },
        { background: '#1d2a22', color: '#7fd2a7' },
        { background: '#2f2818', color: '#e5c07b' },
        { background: '#33231a', color: '#e8a87c' },
        { background: '#3a1e1e', color: '#f1998f' },
    ],
};

function tone(load) {
    const palette = isDarkTheme() ? LOAD_TONES.dark : LOAD_TONES.light;

    return palette[Math.min(Number(load) || 0, 4)];
}
function formatDay(key) { const [y,m,d] = key.split('-').map(Number); return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long' }).format(new Date(y, m-1, d)); }
onMounted(() => {
    load();
    window.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = previousOverflow;
});
</script>

<template>
    <div ref="root" class="flex h-full min-h-0 flex-col">
        <div class="flex shrink-0 items-center justify-between gap-2">
            <button type="button" class="rounded p-1 text-faint hover:bg-surface-2 hover:text-ink" aria-label="Предыдущий месяц" @click="moveMonth(-1)"><ChevronLeftIcon class="h-3.5 w-3.5" /></button>
            <p class="text-[10px] font-bold capitalize text-ink">{{ monthLabel }}</p>
            <button type="button" class="rounded p-1 text-faint hover:bg-surface-2 hover:text-ink" aria-label="Следующий месяц" @click="moveMonth(1)"><ChevronRightIcon class="h-3.5 w-3.5" /></button>
        </div>
        <div :class="['mt-1 grid grid-cols-7 gap-0.5', compact ? 'text-[7px]' : 'text-[8px]']">
            <span v-for="day in weekdays" :key="day" class="py-0.5 text-center font-mono uppercase tracking-[0.04em] text-faint">{{ day }}</span>
        </div>
        <div v-if="compact" class="grid min-h-0 flex-1 grid-cols-7 gap-0.5">
            <button v-for="cell in weekCells" :key="cell.key" type="button" :class="['relative flex items-center justify-center rounded-[5px] font-mono text-[8px] transition hover:ring-1 hover:ring-primary-300 h-[24px]', cell.today && 'ring-1 ring-primary-300']" :style="tone(cell.load)" :aria-label="`${cell.day}, задач: ${cell.load}`" :title="`${cell.day}: ${cell.load} задач`" @click="selectDay(cell)">{{ cell.day }}</button>
        </div>
        <div v-else :class="['grid min-h-0 flex-1 grid-cols-7 gap-0.5']">
            <button v-for="cell in cells" :key="cell.key" type="button" :class="['relative flex items-center justify-center rounded-[5px] font-mono transition hover:ring-1 hover:ring-primary-300 h-[20px] text-[8px]', !cell.current && 'opacity-30', cell.today && 'ring-1 ring-primary-300']" :style="tone(cell.load)" :aria-label="`${cell.day}, задач: ${cell.load}`" :title="`${cell.day}: ${cell.load} задач`" @click="selectDay(cell)">{{ cell.day }}</button>
        </div>
        <div v-if="!compact" class="mt-1.5 flex items-center gap-1 text-[7px] text-faint">
            <span class="h-2 w-2 rounded-[2px]" :style="{ background: tone(1).background }"></span><span>спокойно</span>
            <span class="h-2 w-2 rounded-[2px]" :style="{ background: tone(2).background }"></span>
            <span class="h-2 w-2 rounded-[2px]" :style="{ background: tone(3).background }"></span>
            <span class="h-2 w-2 rounded-[2px]" :style="{ background: tone(4).background }"></span><span>пик</span>
        </div>

    </div>
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="selectedDay" class="fixed inset-0 z-[95] flex items-center justify-center bg-black/45 p-3 backdrop-blur-[3px] sm:p-6" @click.self="closeTasks">
                <section class="flex max-h-[88vh] w-full max-w-[660px] flex-col overflow-hidden rounded-[28px] border border-line bg-surface shadow-[0_30px_90px_rgba(20,16,30,.28)]" role="dialog" aria-modal="true" aria-label="Задачи на день">
                    <header class="relative shrink-0 overflow-hidden border-b border-line/70 px-5 pb-5 pt-6 sm:px-7">
                        <div class="absolute inset-x-0 top-0 h-1.5" :style="{ backgroundColor: tone(selectedLoad).background }"></div>
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-mono text-[8px] uppercase tracking-[0.18em] text-primary-700">День · {{ formatWeekday(selectedDay) }}</p>
                                <h2 class="mt-1 font-display text-[28px] font-medium leading-none tracking-[-0.05em] text-ink sm:text-[34px]">{{ formatDay(selectedDay) }}</h2>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-1 font-mono text-[9px] font-semibold uppercase tracking-[0.08em]" :style="tone(selectedLoad)">{{ selectedLoad }} задач</span>
                                    <span class="text-[10px] text-muted">{{ workloadLabel }}</span>
                                </div>
                            </div>
                            <button ref="closeButton" type="button" class="rounded-full border border-line bg-white/60 p-2 text-muted transition hover:border-line-strong hover:text-ink" aria-label="Закрыть задачи дня" @click="closeTasks"><XMarkIcon class="h-4 w-4" /></button>
                        </div>
                    </header>

                    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6 sm:py-5">
                        <div v-if="tasksLoading" class="grid gap-2 sm:grid-cols-2">
                            <div v-for="i in 4" :key="i" class="h-[86px] animate-pulse rounded-[16px] border border-line/50 bg-surface-2/50"></div>
                        </div>
                        <div v-else-if="selectedTasks.length" class="grid gap-2 sm:grid-cols-2">
                            <Link v-for="task in selectedTasks" :key="task.id" :href="task.url" class="group flex min-h-[86px] flex-col justify-between gap-3 rounded-[16px] border border-line/70 bg-white/55 p-3.5 transition hover:-translate-y-0.5 hover:border-primary-300 hover:bg-primary-soft/25 hover:shadow-[0_8px_22px_rgba(83,61,130,.09)]">
                                <div class="flex items-start gap-2">
                                    <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" :style="{ backgroundColor: tone(selectedLoad).color }"></span>
                                    <p class="min-w-0 flex-1 text-[13px] font-semibold leading-snug text-ink">{{ task.title }}</p>
                                    <ArrowUpRightIcon class="h-3.5 w-3.5 shrink-0 text-faint transition group-hover:text-primary-600" />
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 pl-3.5">
                                    <span :class="['rounded-full px-2 py-0.5 text-[9px] font-semibold', statusTone(task.status)]">{{ taskStatusLabels[task.status] || task.status }}</span>
                                    <span class="font-mono text-[9px] text-faint">{{ taskPeriod(task) }}</span>
                                </div>
                            </Link>
                        </div>
                        <div v-else class="rounded-[18px] border border-dashed border-line px-5 py-10 text-center">
                            <p class="text-[12px] font-semibold text-ink">На эту дату задач нет</p>
                            <p class="mt-1 text-[10px] text-muted">Выберите другой день с заполненной шкалой нагрузки.</p>
                        </div>
                    </div>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>
