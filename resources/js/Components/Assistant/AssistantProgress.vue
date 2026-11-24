<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import axios from 'axios';
import { CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';

const props = defineProps({
    active: { type: Boolean, default: false },
    conversationId: { type: String, default: null },
});

const steps = ref([]);
const stage = ref('Разбираюсь в запросе');
const elapsed = ref(0);
const loaded = ref(false);
let timer = null;
let ticker = null;

const toolCount = computed(() => steps.value.filter((step) => step.name !== 'thinking').length);

async function poll() {
    if (!props.active || !props.conversationId) return;

    try {
        const { data } = await axios.get(route('assistant.conversations.progress', props.conversationId));
        steps.value = Array.isArray(data.steps) ? data.steps : [];
        stage.value = data.stage || 'Разбираюсь в запросе';
        elapsed.value = Math.floor(Number(data.elapsed_seconds) || 0);
        loaded.value = true;
    } catch {
        // Прогресс — вспомогательный слой: ответ всё равно придёт в чат.
    }
}

function stop() {
    if (timer !== null) window.clearInterval(timer);
    if (ticker !== null) window.clearInterval(ticker);
    timer = null;
    ticker = null;
}

function start() {
    stop();
    loaded.value = false;
    steps.value = [];
    stage.value = 'Разбираюсь в запросе';
    elapsed.value = 0;
    poll();
    timer = window.setInterval(poll, 1100);
    ticker = window.setInterval(() => {
        elapsed.value += 1;
    }, 1000);
}

watch(
    () => [props.active, props.conversationId],
    ([active, id]) => {
        if (active && id) start();
        else stop();
    },
    { immediate: true },
);

onBeforeUnmount(stop);

const elapsedLabel = computed(() => {
    if (elapsed.value < 60) return `${elapsed.value} с`;

    return `${Math.floor(elapsed.value / 60)} мин ${elapsed.value % 60} с`;
});
</script>

<template>
    <div class="flex items-start gap-2.5">
        <span class="relative mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center">
            <span class="absolute inset-0 animate-ping rounded-full bg-primary-400/20"></span>
            <span class="absolute inset-0 rounded-full bg-primary-soft/70"></span>
            <KikiAvatar variant="face" fit="contain" :size="28" class="relative" />
        </span>

        <div class="min-w-0 flex-1 rounded-[16px] border border-line/80 bg-white/55 px-3.5 py-3">
            <div class="flex items-center gap-2">
                <span class="flex items-end gap-[2px]" aria-hidden="true">
                    <span class="h-2 w-[3px] animate-pulse rounded-full bg-primary-500 [animation-delay:-0.4s]"></span>
                    <span class="h-3.5 w-[3px] animate-pulse rounded-full bg-primary-500 [animation-delay:-0.2s]"></span>
                    <span class="h-2.5 w-[3px] animate-pulse rounded-full bg-primary-500"></span>
                    <span class="h-4 w-[3px] animate-pulse rounded-full bg-primary-500 [animation-delay:-0.3s]"></span>
                </span>
                <p class="min-w-0 flex-1 truncate text-[11px] font-semibold text-ink">{{ stage }}</p>
                <span class="shrink-0 rounded-full bg-ink/[0.05] px-2 py-0.5 font-mono text-[9px] text-muted">{{ elapsedLabel }}</span>
            </div>

            <div v-if="!loaded" class="mt-2 space-y-1.5">
                <span class="block h-2 w-3/4 animate-pulse rounded-full bg-ink/[0.06]"></span>
                <span class="block h-2 w-1/2 animate-pulse rounded-full bg-ink/[0.06] [animation-delay:-0.3s]"></span>
            </div>

            <ol v-else class="mt-2 space-y-1">
                <li v-for="step in steps" :key="step.id" class="flex items-start gap-2 text-[10.5px] leading-relaxed">
                    <span class="mt-[2px] flex h-4 w-4 shrink-0 items-center justify-center rounded-full"
                        :class="step.status === 'running' ? 'bg-primary-soft text-primary-700' : step.status === 'error' ? 'bg-danger/10 text-danger-ink' : 'bg-sage/25 text-sage-700'">
                        <span v-if="step.status === 'running'" class="h-2.5 w-2.5 animate-spin rounded-full border-[1.5px] border-primary-500 border-t-transparent"></span>
                        <ExclamationTriangleIcon v-else-if="step.status === 'error'" class="h-2.5 w-2.5" />
                        <CheckIcon v-else class="h-2.5 w-2.5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span :class="['block truncate', step.status === 'running' ? 'font-semibold text-ink' : 'text-ink-soft']">{{ step.label }}</span>
                        <span v-if="step.detail" class="block truncate text-[9px] text-faint">{{ step.detail }}</span>
                    </span>
                    <span class="shrink-0 font-mono text-[8px] uppercase tracking-[0.08em]"
                        :class="step.status === 'running' ? 'text-primary-700' : step.status === 'error' ? 'text-danger' : 'text-faint'">
                        {{ step.status === 'running' ? 'сейчас' : step.status === 'error' ? 'ошибка' : 'готово' }}
                    </span>
                </li>
            </ol>

            <p v-if="toolCount > 0" class="mt-1.5 font-mono text-[8px] uppercase tracking-[0.1em] text-faint">
                инструментов: {{ toolCount }}
            </p>
        </div>
    </div>
</template>
