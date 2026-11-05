<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    ArchiveBoxIcon,
    ChatBubbleLeftRightIcon,
    EllipsisHorizontalIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    activeId: { type: String, default: null },
    loading: { type: Boolean, default: false },
    showArchived: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'new', 'rename', 'pin', 'archive', 'delete', 'search', 'toggle-archived']);

const search = ref('');
const menuId = ref(null);
const editingId = ref(null);
const editTitle = ref('');
const searchTimer = ref(null);

const groups = computed(() => {
    const pinned = props.sessions.filter((session) => session.pinned);
    const rest = props.sessions.filter((session) => !session.pinned);

    const buckets = new Map();

    rest.forEach((session) => {
        const label = groupLabel(session.updated_at);
        if (!buckets.has(label)) buckets.set(label, []);
        buckets.get(label).push(session);
    });

    const order = ['Сегодня', 'Вчера', '7 дней', '30 дней', 'Ранее'];
    const result = [];

    if (pinned.length > 0) {
        result.push({ label: 'Закреплённые', items: pinned });
    }

    order.forEach((label) => {
        if (buckets.has(label)) {
            result.push({ label, items: buckets.get(label) });
        }
    });

    return result;
});

function groupLabel(value) {
    if (!value) return 'Ранее';

    const date = new Date(value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const day = new Date(date);
    day.setHours(0, 0, 0, 0);
    const diff = Math.round((today - day) / 86400000);

    if (diff <= 0) return 'Сегодня';
    if (diff === 1) return 'Вчера';
    if (diff < 7) return '7 дней';
    if (diff < 30) return '30 дней';

    return 'Ранее';
}

function timeLabel(value) {
    if (!value) return '';

    const date = new Date(value);
    const today = new Date();

    if (date.toDateString() === today.toDateString()) {
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    }

    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
}

function onSearch() {
    window.clearTimeout(searchTimer.value);
    searchTimer.value = window.setTimeout(() => emit('search', search.value), 300);
}

function startRename(session) {
    menuId.value = null;
    editingId.value = session.id;
    editTitle.value = session.title;

    if (typeof window !== 'undefined') {
        window.setTimeout(() => {
            const input = document.querySelector(`[data-rename="${session.id}"]`);
            input?.focus();
            input?.select();
        }, 20);
    }
}

function finishRename(session) {
    if (editingId.value !== session.id) {
        return;
    }

    const title = editTitle.value.trim();
    editingId.value = null;

    if (title !== '' && title !== session.title) {
        emit('rename', session, title);
    }
}

function cancelRename() {
    editingId.value = null;
}

function toggleMenu(id) {
    menuId.value = menuId.value === id ? null : id;
}

function closeMenu() {
    menuId.value = null;
}

onMounted(() => document.addEventListener('click', closeMenu));
onBeforeUnmount(() => document.removeEventListener('click', closeMenu));
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="shrink-0 space-y-2.5 px-3 pb-3 pt-3.5">
            <button
                type="button"
                class="flex w-full items-center justify-center gap-2 rounded-[14px] bg-ink px-3 py-2.5 text-[12px] font-semibold text-canvas transition hover:bg-primary-700"
                @click="emit('new')"
            >
                <PlusIcon class="h-4 w-4" />
                Новый диалог
            </button>

            <label class="relative block">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-faint" />
                <input
                    v-model="search"
                    type="search"
                    class="h-9 w-full rounded-[12px] border border-line bg-white/60 pl-8 pr-3 text-[11px] text-ink placeholder-faint focus:border-primary-300 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                    placeholder="Найти диалог"
                    @input="onSearch"
                />
            </label>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-2 pb-3">
            <p v-if="loading && sessions.length === 0" class="px-3 py-6 text-center text-[11px] text-muted">Загружаю диалоги…</p>

            <p v-else-if="sessions.length === 0" class="px-3 py-6 text-center text-[11px] leading-relaxed text-muted">
                {{ search ? 'Ничего не нашлось.' : 'Здесь появятся ваши диалоги с Кики.' }}
            </p>

            <section v-for="group in groups" :key="group.label" class="mb-3">
                <p class="px-2 pb-1.5 pt-1 font-mono text-[9px] uppercase tracking-[0.16em] text-faint">{{ group.label }}</p>

                <div class="space-y-0.5">
                    <div
                        v-for="session in group.items"
                        :key="session.id"
                        class="group relative"
                        @click="menuId = null"
                    >
                        <div
                            :class="[
                                'flex cursor-pointer items-start gap-2 rounded-[13px] px-2.5 py-2 transition',
                                activeId === session.id ? 'bg-primary-soft' : 'hover:bg-ink/[0.04]',
                            ]"
                            @click="emit('select', session.id)"
                        >
                            <span :class="['mt-0.5 shrink-0', activeId === session.id ? 'text-primary-600' : 'text-faint']">
                                <MapPinIcon v-if="session.pinned" class="h-3.5 w-3.5" />
                                <ChatBubbleLeftRightIcon v-else class="h-3.5 w-3.5" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline gap-2">
                                    <input
                                        v-if="editingId === session.id"
                                        v-model="editTitle"
                                        :data-rename="session.id"
                                        type="text"
                                        class="w-full rounded-md border border-primary-300 bg-white px-1.5 py-0.5 text-[12px] text-ink focus:outline-none"
                                        @click.stop
                                        @keydown.enter.prevent="finishRename(session)"
                                        @keydown.esc.prevent="cancelRename"
                                        @blur="finishRename(session)"
                                    />
                                    <p
                                        v-else
                                        :class="[
                                            'min-w-0 flex-1 truncate text-[12px]',
                                            activeId === session.id ? 'font-semibold text-primary-900' : 'font-medium text-ink-soft',
                                        ]"
                                    >
                                        {{ session.title }}
                                    </p>
                                    <span class="shrink-0 font-mono text-[9px] text-faint">{{ timeLabel(session.updated_at) }}</span>
                                </div>

                                <p v-if="!editingId" class="mt-0.5 truncate text-[10px] text-faint">
                                    {{ session.preview || 'Пустой диалог' }}
                                </p>

                                <span
                                    v-if="session.pending_actions > 0"
                                    class="mt-1 inline-flex items-center gap-1 rounded-full bg-warning/10 px-1.5 py-0.5 font-mono text-[8px] uppercase tracking-[0.1em] text-warning"
                                >
                                    ждёт подтверждения
                                </span>
                            </div>

                            <button
                                type="button"
                                class="shrink-0 rounded-md p-1 text-faint opacity-0 transition hover:text-ink group-hover:opacity-100"
                                title="Действия"
                                @click.stop="toggleMenu(session.id)"
                            >
                                <EllipsisHorizontalIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>

                        <div
                            v-if="menuId === session.id"
                            class="absolute right-1 top-8 z-20 w-[168px] overflow-hidden rounded-[14px] border border-line bg-surface py-1 shadow-[0_18px_40px_rgba(28,24,35,0.18)]"
                            @click.stop
                        >
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[11px] text-ink-soft transition hover:bg-ink/[0.04]" @click="startRename(session)">
                                <PencilSquareIcon class="h-3.5 w-3.5" />
                                Переименовать
                            </button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[11px] text-ink-soft transition hover:bg-ink/[0.04]" @click="emit('pin', session, !session.pinned); menuId = null">
                                <MapPinIcon class="h-3.5 w-3.5" />
                                {{ session.pinned ? 'Открепить' : 'Закрепить' }}
                            </button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[11px] text-ink-soft transition hover:bg-ink/[0.04]" @click="emit('archive', session, !session.archived); menuId = null">
                                <ArchiveBoxIcon class="h-3.5 w-3.5" />
                                {{ session.archived ? 'Вернуть из архива' : 'В архив' }}
                            </button>
                            <button type="button" class="flex w-full items-center gap-2 px-3 py-2 text-left text-[11px] text-danger transition hover:bg-danger/[0.06]" @click="emit('delete', session); menuId = null">
                                <TrashIcon class="h-3.5 w-3.5" />
                                Удалить
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="shrink-0 border-t border-line/70 px-3 py-2">
            <button
                type="button"
                :class="[
                    'flex w-full items-center gap-2 rounded-[10px] px-2 py-1.5 text-[10px] font-medium transition',
                    showArchived ? 'text-primary-700' : 'text-muted hover:text-ink',
                ]"
                @click="emit('toggle-archived', !showArchived)"
            >
                <ArchiveBoxIcon class="h-3.5 w-3.5" />
                {{ showArchived ? 'Скрыть архив' : 'Показать архив' }}
            </button>
        </div>
    </div>
</template>
