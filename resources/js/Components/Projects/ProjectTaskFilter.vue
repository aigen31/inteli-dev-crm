<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { CheckIcon, ChevronDownIcon, FunnelIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { taskStatusLabels } from '@/Utils/constants.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    statuses: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);
const root = ref(null);
const open = ref(false);

const options = computed(() => [
    { value: '', label: 'Все статусы' },
    ...props.statuses.map((status) => ({ value: status, label: taskStatusLabels[status] || status })),
]);
const selected = computed(() => options.value.find((option) => option.value === props.modelValue) || options.value[0]);
const isFiltered = computed(() => Boolean(props.modelValue));

function choose(value) {
    emit('update:modelValue', value);
    open.value = false;
}

function onOutside(event) {
    if (root.value && !root.value.contains(event.target)) open.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onOutside);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onOutside);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative inline-flex">
        <button
            type="button"
            :class="[
                'inline-flex min-h-[34px] items-center gap-2 rounded-full border py-1 pl-2.5 pr-2 text-left transition',
                isFiltered
                    ? 'border-primary-300 bg-primary-50/80 text-primary-900'
                    : 'border-line/80 bg-white/55 text-ink-soft hover:border-line-strong',
            ]"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-label="$t('Фильтр по статусу')"
            @click="open = !open"
        >
            <FunnelIcon :class="['h-3.5 w-3.5 shrink-0', isFiltered ? 'text-primary-600' : 'text-muted']" />
            <span class="hidden font-mono text-[8px] uppercase tracking-[0.1em] sm:inline" :class="isFiltered ? 'text-primary-600' : 'text-faint'">{{ $t('Статус') }}</span>
            <span class="max-w-[130px] truncate text-[10px] font-semibold">{{ selected.label }}</span>
            <span v-if="isFiltered" class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-500"></span>
            <ChevronDownIcon :class="['h-3 w-3 shrink-0 transition-transform', open ? 'rotate-180 text-primary-600' : 'text-faint']" />
        </button>

        <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="-translate-y-1 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-1 opacity-0"
        >
            <div v-if="open" class="absolute right-0 top-full z-30 mt-2 w-[220px] overflow-hidden rounded-[16px] border border-line bg-surface shadow-[0_18px_50px_rgba(16,13,24,.16)]" role="listbox" aria-label="Статус задачи">
                <div class="flex items-center justify-between border-b border-line/70 bg-surface-2/50 px-3 py-2">
                    <p class="font-mono text-[8px] uppercase tracking-[0.12em] text-faint">{{ $t('Фильтр по статусу') }}</p>
                    <button v-if="isFiltered" type="button" class="inline-flex items-center gap-1 text-[9px] font-semibold text-muted transition hover:text-ink" @click="choose('')">
                        <XMarkIcon class="h-3 w-3" />
                        {{ $t('Сбросить') }}
                    </button>
                </div>
                <div class="max-h-[260px] overflow-y-auto p-1.5">
                    <button
                        v-for="option in options"
                        :key="option.value || 'all'"
                        type="button"
                        role="option"
                        :aria-selected="modelValue === option.value"
                        :class="[
                            'flex w-full items-center gap-2 rounded-[10px] px-2.5 py-2 text-left text-[11px] transition',
                            modelValue === option.value ? 'bg-primary-soft font-semibold text-primary-900' : 'text-ink-soft hover:bg-ink/[0.04]',
                        ]"
                        @click="choose(option.value)"
                    >
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center">
                            <CheckIcon v-if="modelValue === option.value" class="h-3.5 w-3.5 text-primary-600" />
                        </span>
                        <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                    </button>
                </div>
            </div>
        </transition>
    </div>
</template>
