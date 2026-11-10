<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import {
    ArrowTopRightOnSquareIcon,
    CheckIcon,
    ClipboardDocumentIcon,
    ShareIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    href: { type: String, required: true },
    title: { type: String, required: true },
    kind: { type: String, default: 'task' },
    recipient: { type: String, default: null },
});

const open = ref(false);
const copied = ref(false);
const canNativeShare = ref(false);
const link = computed(() => {
    try {
        return new URL(props.href, window.location.origin).href;
    } catch {
        return props.href;
    }
});

const kindLabel = computed(() => (props.kind === 'project' ? 'проектом' : 'задачей'));

function close() {
    open.value = false;
}

async function copy() {
    try {
        await navigator.clipboard.writeText(link.value);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 1800);
    } catch {
        const input = document.getElementById(`share-link-${props.kind}`);
        input?.select();
        document.execCommand('copy');
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 1800);
    }
}

async function nativeShare() {
    try {
        await navigator.share({ title: props.title, text: `Ссылка на ${props.kind === 'project' ? 'проект' : 'задачу'} в CRM`, url: link.value });
    } catch {
        // The user dismissed the native sheet: keep the dialog open.
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') close();
}

onMounted(() => {
    canNativeShare.value = typeof navigator !== 'undefined' && typeof navigator.share === 'function';
    window.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <button
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-line bg-white/60 text-muted transition hover:border-primary-300 hover:bg-primary-50/50 hover:text-primary-700"
        title="Поделиться"
        aria-label="Поделиться"
        @click="open = true"
    >
        <ShareIcon class="h-4 w-4" />
    </button>

    <Teleport to="body">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-[70] flex items-end justify-center bg-ink/45 p-3 backdrop-blur-sm sm:items-center sm:p-6" @click.self="close">
                <section
                    class="relative w-full max-w-[480px] overflow-hidden rounded-[26px] border border-line bg-surface shadow-[0_28px_90px_rgba(16,13,24,.28)]"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Поделиться"
                >
                    <div class="relative overflow-hidden bg-ink px-5 pb-5 pt-5 text-canvas">
                        <div class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-primary-400/30 blur-2xl"></div>
                        <button type="button" class="absolute right-4 top-4 rounded-full p-1.5 text-canvas/60 transition hover:bg-white/10 hover:text-canvas" aria-label="Закрыть" @click="close">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                        <div class="relative flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-[13px] bg-white/10 text-canvas"><ShareIcon class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <p class="font-mono text-[8px] uppercase tracking-[0.16em] text-canvas/55">Передать клиенту</p>
                                <h2 class="mt-1 truncate font-display text-[19px] font-medium tracking-[-0.03em]">Поделиться {{ kindLabel }}</h2>
                            </div>
                            <KikiAvatar variant="face" fit="contain" :size="34" class="ml-auto" />
                        </div>
                    </div>

                    <div class="p-5">
                        <p class="truncate text-[12px] font-semibold text-ink">{{ title }}</p>
                        <p v-if="recipient" class="mt-1 text-[10px] text-muted">Для клиента: {{ recipient }}</p>

                        <label class="mt-4 block text-[10px] font-semibold text-ink-soft" :for="`share-link-${kind}`">Ссылка</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <input
                                :id="`share-link-${kind}`"
                                :value="link"
                                readonly
                                class="h-10 min-w-0 flex-1 rounded-[12px] border border-line bg-white/65 px-3 font-mono text-[10px] text-ink selection:bg-primary-200 focus:border-primary-300 focus:outline-none"
                                @focus="$event.target.select()"
                            />
                            <button type="button" class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-[12px] bg-primary-600 px-3 text-[10px] font-semibold text-white transition hover:bg-primary-700" @click="copy">
                                <CheckIcon v-if="copied" class="h-3.5 w-3.5" />
                                <ClipboardDocumentIcon v-else class="h-3.5 w-3.5" />
                                {{ copied ? 'Скопировано' : 'Копировать' }}
                            </button>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <a :href="link" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/55 px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700">
                                <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
                                Открыть как клиент
                            </a>
                            <button v-if="canNativeShare" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/55 px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700" @click="nativeShare">
                                <ShareIcon class="h-3.5 w-3.5" />
                                Поделиться…
                            </button>
                            <span class="ml-auto font-mono text-[8px] uppercase tracking-[0.1em] text-faint">после входа</span>
                        </div>

                        <p class="mt-4 rounded-[13px] border border-line/70 bg-white/35 px-3 py-2.5 text-[10px] leading-relaxed text-muted">
                            Ссылка ведёт в личный кабинет клиента. Клиент увидит {{ kind === 'project' ? 'проект и свои задачи' : 'задачу, переписку и отчёты' }} после входа в CRM. Права доступа проверяются при открытии.
                        </p>
                    </div>
                </section>
            </div>
        </transition>
    </Teleport>
</template>
