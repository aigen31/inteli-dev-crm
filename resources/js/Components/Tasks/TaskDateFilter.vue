<script setup>
import { computed } from 'vue';
import { CalendarDaysIcon, ChevronDownIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { formatDate } from '@/Utils/formatters.js';
import { loadTone } from '@/Utils/loadTones.js';

const props = defineProps({
    modelValue: { type: String, default: null },
    ranges: { type: Array, default: () => [] },
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'toggle']);

const closedStatuses = new Set(['completed', 'rejected_by_admin']);

function parseDate(value) {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function dateKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/// Семь дней вперёд: маленькая полоска нагрузки внутри кнопки, чтобы день
/// выбирали по загрузке, а не наугад. Считаем те же диапазоны, что и календарь.
const week = computed(() => {
    const counts = {};

    props.ranges.forEach((range) => {
        if (!range?.start || !range?.end || closedStatuses.has(range.status)) return;

        const start = parseDate(range.start);
        const end = parseDate(range.end);
        const cursor = new Date(start);

        while (cursor <= end) {
            const key = dateKey(cursor);
            counts[key] = (counts[key] || 0) + 1;
            cursor.setDate(cursor.getDate() + 1);
        }
    });

    const today = new Date();

    return Array.from({ length: 7 }, (_, index) => {
        const date = new Date(today.getFullYear(), today.getMonth(), today.getDate() + index);
        const load = counts[dateKey(date)] || 0;

        return {
            key: dateKey(date),
            load,
            height: 4 + Math.min(load, 6) * 2,
            color: load ? loadTone(load).color : 'currentColor',
        };
    });
});

const hasWeekLoad = computed(() => week.value.some((day) => day.load > 0));
</script>

<template>
    <div class="task-date-filter relative min-w-0">
        <button
            type="button"
            class="group flex h-10 w-full items-center gap-2 rounded-[13px] border px-2.5 text-left transition"
            :class="
                open || modelValue
                    ? 'border-primary-300 bg-primary-soft/45 ring-2 ring-primary-500/10'
                    : 'border-line bg-white/70 hover:border-primary-300 hover:bg-white'
            "
            :aria-expanded="open"
            aria-haspopup="dialog"
            aria-controls="task-date-calendar"
            @click="emit('toggle')"
        >
            <span
                class="grid h-7 w-7 shrink-0 place-items-center rounded-[9px] transition"
                :class="open || modelValue ? 'bg-primary-600 text-white' : 'bg-surface-2/80 text-primary-700 group-hover:bg-primary-soft'"
            >
                <CalendarDaysIcon class="h-4 w-4" />
            </span>

            <span class="min-w-0 flex-1">
                <span class="block font-mono text-[8px] uppercase tracking-[0.12em] text-faint">Дата</span>
                <span class="block truncate text-[11px] font-semibold leading-tight text-ink">
                    {{ modelValue ? formatDate(modelValue) : 'Любой день' }}
                </span>
            </span>

            <span v-if="hasWeekLoad" class="hidden h-4 items-end gap-[2px] opacity-80 sm:flex" aria-hidden="true">
                <i
                    v-for="day in week"
                    :key="day.key"
                    class="w-[3px] rounded-[1px]"
                    :style="{ height: `${day.height}px`, background: day.color }"
                ></i>
            </span>

            <ChevronDownIcon class="h-3.5 w-3.5 shrink-0 text-faint transition" :class="{ 'rotate-180 text-primary-700': open }" />
        </button>

        <button
            v-if="modelValue"
            type="button"
            class="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full border border-line bg-white text-muted shadow-sm transition hover:border-primary-300 hover:text-primary-700"
            aria-label="Сбросить дату"
            @click.stop="emit('update:modelValue', null)"
        >
            <XMarkIcon class="h-3 w-3" />
        </button>
    </div>
</template>
