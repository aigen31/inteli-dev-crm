<script setup>
import { computed, ref, watch } from 'vue';
import { ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { loadTone } from '@/Utils/loadTones.js';

const props = defineProps({
    ranges: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const today = new Date();
const view = ref(startOfMonth(props.modelValue ? parseDate(props.modelValue) : today));
const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const closedStatuses = new Set(['completed', 'rejected_by_admin']);

watch(
    () => props.modelValue,
    (value) => {
        if (value) view.value = startOfMonth(parseDate(value));
    },
);

function parseDate(value) {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function startOfMonth(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function dateKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

const monthLabel = computed(() => {
    const label = new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric' }).format(view.value);

    return label.charAt(0).toUpperCase() + label.slice(1);
});

const counts = computed(() => {
    const map = {};

    props.ranges.forEach((range) => {
        if (!range?.start || !range?.end || closedStatuses.has(range.status)) return;

        const start = parseDate(range.start);
        const end = parseDate(range.end);
        const cursor = new Date(start);

        while (cursor <= end) {
            const key = dateKey(cursor);
            map[key] = (map[key] || 0) + 1;
            cursor.setDate(cursor.getDate() + 1);
        }
    });

    return map;
});

const cells = computed(() => {
    const first = new Date(view.value.getFullYear(), view.value.getMonth(), 1);
    const offset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(start.getDate() - offset);
    const todayKey = dateKey(new Date());

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        const key = dateKey(date);
        const load = counts.value[key] || 0;

        return {
            key,
            day: date.getDate(),
            current: date.getMonth() === view.value.getMonth(),
            today: key === todayKey,
            selected: key === props.modelValue,
            load,
        };
    });
});

const selectedLabel = computed(() => {
    if (!props.modelValue) return null;

    return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }).format(parseDate(props.modelValue));
});

function loadClass(cell) {
    if (!cell.current) return 'text-faint/60';

    return cell.selected ? 'bg-primary-600 text-white shadow-[0_4px_12px_rgba(83,61,130,.25)]' : 'text-ink hover:ring-1 hover:ring-primary-300';
}

function loadStyle(cell) {
    return cell.selected || !cell.current ? {} : loadTone(cell.load);
}

function select(cell) {
    if (cell.selected) {
        emit('update:modelValue', null);

        return;
    }

    emit('update:modelValue', cell.key);
}

function shift(delta) {
    view.value = new Date(view.value.getFullYear(), view.value.getMonth() + delta, 1);
}

function goToday() {
    view.value = startOfMonth(new Date());
}
</script>

<template>
    <section class="rounded-[18px] border border-line/70 bg-surface-2/35 p-3.5">
        <div class="flex flex-wrap items-center gap-2">
            <p class="eyebrow">Нагрузка по датам</p>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-muted transition hover:bg-ink/[0.06] hover:text-ink" aria-label="Предыдущий месяц" @click="shift(-1)">
                    <ChevronLeftIcon class="h-4 w-4" />
                </button>
                <span class="min-w-[128px] text-center text-[11px] font-semibold text-ink">{{ monthLabel }}</span>
                <button type="button" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-muted transition hover:bg-ink/[0.06] hover:text-ink" aria-label="Следующий месяц" @click="shift(1)">
                    <ChevronRightIcon class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div class="mt-3 grid grid-cols-7 gap-1 text-center">
            <span v-for="day in weekdays" :key="day" class="pb-1 font-mono text-[8px] uppercase tracking-[0.08em] text-faint">{{ day }}</span>
            <button
                v-for="cell in cells"
                :key="cell.key"
                type="button"
                :title="cell.load ? `${cell.day}: ${cell.load} задач` : `${cell.day}: свободно`"
                :aria-label="`${cell.key}, задач: ${cell.load}`"
                :aria-pressed="cell.selected"
                :class="['relative flex h-9 items-center justify-center rounded-[10px] text-[11px] font-semibold transition', loadClass(cell)]"
                :style="loadStyle(cell)"
                @click="select(cell)"
            >
                {{ cell.day }}
                <span v-if="cell.today" :class="['absolute bottom-1 h-1 w-1 rounded-full', cell.selected ? 'bg-white' : 'bg-primary-600']"></span>
            </button>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line/60 pt-2.5">
            <button type="button" class="text-[10px] font-semibold text-primary-700 hover:underline" @click="goToday">Сегодня</button>
            <div class="ml-auto flex items-center gap-1 text-[8px] text-muted">
                <span class="h-2 w-2 rounded-[2px]" :style="{ background: loadTone(1).background }"></span><span>спокойно</span>
                <span class="ml-1 h-2 w-2 rounded-[2px]" :style="{ background: loadTone(2).background }"></span>
                <span class="ml-1 h-2 w-2 rounded-[2px]" :style="{ background: loadTone(3).background }"></span>
                <span class="ml-1 h-2 w-2 rounded-[2px]" :style="{ background: loadTone(4).background }"></span><span>пик</span>
            </div>
        </div>

        <div v-if="selectedLabel" class="mt-2 flex items-center gap-2 rounded-[11px] bg-primary-soft/60 px-2.5 py-1.5">
            <span class="text-[10px] font-semibold text-primary-800">{{ selectedLabel }}</span>
            <span class="text-[9px] text-primary-700/70">{{ counts[modelValue] || 0 }} задач</span>
            <button type="button" class="ml-auto inline-flex items-center gap-1 text-[9px] font-semibold text-primary-700 hover:underline" @click="emit('update:modelValue', null)">
                <XMarkIcon class="h-3 w-3" />Сбросить
            </button>
        </div>
    </section>
</template>
