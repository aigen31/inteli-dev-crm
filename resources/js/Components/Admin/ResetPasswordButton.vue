<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CheckIcon, ClipboardDocumentIcon, KeyIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    action: { type: String, required: true },
    userName: { type: String, default: 'пользователя' },
});

const open = ref(false);
const link = ref('');
const copied = ref(false);
const processing = ref(false);

function createLink() {
    if (processing.value) return;
    processing.value = true;
    router.post(props.action, {}, {
        preserveScroll: true,
        onSuccess: (page) => {
            link.value = page.props.flash?.reset_link || '';
            if (link.value) open.value = true;
        },
        onFinish: () => (processing.value = false),
    });
}

async function copy() {
    try {
        await navigator.clipboard.writeText(link.value);
    } catch {
        const input = document.getElementById('reset-link-input');
        input?.select();
        document.execCommand('copy');
    }
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1800);
}

function close() { open.value = false; }
</script>

<template>
    <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-white/60 px-3 py-2 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700 disabled:opacity-60" :disabled="processing" @click="createLink">
        <KeyIcon class="h-3.5 w-3.5" />
        {{ processing ? 'Создаю…' : 'Сбросить пароль' }}
    </button>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[80] flex items-end justify-center bg-black/45 p-3 backdrop-blur-sm sm:items-center sm:p-6" @click.self="close">
            <section class="relative w-full max-w-[500px] overflow-hidden rounded-[26px] border border-line bg-surface shadow-[0_28px_90px_rgba(16,13,24,.28)]" role="dialog" aria-modal="true" aria-label="Ссылка для сброса пароля">
                <div class="relative overflow-hidden bg-ink px-5 pb-5 pt-5 text-canvas">
                    <div class="pointer-events-none absolute -right-8 -top-10 h-32 w-32 rounded-full bg-primary-400/30 blur-2xl"></div>
                    <button type="button" class="absolute right-4 top-4 z-10 inline-flex h-8 w-8 items-center justify-center rounded-full text-canvas/60 transition hover:bg-white/10 hover:text-canvas" aria-label="Закрыть" @click="close"><XMarkIcon class="h-4 w-4" /></button>
                    <div class="relative flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-[13px] bg-white/10"><KeyIcon class="h-5 w-5" /></span>
                        <div><p class="font-mono text-[8px] uppercase tracking-[0.16em] text-canvas/55">Одноразовая ссылка</p><h2 class="mt-1 font-display text-[18px] font-medium tracking-[-0.03em]">Сброс пароля</h2></div>
                    </div>
                </div>
                <div class="p-5">
                    <p class="text-[12px] font-semibold text-ink">Для {{ userName }}</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Передайте ссылку пользователю. Она действует 60 минут и сработает один раз.</p>
                    <div class="mt-4 flex items-center gap-2">
                        <input id="reset-link-input" :value="link" readonly class="h-10 min-w-0 flex-1 rounded-[12px] border border-line bg-white/65 px-3 font-mono text-[9px] text-ink focus:border-primary-300 focus:outline-none" @focus="$event.target.select()" />
                        <button type="button" class="inline-flex h-10 shrink-0 items-center gap-1.5 rounded-[12px] bg-primary-600 px-3 text-[10px] font-semibold text-white transition hover:bg-primary-700" @click="copy">
                            <CheckIcon v-if="copied" class="h-3.5 w-3.5" /><ClipboardDocumentIcon v-else class="h-3.5 w-3.5" />
                            {{ copied ? 'Скопировано' : 'Копировать' }}
                        </button>
                    </div>
                    <a :href="link" target="_blank" rel="noopener" class="mt-3 inline-flex text-[10px] font-semibold text-primary-700 hover:underline">Открыть форму сброса →</a>
                    <p class="mt-4 rounded-[13px] border border-line/70 bg-white/35 px-3 py-2.5 text-[10px] leading-relaxed text-muted">Пользователь введёт новый пароль на этой странице. Старый пароль перестанет действовать сразу.</p>
                </div>
            </section>
        </div>
    </Teleport>
</template>
