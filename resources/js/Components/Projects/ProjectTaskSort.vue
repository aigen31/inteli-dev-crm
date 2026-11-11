<script setup>
import { ArrowsUpDownIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

defineProps({
    modelValue: { type: String, default: 'newest' },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex items-center gap-2 rounded-full border border-line/80 bg-white/55 py-1 pl-2.5 pr-1 transition hover:border-line-strong">
        <ArrowsUpDownIcon class="h-3.5 w-3.5 text-muted" />
        <span class="hidden font-mono text-[8px] uppercase tracking-[0.1em] text-faint sm:inline">{{ $t('Сортировка') }}</span>
        <span class="relative inline-flex items-center">
            <select
                :value="modelValue"
                class="appearance-none bg-transparent py-1 pr-5 text-[10px] font-semibold text-ink-soft focus:outline-none"
                :aria-label="$t('Сортировка задач')"
                @change="emit('update:modelValue', $event.target.value)"
            >
                <option value="newest">{{ $t('Сначала новые') }}</option>
                <option value="status">{{ $t('По статусу') }}</option>
            </select>
            <ChevronDownIcon class="pointer-events-none absolute right-1 h-3 w-3 text-faint" />
        </span>
    </label>
</template>
