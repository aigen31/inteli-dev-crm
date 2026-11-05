<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    Bars3Icon,
    PlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import ChatThread from '@/Components/Assistant/ChatThread.vue';
import Composer from '@/Components/Assistant/Composer.vue';
import SessionSidebar from '@/Components/Assistant/SessionSidebar.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { useAssistantChat } from '@/Composables/useAssistantChat';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    active: { type: Object, default: null },
    suggestions: { type: Array, default: () => [] },
    limits: { type: Object, default: () => ({}) },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);
const layout = computed(() => (isAdmin.value ? AdminLayout : ClientLayout));

const {
    conversationId,
    session,
    messages,
    pendingActions,
    suggestions,
    draft,
    attachments,
    documents,
    loading,
    uploading,
    error,
    fieldErrors,
    canSend,
    loadSession,
    send,
    regenerate,
    confirmAction,
    rejectAction,
    uploadFiles,
    removeAttachment,
    uploadDocuments,
    removeDocument,
    reset,
} = useAssistantChat({
    initialPayload: props.active,
    maxAttachments: props.limits?.attachments ?? 4,
    context: () => ({ url: window.location.pathname, page: 'assistant' }),
});

const sessionList = ref([...props.sessions]);
const listLoading = ref(false);
const sidebarOpen = ref(false);
const showArchived = ref(false);
const searchQuery = ref('');
const composer = ref(null);
const actionError = ref(null);

const activeId = computed(() => conversationId.value);
const title = computed(() => session.value?.title || 'Новый диалог');
const routeLabel = computed(() => session.value?.route || null);
const promptSuggestions = computed(() => (suggestions.value?.length ? suggestions.value : props.suggestions));

function syncUrl(id) {
    const base = route('assistant.index');
    const url = id ? `${base}?c=${encodeURIComponent(id)}` : base;

    if (typeof window !== 'undefined') {
        window.history.replaceState({}, '', url);
    }
}

async function refreshSessions() {
    listLoading.value = true;

    try {
        const { data } = await axios.get(route('assistant.conversations.index'), {
            params: {
                q: searchQuery.value || undefined,
                archived: showArchived.value ? 1 : 0,
            },
        });

        sessionList.value = data.sessions ?? [];
    } catch {
        // Keep the current list; the thread is still usable.
    } finally {
        listLoading.value = false;
    }
}

async function selectSession(id) {
    sidebarOpen.value = false;

    if (id === activeId.value) {
        return;
    }

    const payload = await loadSession(id);

    if (payload) {
        syncUrl(id);
    }
}

async function newChat() {
    reset();
    actionError.value = null;
    syncUrl(null);
    sidebarOpen.value = false;
    await nextTick();
    composer.value?.focus();
}

async function submit(text = draft.value) {
    const wasNew = !conversationId.value;
    const payload = await send(text);

    if (payload) {
        if (wasNew) {
            syncUrl(conversationId.value);
        }
        await refreshSessions();
    }
}

async function onConfirm(action, overrides) {
    actionError.value = null;
    const payload = await confirmAction(action, overrides);

    if (payload) {
        await refreshSessions();
    }
}

async function onReject(action) {
    const payload = await rejectAction(action);

    if (payload) {
        await refreshSessions();
    }
}

async function onRegenerate() {
    await regenerate();
}

async function renameSession(item, value) {
    try {
        const { data } = await axios.patch(route('assistant.conversations.update', item.id), { title: value });
        const updated = data.session;

        sessionList.value = sessionList.value.map((entry) => (entry.id === item.id ? { ...entry, ...updated } : entry));

        if (session.value && session.value.id === item.id) {
            session.value = { ...session.value, ...updated };
        }
    } catch (exception) {
        actionError.value = exception?.response?.data?.message || 'Не удалось переименовать диалог.';
    }
}

async function togglePin(item, pinned) {
    try {
        await axios.patch(route('assistant.conversations.update', item.id), { pinned });
        await refreshSessions();
    } catch (exception) {
        actionError.value = exception?.response?.data?.message || 'Не удалось закрепить диалог.';
    }
}

async function archiveSession(item, archived) {
    try {
        await axios.patch(route('assistant.conversations.update', item.id), { archived });
        await refreshSessions();
    } catch (exception) {
        actionError.value = exception?.response?.data?.message || 'Не удалось изменить архив.';
    }
}

async function deleteSession(item) {
    if (!window.confirm(`Удалить диалог «${item.title}»? Действие необратимо.`)) {
        return;
    }

    try {
        await axios.delete(route('assistant.conversations.destroy', item.id));
        sessionList.value = sessionList.value.filter((entry) => entry.id !== item.id);

        if (activeId.value === item.id) {
            await newChat();
        }
    } catch (exception) {
        actionError.value = exception?.response?.data?.message || 'Не удалось удалить диалог.';
    }
}

function onSearch(value) {
    searchQuery.value = value;
    refreshSessions();
}

