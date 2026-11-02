<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import {
    ArrowPathIcon,
    CheckIcon,
    PaperAirplaneIcon,
    SparklesIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import KikiChatAvatar from '@/Components/Kiki/KikiChatAvatar.vue';
import { useAssistant } from '@/Composables/useAssistant';

const page = usePage();
const { state, close } = useAssistant();

const booted = ref(false);
const loading = ref(false);
const messages = ref([]);
const pendingActions = ref([]);
const suggestions = ref([]);
const conversationId = ref(null);
const input = ref('');
const error = ref(null);
const scroller = ref(null);
const composer = ref(null);

const hasMessages = computed(() => messages.value.length > 0);
const canSend = computed(() => input.value.trim().length > 0 && !loading.value);

marked.setOptions({ breaks: true, gfm: true });

function renderMarkdown(text) {
    return DOMPurify.sanitize(marked.parse(text ?? ''), { USE_PROFILES: { html: true } });
}

function formatTime(value) {
    if (!value) return '';
    return new Date(value).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
}

function scrollToBottom() {
    nextTick(() => {
        if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
    });
}

function applyPayload(data) {
    messages.value = data.messages ?? [];
    pendingActions.value = data.pending_actions ?? [];
    suggestions.value = data.suggestions ?? suggestions.value;
    conversationId.value = data.conversation_id ?? conversationId.value;
    error.value = null;
    scrollToBottom();
}

async function load() {
    if (booted.value) return;
    booted.value = true;

    try {
        const { data } = await axios.get(route('assistant.show'));
        applyPayload(data);
    } catch (exception) {
        error.value = 'Не удалось загрузить диалог. Попробуйте ещё раз.';
    }
}

async function send(text = input.value) {
    const message = text.trim();
    if (!message || loading.value) return;

    input.value = '';
    error.value = null;
    loading.value = true;
    messages.value.push({
        id: `local-${Date.now()}`,
        role: 'user',
        content: message,
        created_at: new Date().toISOString(),
    });
    scrollToBottom();

    try {
        const { data } = await axios.post(route('assistant.chat'), {
            message,
            conversation_id: conversationId.value,
            context: {
                url: page.url,
                page: page.component,
            },
        });
        applyPayload(data);

        if (data.message?.content) {
            // payload already includes the message; this keeps the panel scrolled to the answer
            scrollToBottom();
        }
    } catch (exception) {
        const apiMessage = exception?.response?.data?.message;
        error.value = apiMessage || 'Кики не ответила. Проверьте соединение и попробуйте снова.';
    } finally {
        loading.value = false;
        composer.value?.focus();
    }
}

async function confirmAction(action) {
    loading.value = true;
    error.value = null;

    try {
        const { data } = await axios.post(route('assistant.confirm', action.id));
        applyPayload(data);
    } catch (exception) {
        error.value = exception?.response?.data?.message || 'Не удалось выполнить действие.';
    } finally {
        loading.value = false;
    }
}

async function rejectAction(action) {
    loading.value = true;

    try {
        const { data } = await axios.post(route('assistant.reject', action.id));
        applyPayload(data);
    } catch (exception) {
        error.value = 'Не удалось отменить действие.';
    } finally {
        loading.value = false;
    }
}

async function clearChat() {
    if (!window.confirm('Очистить историю диалога с Кики?')) return;

    await axios.delete(route('assistant.clear'));
    messages.value = [];
    pendingActions.value = [];
    conversationId.value = null;
    error.value = null;
    booted.value = false;
    await load();
}

function onKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        state.open = true;
    }

    if (event.key === 'Escape' && state.open) {
        close();
    }
}

function onComposerKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send();
    }
}

