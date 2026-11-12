<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { CalendarDaysIcon, ChevronDownIcon, ChevronLeftIcon, ChevronRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { validationMessage } from '@/Utils/validation';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Дата' },
    assigneeId: { type: String, default: null },
    taskId: { type: String, default: null },
    error: { type: String, default: null },
    placeholder: { type: String, default: 'Выберите дату' },
});

const emit = defineEmits(['update:modelValue']);
const root = ref(null);
const open = ref(false);
const view = ref(startOfMonth(parseDate(props.modelValue) || new Date()));

const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const intervals = ref([]);
const loading = ref(false);

async function loadIntervals() {
    if (!props.assigneeId && !props.taskId) {
        intervals.value = [];
        return;
    }

    loading.value = true;
    try {
        const params = props.taskId ? { task_id: props.taskId } : { assignee_id: props.assigneeId };
        const { data } = await axios.get(route('calendar.intervals'), { params });
        intervals.value = (data.intervals || []).map((item) => ({
            start: item.start?.slice(0, 10),
            end: item.end?.slice(0, 10),
        })).filter((item) => item.start && item.end);
    } catch {
        intervals.value = [];
    } finally {
        loading.value = false;
    }
}
const monthStartKey = computed(() => dateKey(new Date(view.value.getFullYear(), view.value.getMonth(), 1)));
const monthEndKey = computed(() => dateKey(new Date(view.value.getFullYear(), view.value.getMonth() + 1, 0)));
const monthIntervals = computed(() => intervals.value.filter((item) => item.start <= monthEndKey.value && item.end >= monthStartKey.value));
const monthLabel = computed(() => new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric' }).format(view.value));
const display = computed(() => props.modelValue ? new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }).format(parseDate(normalize(props.modelValue))) : props.placeholder);

const cells = computed(() => {
    const first = new Date(view.value.getFullYear(), view.value.getMonth(), 1);
    const offset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(start.getDate() - offset);
    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        return {
            date,
            key: dateKey(date),
            day: date.getDate(),
            current: date.getMonth() === view.value.getMonth(),
            today: dateKey(date) === dateKey(new Date()),
            selected: dateKey(date) === normalize(props.modelValue),
            busy: busyFor(dateKey(date)).length > 0,
        };
    });
});

function normalize(value) { return (value || '').slice(0, 10); }
function parseDate(value) {
    if (!value) return null;
    const [year, month, day] = value.slice(0, 10).split('-').map(Number);
    return year && month && day ? new Date(year, month - 1, day) : null;
}

function startOfMonth(date) { return new Date(date.getFullYear(), date.getMonth(), 1); }
function dateKey(date) { return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; }
function busyFor(key) { return intervals.value.filter((item) => item.start <= key && key <= item.end); }
function formatRange(item) {
    const start = parseDate(item.start);
    const end = parseDate(item.end);
    const fmt = new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit' });
    return item.start === item.end ? fmt.format(start) : `${fmt.format(start)} — ${fmt.format(end)}`;
}
function choose(cell) {
    emit('update:modelValue', cell.key);
    open.value = false;
}
function moveMonth(delta) { view.value = new Date(view.value.getFullYear(), view.value.getMonth() + delta, 1); }
function onOutside(event) { if (root.value && !root.value.contains(event.target)) open.value = false; }
function onKeydown(event) { if (event.key === 'Escape') open.value = false; }
function toggle() {
    if (!open.value) view.value = startOfMonth(parseDate(normalize(props.modelValue)) || new Date());
    open.value = !open.value;
    if (open.value) loadIntervals();
}

watch([() => props.assigneeId, () => props.taskId], () => {
    loadIntervals();
});

watch(() => props.modelValue, (value) => {
    const date = parseDate(normalize(value));
    if (date) view.value = startOfMonth(date);
});

