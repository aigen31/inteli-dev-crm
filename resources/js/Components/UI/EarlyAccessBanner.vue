<script setup>
import { onMounted, ref } from 'vue';
import { ExclamationTriangleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const STORAGE_KEY = 'inteli-dev-early-access-dismissed';
const visible = ref(true);

onMounted(() => {
    visible.value = window.localStorage.getItem(STORAGE_KEY) !== '1';
});

function dismiss() {
    visible.value = false;
    window.localStorage.setItem(STORAGE_KEY, '1');
}
</script>

<template>
    <div
        v-if="visible"
        class="mb-5 flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-[14px] border border-warning/20 bg-warning/[0.08] px-3.5 py-2.5 text-[10px] leading-relaxed text-ink-soft"
        role="status"
        aria-live="polite"
    >
        <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0 text-warning" />
        <p class="min-w-0 flex-1">
            <strong class="font-semibold text-ink">Ранний доступ.</strong>
            Приложение в бете — возможны ошибки и перебои. Важные данные дублируйте.
        </p>
        <a
            href="https://t.me/AiGen31"
            target="_blank"
            rel="noopener noreferrer"
            class="shrink-0 font-semibold text-warning underline decoration-warning/40 underline-offset-2 transition hover:text-ink"
        >
            Сообщить об ошибке
        </a>
        <button
            type="button"
            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-muted transition hover:bg-ink/[0.05] hover:text-ink"
            aria-label="Скрыть предупреждение о раннем доступе"
            title="Скрыть"
            @click="dismiss"
        >
            <XMarkIcon class="h-3.5 w-3.5" />
        </button>
    </div>
</template>