watch(
    () => state.open,
    (open) => {
        if (open) {
            load();
            scrollToBottom();
            nextTick(() => composer.value?.focus());
        }
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="state.open" class="pointer-events-none fixed inset-0 z-[70]">
            <div class="pointer-events-auto absolute inset-0 bg-ink/25 backdrop-blur-[2px] lg:hidden" @click="close"></div>

            <aside
                class="pointer-events-auto absolute inset-x-0 bottom-0 top-0 flex flex-col overflow-hidden border-line bg-surface shadow-[0_30px_90px_rgba(28,24,35,0.28)] lg:inset-y-3 lg:left-auto lg:right-3 lg:w-[430px] lg:rounded-[30px] lg:border"
            >
                <header class="relative shrink-0 overflow-hidden border-b border-line px-4 pb-3.5 pt-4">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-surface to-surface"></div>
                    <div class="relative flex items-center gap-3">
                        <KikiChatAvatar :size="42" />
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="text-[14px] font-bold text-ink">Кики</p>
                                <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                            </div>
                            <p class="text-[11px] text-muted">ассистент inteli dev</p>
                        </div>
                        <div class="ml-auto flex items-center gap-1">
                            <button type="button" class="rounded-xl p-2 text-muted transition hover:bg-ink/[0.05] hover:text-ink" title="Очистить диалог" @click="clearChat">
                                <TrashIcon class="h-4 w-4" />
                            </button>
                            <button type="button" class="rounded-xl p-2 text-muted transition hover:bg-ink/[0.05] hover:text-ink" title="Закрыть" @click="close">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </header>

                <div ref="scroller" class="flex-1 overflow-y-auto px-4 py-4">
                    <div v-if="!hasMessages && !loading" class="flex h-full flex-col items-center justify-center px-2 text-center">
                        <img
                            src="/images/kiki/kiki-seated.webp"
                            alt="Кики — ассистент inteli dev"
                            class="pointer-events-none h-[300px] w-full max-w-[390px] select-none object-contain"
                        />
                        <h2 class="mt-3 font-display text-[22px] font-medium tracking-[-0.03em] text-ink">Чем помочь?</h2>
                        <p class="mt-2 max-w-[300px] text-[13px] leading-relaxed text-muted">
                            Я знаю ваши задачи, клиентов и счета. Спросите словами — покажу, найду или сделаю.
                        </p>
                        <div class="mt-6 flex w-full flex-wrap justify-center gap-2">
                            <button
                                v-for="suggestion in suggestions"
                                :key="suggestion"
                                type="button"
                                class="rounded-full border border-line bg-white/60 px-3 py-1.5 text-[11px] font-medium text-ink-soft transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-800"
                                @click="send(suggestion)"
                            >
                                {{ suggestion }}
                            </button>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="message in messages" :key="message.id">
                            <div v-if="message.role === 'user'" class="flex justify-end">
                                <div class="max-w-[86%] rounded-[20px] rounded-br-md bg-primary-500 px-4 py-2.5 text-[13px] leading-relaxed text-white">
                                    {{ message.content }}
                                </div>
                            </div>
                            <div v-else class="flex items-start gap-2.5">
                                <KikiChatAvatar :size="28" class="mt-0.5" />
                                <div class="min-w-0 max-w-[86%]">
                                    <div class="prose-kiki rounded-[20px] rounded-tl-md border border-line bg-white/60 px-4 py-2.5" v-html="renderMarkdown(message.content)"></div>
                                    <p v-if="message.created_at" class="mt-1 pl-1 font-mono text-[9px] text-faint">{{ formatTime(message.created_at) }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="pendingActions.length" class="space-y-2">
                            <div
                                v-for="action in pendingActions"
                                :key="action.id"
                                class="rounded-[20px] border border-warning/30 bg-warning/[0.07] p-4"
                            >
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
                                        class="inline-flex items-center gap-1.5 rounded-full bg-ink px-3 py-1.5 text-[11px] font-semibold text-canvas transition hover:bg-primary-700"
                                        @click="confirmAction(action)"
                                    >
                                        <CheckIcon class="h-3.5 w-3.5" />
                                        Подтвердить
                                    </button>
                                    <button type="button" class="rounded-full border border-line px-3 py-1.5 text-[11px] font-medium text-muted transition hover:text-ink" @click="rejectAction(action)">
                                        Отмена
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="loading" class="flex items-center gap-2.5">
                            <KikiChatAvatar :size="28" />
                            <div class="flex items-center gap-1 rounded-full border border-line bg-white/60 px-4 py-3">
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-400 [animation-delay:-0.2s]"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-400 [animation-delay:-0.1s]"></span>
                                <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-primary-400"></span>
                            </div>
                        </div>
                    </div>

                    <div v-if="error" class="mt-3 rounded-2xl border border-danger/20 bg-danger/[0.06] px-3.5 py-2.5 text-[12px] text-danger-ink">
                        {{ error }}
                    </div>
                </div>

                <footer class="shrink-0 border-t border-line bg-surface/95 px-3 pb-[max(12px,env(safe-area-inset-bottom))] pt-3">
                    <div class="flex items-end gap-2 rounded-[22px] border border-line bg-white/70 p-1.5 pl-3 transition focus-within:border-primary-300 focus-within:ring-2 focus-within:ring-primary-500/10">
                        <textarea
                            ref="composer"
                            v-model="input"
                            rows="1"
                            class="max-h-32 min-h-[38px] flex-1 resize-none border-0 bg-transparent py-2 text-[13px] text-ink placeholder-faint focus:outline-none focus:ring-0"
                            placeholder="Спросите Кики…"
                            @keydown="onComposerKeydown"
                        ></textarea>
                        <button
                            type="button"
                            :disabled="!canSend"
                            class="mb-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-500 text-white transition hover:bg-primary-600 disabled:opacity-35"
                            title="Отправить"
                            @click="send()"
                        >
                            <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                            <PaperAirplaneIcon v-else class="h-4 w-4 -translate-x-px" />
                        </button>
                    </div>
                    <p class="mt-1.5 text-center font-mono text-[9px] uppercase tracking-[0.12em] text-faint">
                        Enter — отправить · Shift+Enter — перенос · Кики может ошибаться
                    </p>
                </footer>
            </aside>
        </div>
    </Teleport>
</template>
