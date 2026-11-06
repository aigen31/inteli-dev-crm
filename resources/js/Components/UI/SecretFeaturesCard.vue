<script setup>
import { computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { CheckIcon, KeyIcon, LockClosedIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import Button from '@/Components/UI/Button.vue';

const page = usePage();
const features = computed(() => page.props.features || {});
const theme = computed(() => page.props.theme || 'default');
const unlocked = computed(() => features.value.xp_theme === true);

const form = useForm({ code: '' });

function unlock() {
    form.post(route('settings.unlock'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function setTheme(value) {
    router.patch(route('settings.theme'), { theme: value }, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
    });
}
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="flex flex-wrap items-start gap-3 border-b border-line/70 bg-white/35 px-5 py-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-ink text-canvas">
                <KeyIcon class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-[13px] font-bold text-ink">Секретные функции</p>
                    <span v-if="unlocked" class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-[9px] font-semibold text-success">
                        <CheckIcon class="h-3 w-3" /> разблокировано
                    </span>
                </div>
                <p class="mt-0.5 text-[10px] leading-relaxed text-muted">
                    Если у вас есть секретный код, введите его здесь. Разблокированные возможности появятся только у вашего аккаунта.
                </p>
            </div>
            <LockClosedIcon class="h-4 w-4 shrink-0 text-faint" />
        </div>

        <div class="p-5">
            <form v-if="!unlocked" class="flex flex-wrap items-end gap-2" @submit.prevent="unlock">
                <div class="min-w-[220px] flex-1">
                    <label for="secret-code" class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Код доступа</label>
                    <input
                        id="secret-code"
                        v-model="form.code"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        placeholder="Введите секретный код"
                        class="h-10 w-full rounded-[12px] border border-line bg-white/70 px-3 font-mono text-[12px] text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                    />
                    <p v-if="form.errors.code" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.code }}</p>
                </div>
                <Button type="submit" size="sm" :disabled="form.processing || !form.code">
                    {{ form.processing ? 'Проверяю…' : 'Разблокировать' }}
                </Button>
            </form>

            <div v-else class="space-y-4">
                <div class="flex flex-wrap items-center gap-3 rounded-[16px] border border-line bg-white/45 p-3.5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-gradient-to-br from-[#3a6ea5] to-[#0a246a] text-white shadow-[inset_1px_1px_0_rgba(255,255,255,.4)]">
                        <SparklesIcon class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-[12px] font-bold text-ink">Windows XP</p>
                        <p class="mt-0.5 text-[10px] text-muted">Luna Blue: окна, кнопки, Tahoma и классические тени.</p>
                    </div>
                    <div class="flex items-center gap-1 rounded-full border border-line bg-white/60 p-1">
                        <button type="button" :class="['rounded-full px-3 py-1.5 text-[10px] font-semibold transition', theme !== 'xp' ? 'bg-ink text-canvas' : 'text-muted']" @click="setTheme('default')">
                            Обычная
                        </button>
                        <button type="button" :class="['rounded-full px-3 py-1.5 text-[10px] font-semibold transition', theme === 'xp' ? 'bg-[#0a246a] text-white' : 'text-muted']" @click="setTheme('xp')">
                            Windows XP
                        </button>
                    </div>
                </div>
                <p class="text-[10px] leading-relaxed text-muted">
                    Тема применяется ко всем разделам CRM. Переключение обратимо: вернуться к «Обычной» можно в любой момент.
                </p>
            </div>
        </div>
    </section>
</template>
