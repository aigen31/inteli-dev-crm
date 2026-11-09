<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { formatRelativeTime } from '@/Utils/formatters.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const page = usePage();

const primary = computed(() => props.items[0] ?? null);
const currentPath = computed(() => page.url.split('?')[0]);
const visible = computed(() => primary.value !== null && primary.value.url !== currentPath.value);

const colorDot = {
    violet: 'bg-primary-500',
    sage: 'bg-sage',
    sky: 'bg-sky',
    peach: 'bg-peach',
    ink: 'bg-ink',
};

const dotClass = computed(() => colorDot[primary.value?.project?.color] || colorDot.violet);
const label = computed(() => primary.value?.reference || primary.value?.title || '');
const relative = computed(() => (primary.value ? formatRelativeTime(primary.value.visited_at, 'short') : ''));
</script>

<template>
    <Link
        v-if="visible"
        :href="primary.url"
        class="group flex max-w-[190px] items-center gap-2 overflow-hidden whitespace-nowrap rounded-full border border-line bg-white/50 py-1.5 pl-2.5 pr-3 text-[12px] text-muted transition-all duration-200 hover:max-w-[360px] hover:border-line-strong hover:bg-white/80 hover:text-ink"
        :title="'Вернуться: ' + primary.title"
    >
        <ArrowLeftIcon class="h-3.5 w-3.5 shrink-0 transition group-hover:-translate-x-0.5 group-hover:text-primary-600" />
        <span :class="['h-1.5 w-1.5 shrink-0 rounded-full', dotClass]"></span>
        <span class="max-w-[110px] shrink-0 truncate text-[11px] font-semibold text-ink">{{ label }}</span>
        <span class="shrink-0 font-mono text-[9px] text-faint group-hover:hidden">{{ relative }}</span>
        <span class="hidden truncate text-[11px] font-medium text-ink-soft group-hover:inline">{{ primary.title }}</span>
    </Link>
</template>
