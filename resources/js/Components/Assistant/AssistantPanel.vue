<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { ArrowTopRightOnSquareIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import ChatThread from '@/Components/Assistant/ChatThread.vue';
import Composer from '@/Components/Assistant/Composer.vue';
import { useAssistant } from '@/Composables/useAssistant';
import { useAssistantChat } from '@/Composables/useAssistantChat';

const page = usePage();
const { state, close } = useAssistant();

const booted = ref(false);
const composer = ref(null);

const {
    conversationId,
    session,
    messages,
    pendingActions,
    suggestions,
    draft,
    attachments,
    loading,
    uploading,
    error,
    fieldErrors,
    canSend,
    applyPayload,
    send,
    regenerate,
    confirmAction,
    rejectAction,
    uploadFiles,
    removeAttachment,
    reset,
} = useAssistantChat({
    context: () => ({ url: page.url, page: page.component }),
});

const promptSuggestions = computed(() => (suggestions.value?.length ? suggestions.value : []));
const fullPageUrl = computed(() =>
    conversationId.value
        ? `${route('assistant.index')}?c=${encodeURIComponent(conversationId.value)}`
        : route('assistant.index'),
);

async function load() {
    if (booted.value) return;
    booted.value = true;

    try {
        const { data } = await axios.get(route('assistant.state'));
        applyPayload(data);
    } catch {
        booted.value = false;
    }
}

async function submit(text = draft.value) {
    await send(text);
}

async function onConfirm(action, overrides) {
    await confirmAction(action, overrides);
}

async function onReject(action) {
    await rejectAction(action);
}

async function onRegenerate() {
    await regenerate();
}

function newChat() {
    reset();
    booted.value = true;
    nextTick(() => composer.value?.focus());
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

watch(
    () => state.open,
    (open) => {
        if (open) {
            if (!booted.value || (messages.value.length === 0 && !loading.value)) {
                booted.value = false;
                load();
            }
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
                class="pointer-events-auto absolute inset-x-0 bottom-0 top-0 flex flex-col overflow-hidden border-line bg-surface shadow-[0_30px_90px_rgba(28,24,35,0.28)] lg:inset-y-3 lg:left-auto lg:right-3 lg:w-[440px] lg:rounded-[30px] lg:border"
            >
                <header class="relative shrink-0 overflow-hidden border-b border-line px-4 pb-3 pt-3.5">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-surface to-surface"></div>
                    <div class="relative flex items-center gap-3">
                        <KikiAvatar variant="face" fit="contain" :size="40" />
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <p class="truncate text-[14px] font-bold text-ink">{{ session?.title || 'Кики' }}</p>
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-success"></span>
                            </div>
                            <p class="text-[11px] text-muted">ассистент inteli dev</p>
                        </div>
                        <div class="ml-auto flex items-center gap-1">
                            <button type="button" class="rounded-xl p-2 text-muted transition hover:bg-ink/[0.05] hover:text-ink" title="Новый диалог" @click="newChat">
                                <PlusIcon class="h-4 w-4" />
                            </button>
                            <Link
                                :href="fullPageUrl"
                                class="rounded-xl p-2 text-muted transition hover:bg-ink/[0.05] hover:text-ink"
                                title="Открыть на весь экран"
                                @click="close"
                            >
                                <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                            </Link>
                            <button type="button" class="rounded-xl p-2 text-muted transition hover:bg-ink/[0.05] hover:text-ink" title="Свернуть" @click="close">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </header>

                <ChatThread
                    class="min-h-0 flex-1"
                    compact
                    :messages="messages"
                    :pending-actions="pendingActions"
                    :suggestions="promptSuggestions"
                    :loading="loading"
                    :busy="loading"
                    :can-regenerate="Boolean(conversationId)"
                    :error="error"
                    :field-errors="fieldErrors"
                    @send="submit"
                    @confirm="onConfirm"
                    @reject="onReject"
                    @regenerate="onRegenerate"
                />

                <footer class="shrink-0 border-t border-line bg-surface/95 px-3 pb-[max(12px,env(safe-area-inset-bottom))] pt-3">
                    <Composer
                        ref="composer"
                        v-model="draft"
                        :attachments="attachments"
                        :uploading="uploading"
                        :loading="loading"
                        :can-send="canSend"
                        :max-attachments="4"
                        hint="Enter — отправить · Shift+Enter — перенос"
                        @send="submit()"
                        @files="uploadFiles"
                        @remove="removeAttachment"
                    />
                </footer>
            </aside>
        </div>
    </Teleport>
</template>
