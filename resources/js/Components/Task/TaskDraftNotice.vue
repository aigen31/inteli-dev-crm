<script setup>
import { computed } from 'vue';
import { ClockIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    savedAt: { type: Date, default: null },
    restored: { type: Boolean, default: false },
});

const emit = defineEmits(['discard']);

const time = computed(() => {
    if (!props.savedAt) return '';

    return new Intl.DateTimeFormat('ru-RU', { hour: '2-digit', minute: '2-digit' }).format(props.savedAt);
});
</script>

<template>
    <div class="mb-4 flex flex-wrap items-center gap-2 rounded-[14px] border border-line/70 bg-white/40 px-3.5 py-2 text-[10px] text-muted">
        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-success/10 text-success"><ClockIcon class="h-3 w-3" /></span>
        <p class="min-w-0 flex-1">
            <template v-if="restored"><strong class="font-semibold text-ink">Черновик восстановлен.</strong> Продолжайте с места, где остановились.</template>
            <template v-else><strong class="font-semibold text-ink">Черновик сохранён.</strong> Последнее изменение в {{ time }}.</template>
        </p>
        <button type="button" class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-[9px] font-semibold text-faint transition hover:bg-danger/10 hover:text-danger-ink" @click="emit('discard')">
            <TrashIcon class="h-3 w-3" />
            Удалить черновик
        </button>
    </div>
</template>
