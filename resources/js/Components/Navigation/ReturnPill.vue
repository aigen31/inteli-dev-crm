<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const page = usePage();

const primary = computed(() => props.items[0] ?? null);
const currentPath = computed(() => page.url.split('?')[0]);
const canGoBack = computed(() => typeof window !== 'undefined' && window.history.length > 1);
const visible = computed(() => canGoBack.value || (primary.value !== null && primary.value.url !== currentPath.value));

const label = computed(() => primary.value?.title || 'Назад');

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
        :title="primary ? 'Назад: ' + primary.title : 'Назад'"
        aria-label="Назад"
        @click="goBack"
    >
        <ArrowLeftIcon class="h-3.5 w-3.5 shrink-0 transition group-hover:-translate-x-0.5 group-hover:text-primary-600" />
        <span class="min-w-0 truncate text-[11px] font-semibold text-ink">{{ label }}</span>
    </button>
</template>
