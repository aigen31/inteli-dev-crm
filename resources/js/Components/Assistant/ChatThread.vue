<script setup>
import { nextTick, ref, watch } from 'vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import MessageBubble from '@/Components/Assistant/MessageBubble.vue';
import PendingActionCard from '@/Components/Assistant/PendingActionCard.vue';
import FormActionCard from '@/Components/Assistant/FormActionCard.vue';

const props = defineProps({
    messages: { type: Array, default: () => [] },
    pendingActions: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
    canRegenerate: { type: Boolean, default: false },
    error: { type: String, default: null },
    fieldErrors: { type: Object, default: () => ({}) },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['send', 'confirm', 'reject', 'regenerate']);

const scroller = ref(null);
const hasMessages = ref(false);

hasMessages.value = props.messages.length > 0;

watch(
    () => [props.messages.length, props.pendingActions.length, props.loading],
    () => {
        hasMessages.value = props.messages.length > 0;

        if (!hasMessages.value) {
            return;
        }

        nextTick(() => {
            if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
        });
    },
    { immediate: true },
);

function lastAssistantId() {
    for (let index = props.messages.length - 1; index >= 0; index -= 1) {
        if (props.messages[index].role === 'assistant') {
            return props.messages[index].id;
        }
    }

    return null;
}
</script>

<template>
    <div ref="scroller" class="h-full overflow-y-auto overscroll-contain px-4 py-4">
        <div v-if="!hasMessages && !loading" class="flex min-h-full flex-col items-center justify-center px-2 py-8 text-center">
            <img
                src="/images/kiki/kiki-seated.webp"
                alt="Кики — ассистент inteli dev"
                class="pointer-events-none w-full max-w-[360px] select-none object-contain"
                :class="compact ? 'h-[180px]' : 'h-[230px]'"
            />
            <h2 class="font-display font-medium tracking-[-0.03em] text-ink" :class="compact ? 'mt-2 text-[18px]' : 'mt-3 text-[22px]'">
                Чем помочь?
            </h2>
            <p class="mt-2 max-w-[340px] text-[13px] leading-relaxed text-muted">
                Я знаю ваши задачи, клиентов и счета. Спросите словами, прикрепите скриншот — покажу, найду или оформлю заявку.
            </p>
            <div class="mt-5 flex w-full flex-wrap justify-center gap-2">
                <button
                    v-for="suggestion in suggestions.slice(0, 4)"
                    :key="suggestion"
                    type="button"
                    class="rounded-full border border-line bg-white/60 px-3 py-1.5 text-[11px] font-medium text-ink-soft transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-800"
                    @click="emit('send', suggestion)"
                >
                    {{ suggestion }}
                </button>
            </div>

            <div v-if="error" class="mt-5 w-full max-w-[420px] rounded-2xl border border-danger/20 bg-danger/[0.06] px-3.5 py-2.5 text-left text-[12px] text-danger-ink">
                {{ error }}
            </div>
        </div>

        <div v-else class="mx-auto max-w-[760px] space-y-4">
            <MessageBubble
                v-for="message in messages"
                :key="message.id"
                :message="message"
                :can-regenerate="canRegenerate && message.id === lastAssistantId()"
                :busy="busy"
                @regenerate="emit('regenerate')"
            />

            <template v-for="action in pendingActions" :key="action.id">
                <FormActionCard
                    v-if="action.type === 'form' && action.form"
                    :action="action"
                    :busy="busy"
                    :errors="fieldErrors"
                    @confirm="(payload) => emit('confirm', action, payload)"
                    @reject="emit('reject', action)"
                />
                <PendingActionCard
                    v-else
                    :action="action"
                    :busy="busy"
                    @confirm="(payload) => emit('confirm', action, payload)"
                    @reject="emit('reject', action)"
                />
            </template>

            <div v-if="loading" class="flex items-center gap-2.5">
                <KikiAvatar variant="face" fit="contain" :size="28" />
                <div class="flex items-center gap-1 rounded-full border border-line bg-white/60 px-4 py-3">
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-400 [animation-delay:-0.2s]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-400 [animation-delay:-0.1s]"></span>
                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-400"></span>
                </div>
            </div>

            <div v-if="error" class="rounded-2xl border border-danger/20 bg-danger/[0.06] px-3.5 py-2.5 text-[12px] text-danger-ink">
                {{ error }}
            </div>
        </div>
    </div>
</template>