onMounted(() => {
    loadIntervals();
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <label class="mb-1.5 block text-[11px] font-semibold text-ink-soft">{{ label }}</label>
        <button
            type="button"
            :class="[
                'flex h-10 w-full items-center gap-2 rounded-[13px] border bg-white/70 px-3.5 text-left text-[12px] transition focus:outline-none focus:ring-2',
                error ? 'border-danger focus:border-danger focus:ring-danger/15' : 'border-line focus:border-primary-400 focus:ring-primary-500/10',
            ]"
            :aria-expanded="open"
            :aria-label="`${label}: ${display}`"
            @click="toggle"
        >
            <CalendarDaysIcon class="h-4 w-4 shrink-0 text-muted" />
            <span :class="['min-w-0 flex-1 truncate', modelValue ? 'text-ink' : 'text-faint']">{{ display }}</span>
            <span v-if="modelValue" class="rounded-full p-1 text-faint transition hover:bg-ink/[0.06] hover:text-ink" aria-label="Очистить дату" @click.stop="emit('update:modelValue', '')"><XMarkIcon class="h-3 w-3" /></span>
            <ChevronDownIcon :class="['h-3.5 w-3.5 shrink-0 text-faint transition', open ? 'rotate-180' : '']" />
        </button>
        <p v-if="error" class="mt-1 text-[10px] text-danger-ink">{{ validationMessage(error) }}</p>

        <transition enter-active-class="transition duration-100 ease-out" enter-from-class="-translate-y-1 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-75 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="-translate-y-1 opacity-0">
            <div v-if="open" class="absolute left-0 top-full z-[60] mt-2 w-[340px] max-w-[calc(100vw-32px)] overflow-hidden rounded-[20px] border border-line bg-surface shadow-[0_22px_60px_rgba(16,13,24,.18)]" @click.stop>
                <div class="flex items-center justify-between border-b border-line/70 bg-surface-2/45 px-3 py-2.5">
                    <button type="button" class="rounded-lg p-1.5 text-muted transition hover:bg-white/60 hover:text-ink" aria-label="Предыдущий месяц" @click="moveMonth(-1)"><ChevronLeftIcon class="h-4 w-4" /></button>
                    <p class="text-[11px] font-bold capitalize text-ink">{{ monthLabel }}</p>
                    <button type="button" class="rounded-lg p-1.5 text-muted transition hover:bg-white/60 hover:text-ink" aria-label="Следующий месяц" @click="moveMonth(1)"><ChevronRightIcon class="h-4 w-4" /></button>
                </div>
                <div class="p-3">
                    <div class="mb-1 grid grid-cols-7">
                        <span v-for="day in weekdays" :key="day" class="py-1 text-center font-mono text-[8px] uppercase tracking-[0.08em] text-faint">{{ day }}</span>
                    </div>
                    <div class="grid grid-cols-7 gap-1">
                        <button
                            v-for="cell in cells"
                            :key="cell.key"
                            type="button"
                            :class="[
                                'relative flex h-9 items-center justify-center rounded-[10px] text-[11px] transition',
                                !cell.current && 'text-faint/55',
                                cell.selected && 'bg-primary-600 font-semibold text-white',
                                !cell.selected && cell.busy && 'bg-[#FBF0E6] font-semibold text-warning',
                                !cell.selected && !cell.busy && 'text-ink-soft hover:bg-primary-soft/60',
                                cell.today && !cell.selected && 'ring-1 ring-inset ring-primary-300',
                            ]"
                            :aria-label="`${cell.day}${cell.busy ? ', есть занятые задачи' : ''}`"
                            @click="choose(cell)"
                        >
                            {{ cell.day }}
                            <span v-if="cell.busy" :class="['absolute bottom-1 h-1 w-1 rounded-full', cell.selected ? 'bg-white/80' : 'bg-warning']"></span>
                        </button>
                    </div>
                    <div class="mt-3 flex items-center gap-3 border-t border-line/60 pt-2 text-[8px] text-faint">
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-[3px] bg-[#FBF0E6]"></span>занято</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-[3px] bg-primary-600"></span>выбрано</span>
                        <span class="ml-auto">можно выбрать любую дату</span>
                    </div>
                    <div v-if="monthIntervals.length" class="mt-3 max-h-[128px] space-y-1 overflow-y-auto">
                        <p class="font-mono text-[8px] uppercase tracking-[0.1em] text-faint">Занятые интервалы</p>
                        <div v-for="item in monthIntervals" :key="item.start + item.end" class="flex items-center gap-2 rounded-[9px] bg-surface-2/55 px-2 py-1.5">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-warning"></span>
                            <span class="shrink-0 font-mono text-[8px] text-ink-soft">{{ formatRange(item) }}</span>
                            <span class="ml-auto text-[8px] text-faint">{{ loading ? 'обновляю…' : '' }}</span>
                        </div>
                    </div>
                    <p v-else class="mt-3 rounded-[9px] bg-surface-2/45 px-2 py-1.5 text-[9px] text-muted">Нет занятых интервалов для выбранного исполнителя.</p>
                </div>
            </div>
        </transition>
    </div>
</template>
