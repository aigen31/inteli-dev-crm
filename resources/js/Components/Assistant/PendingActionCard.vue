<script setup>
import { CheckIcon, SparklesIcon } from '@heroicons/vue/24/outline';

defineProps({
    action: { type: Object, required: true },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm', 'reject']);
</script>

<template>
    <div class="rounded-[20px] border border-warning/30 bg-warning/[0.07] p-4">
        <div class="flex items-start gap-2.5">
            <SparklesIcon class="mt-0.5 h-4 w-4 shrink-0 text-warning" />
            <div class="min-w-0">
                <p class="text-[12px] font-semibold text-ink">Нужно подтверждение</p>
                <p class="mt-1 text-[13px] leading-relaxed text-ink-soft">{{ action.summary }}</p>
            </div>
        </div>
        <div class="mt-3 flex gap-2 pl-7">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full bg-ink px-3 py-1.5 text-[11px] font-semibold text-canvas transition hover:bg-primary-700 disabled:opacity-50"
                :disabled="busy"
                @click="emit('confirm', {})"
            >
                <CheckIcon class="h-3.5 w-3.5" />
                Подтвердить
            </button>
            <button
                type="button"
                class="rounded-full border border-line px-3 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink disabled:opacity-50"
                :disabled="busy"
                @click="emit('reject')"
            >
                Отмена
            </button>
        </div>
    </div>
</template>
