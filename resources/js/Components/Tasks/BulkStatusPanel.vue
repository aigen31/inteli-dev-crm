<script setup>
import { computed, ref, watch } from 'vue';
import Button from '@/Components/UI/Button.vue';
import { CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    selectedTasks: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['apply', 'clear']);

const status = ref('');
const comment = ref('');

const targets = computed(() => {
    const rows = new Map();

    for (const task of props.selectedTasks) {
        for (const transition of task.allowed_transitions || []) {
            const current = rows.get(transition.value) || { ...transition, count: 0 };
            current.count += 1;
            rows.set(transition.value, current);
        }
    }

    return [...rows.values()].sort((a, b) => b.count - a.count);
});

const eligible = computed(() => targets.value.find((target) => target.value === status.value)?.count || 0);

watch(
    targets,
    (next) => {
        if (!next.some((target) => target.value === status.value)) {
            status.value = next[0]?.value || '';
        }
    },
    { immediate: true },
);

function apply() {
    if (!status.value) return;
    emit('apply', { status: status.value, comment: comment.value });
}

defineExpose({
    reset() {
        status.value = '';
        comment.value = '';
    },
});
</script>

<template>
    <transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-5 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0"
    >
        <div class="fixed inset-x-3 bottom-4 z-40 mx-auto max-w-[920px] sm:inset-x-6 sm:bottom-6">
            <section class="overflow-hidden rounded-[24px] border border-white/10 bg-ink text-canvas shadow-[0_24px_70px_rgba(15,12,21,.35)]">
                <div class="flex items-center gap-3 border-b border-white/10 px-4 py-3 sm:px-5">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-500 text-[12px] font-bold text-white">{{ selectedTasks.length }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12px] font-semibold text-canvas">Задачи выбраны</p>
                        <p class="text-[9px] text-canvas/55">Пакетное изменение статуса · без перезагрузки фильтров</p>
                    </div>
                    <button type="button" class="rounded-full p-1.5 text-canvas/55 transition hover:bg-white/10 hover:text-canvas" aria-label="Снять выделение" @click="emit('clear')">
                        <XMarkIcon class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="targets.length" class="p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-canvas/45">Новый статус</p>
                        <p class="font-mono text-[8px] text-canvas/45">{{ $t('доступно') }} {{ eligible }} / {{ selectedTasks.length }}</p>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="target in targets"
                            :key="target.value"
                            type="button"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[10px] font-semibold transition',
                                status === target.value
                                    ? 'border-primary-300 bg-primary-500 text-white'
                                    : 'border-white/10 bg-white/[0.06] text-canvas/70 hover:border-white/25 hover:text-canvas',
                            ]"
                            @click="status = target.value"
                        >
                            {{ target.label }}
                            <span :class="['rounded-full px-1.5 py-0.5 font-mono text-[8px]', status === target.value ? 'bg-white/20' : 'bg-white/10']">{{ target.count }}</span>
                        </button>
                    </div>

                    <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                        <input
                            v-model="comment"
                            type="text"
                            maxlength="1000"
                            placeholder="Комментарий к переходу (необязательно)"
                            class="h-9 min-w-0 flex-1 rounded-[11px] border border-white/10 bg-white/[0.06] px-3 text-[11px] text-canvas placeholder:text-canvas/35 focus:border-primary-300 focus:outline-none"
                        />
                        <Button class="shrink-0" :disabled="processing || !status || !eligible" @click="apply">
                            <CheckCircleIcon class="h-4 w-4" />
                            {{ processing ? 'Применяю…' : 'Применить статус' }}
                        </Button>
                    </div>
                    <p v-if="errors.task_ids" class="mt-2 text-[10px] text-danger-ink">{{ errors.task_ids }}</p>
                    <p v-if="errors.status" class="mt-2 text-[10px] text-danger-ink">{{ errors.status }}</p>
                    <p v-if="eligible < selectedTasks.length" class="mt-2 text-[9px] leading-relaxed text-canvas/45">
                        {{ selectedTasks.length - eligible }} · {{ $t('У задач нет такого перехода — они останутся без изменений.') }}
                    </p>
                </div>

                <div v-else class="px-4 py-4 text-[10px] leading-relaxed text-canvas/55 sm:px-5">
                    Для выбранных задач нет доступных переходов статуса.
                </div>
            </section>
        </div>
    </transition>
</template>
