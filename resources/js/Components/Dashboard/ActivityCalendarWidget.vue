<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { taskStatusLabels } from '@/Utils/constants.js';

const props = defineProps({ compact: { type: Boolean, default: false } });
const root = ref(null);
const view = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1));
const counts = ref({});
const loading = ref(false);
const selectedDay = ref(null);
const selectedTasks = ref([]);
const tasksLoading = ref(false);
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
        const { data } = await axios.get(route('calendar.activity'), { params: { month: monthKey.value } });
        counts.value = data.days || {};
    } catch {
        counts.value = {};
    } finally {
        loading.value = false;
    }
}

async function selectDay(cell) {
    if (!cell.load) return;
    selectedDay.value = cell.key;
    selectedTasks.value = [];
    tasksLoading.value = true;
    try {
        const { data } = await axios.get(route('calendar.tasks'), { params: { all: 1, date: cell.key } });
        selectedTasks.value = data.tasks || [];
    } catch {
        selectedTasks.value = [];
    } finally {
        tasksLoading.value = false;
    }
}

function closeTasks() { selectedDay.value = null; }
function moveMonth(delta) { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + delta, 1); load(); }
function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
function tone(load) {
    if (!load) return { background: 'transparent', color: 'inherit' };
    if (load === 1) return { background: '#E8F3EC', color: '#2F8F62' };
    if (load === 2) return { background: '#FBF0D9', color: '#B7791F' };
    if (load === 3) return { background: '#F6D8C4', color: '#A96120' };
    return { background: '#F2C7C1', color: '#A33A33' };
}
function formatDay(key) { const [y,m,d] = key.split('-').map(Number); return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long' }).format(new Date(y, m-1, d)); }
onMounted(load);
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
            <span class="h-2 w-2 rounded-[2px]" style="background:#E8F3EC"></span><span>спокойно</span>
            <span class="h-2 w-2 rounded-[2px]" style="background:#FBF0D9"></span>
            <span class="h-2 w-2 rounded-[2px]" style="background:#F6D8C4"></span>
            <span class="h-2 w-2 rounded-[2px]" style="background:#F2C7C1"></span><span>пик</span>
        </div>
        <div v-if="selectedDay" class="absolute inset-x-2 bottom-2 z-20 rounded-[12px] border border-line bg-surface p-2 shadow-[0_12px_30px_rgba(16,13,24,.18)]">
            <div class="flex items-center justify-between gap-2"><p class="text-[9px] font-bold text-ink">{{ formatDay(selectedDay) }}</p><button type="button" class="rounded p-0.5 text-faint hover:text-ink" aria-label="Закрыть" @click="closeTasks"><XMarkIcon class="h-3 w-3" /></button></div>
            <p v-if="tasksLoading" class="mt-1 text-[8px] text-muted">Загружаю…</p>
            <div v-else-if="selectedTasks.length" class="mt-1 max-h-[86px] space-y-1 overflow-y-auto">
                <Link v-for="task in selectedTasks" :key="task.id" :href="task.url" class="block rounded-[6px] bg-surface-2/60 px-1.5 py-1 transition hover:bg-primary-soft/60">
                    <p class="truncate text-[8px] font-semibold text-ink">{{ task.title }}</p>
                    <p class="text-[7px] text-muted">{{ taskStatusLabels[task.status] || task.status }}</p>
                </Link>
            </div>
            <p v-else class="mt-1 text-[8px] text-muted">На эту дату задач нет.</p>
        </div>
    </div>
</template>
