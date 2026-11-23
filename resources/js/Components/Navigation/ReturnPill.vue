<script setup>
import { computed } from 'vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { usePageHistory } from '@/Composables/usePageHistory';

const { previous } = usePageHistory();

const canGoBack = computed(() => typeof window !== 'undefined' && window.history.length > 1);
const visible = computed(() => canGoBack.value);
const label = computed(() => previous.value?.title || 'Назад');

function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    }
}
</script>

<template>
    <button
        v-if="visible"
        type="button"
        class="group flex max-w-[190px] items-center gap-2 overflow-hidden whitespace-nowrap rounded-full border border-line bg-surface-2/50 py-1.5 pl-2.5 pr-3 text-[12px] text-muted transition-all duration-200 hover:max-w-[360px] hover:border-line-strong hover:bg-surface-2/80 hover:text-ink"
        :title="label ? 'Назад: ' + label : 'Назад'"
        aria-label="Назад"
        @click="goBack"
    >
        <ArrowLeftIcon class="h-3.5 w-3.5 shrink-0 transition group-hover:-translate-x-0.5 group-hover:text-primary-600" />
        <span class="min-w-0 truncate text-[11px] font-semibold text-ink">{{ label }}</span>
    </button>
</template>