function toggleArchived(value) {
    showArchived.value = value;
    refreshSessions();
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        sidebarOpen.value = false;
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <component :is="layout" title="Кики" bare>
        <div class="surface-card flex h-[calc(100dvh-9rem)] min-h-[520px] gap-0 overflow-hidden p-0 lg:h-[calc(100dvh-5.5rem)] lg:min-h-[600px]">
            <aside class="hidden w-[286px] shrink-0 border-r border-line/80 bg-surface-2/25 lg:block">
                <SessionSidebar
                    :sessions="sessionList"
                    :active-id="activeId"
                    :loading="listLoading"
                    :show-archived="showArchived"
                    @select="selectSession"
                    @new="newChat"
                    @rename="renameSession"
                    @pin="togglePin"
                    @archive="archiveSession"
                    @delete="deleteSession"
                    @search="onSearch"
                    @toggle-archived="toggleArchived"
                />
            </aside>

            <section class="flex min-w-0 flex-1 flex-col">
                <header class="flex shrink-0 items-center gap-3 border-b border-line/80 bg-surface/70 px-3 py-2.5 backdrop-blur sm:px-4">
                    <button
                        type="button"
                        class="-ml-1 rounded-xl p-2 text-ink-soft transition hover:bg-ink/[0.05] lg:hidden"
                        title="Диалоги"
                        @click="sidebarOpen = true"
                    >
                        <Bars3Icon class="h-5 w-5" />
                    </button>

                    <KikiAvatar variant="face" fit="contain" :size="36" />

                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold text-ink">{{ title }}</p>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                            <p class="text-[10px] text-muted">ассистент inteli dev</p>
                            <span
                                v-if="routeLabel"
                                class="rounded-full border border-line bg-white/60 px-1.5 py-[1px] font-mono text-[8px] uppercase tracking-[0.1em] text-muted"
                                :title="session?.model || ''"
                            >
                                {{ routeLabel === 'local' ? 'локальная модель' : 'облако' }}
                            </span>
                        </div>
                    </div>

                    <div class="ml-auto flex items-center gap-1">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/60 px-2.5 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700"
                            title="Новый диалог"
                            @click="newChat"
                        >
                            <PlusIcon class="h-3.5 w-3.5" />
                            <span class="hidden sm:inline">Новый</span>
                        </button>
                    </div>
                </header>

                <div class="flex min-h-0 flex-1 flex-col">
                    <ChatThread
                        class="min-h-0 flex-1"
                        :messages="messages"
                        :pending-actions="pendingActions"
                        :suggestions="promptSuggestions"
                        :loading="loading"
                        :busy="loading"
                        :can-regenerate="Boolean(conversationId)"
                        :error="error || actionError"
                        :field-errors="fieldErrors"
                        @send="submit"
                        @confirm="onConfirm"
                        @reject="onReject"
                        @regenerate="onRegenerate"
                    />

                    <div class="shrink-0 border-t border-line/70 bg-surface/90 px-3 pb-[max(10px,env(safe-area-inset-bottom))] pt-3 sm:px-4">
                        <div class="mx-auto max-w-[760px]">
                            <Composer
                                ref="composer"
                                v-model="draft"
                                :attachments="attachments"
                                :documents="documents"
                                :uploading="uploading"
                                :loading="loading"
                                :can-send="canSend"
                                :max-attachments="limits?.attachments ?? 4"
                                @send="submit()"
                                @files="uploadFiles"
                                @remove="removeAttachment"
                                @document-files="uploadDocuments"
                                @remove-document="removeDocument"
                            />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="sidebarOpen" class="fixed inset-0 z-[80] lg:hidden">
                <div class="absolute inset-0 bg-ink/30 backdrop-blur-[2px]" @click="sidebarOpen = false"></div>
                <div class="absolute inset-y-0 left-0 flex w-[86%] max-w-[330px] flex-col overflow-hidden border-r border-line bg-surface shadow-[0_30px_80px_rgba(28,24,35,0.3)]">
                    <div class="flex items-center justify-between border-b border-line/70 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <KikiAvatar variant="face" fit="contain" :size="28" />
                            <p class="text-[13px] font-semibold text-ink">Диалоги</p>
                        </div>
                        <button type="button" class="rounded-lg p-1.5 text-muted transition hover:text-ink" title="Закрыть" @click="sidebarOpen = false">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="min-h-0 flex-1">
                        <SessionSidebar
                            :sessions="sessionList"
                            :active-id="activeId"
                            :loading="listLoading"
                            :show-archived="showArchived"
                            @select="selectSession"
                            @new="newChat"
                            @rename="renameSession"
                            @pin="togglePin"
                            @archive="archiveSession"
                            @delete="deleteSession"
                            @search="onSearch"
                            @toggle-archived="toggleArchived"
                        />
                    </div>
                </div>
            </div>
        </Teleport>
    </component>
</template>
