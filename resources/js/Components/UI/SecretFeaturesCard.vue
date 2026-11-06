<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { CheckIcon, KeyIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import Button from '@/Components/UI/Button.vue';

const page = usePage();
const features = computed(() => page.props.features || {});
const unlocked = computed(() => features.value.xp_theme === true);

const form = useForm({ code: '' });

function unlock() {
    form.post(route('settings.unlock'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
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
                    Введите секретный код. Новые возможности появятся только у вашего аккаунта.
                </p>
            </div>
            <LockClosedIcon class="h-4 w-4 shrink-0 text-faint" />
        </div>

        <div class="space-y-4 p-5">
            <form class="flex flex-wrap items-end gap-2" @submit.prevent="unlock">
                <div class="min-w-[220px] flex-1">
                    <label for="secret-code" class="mb-1.5 flex flex-wrap items-center gap-2 text-[11px] font-semibold text-ink-soft">
                        {{ unlocked ? 'Введите новый код' : 'Код доступа' }}
                        <span v-if="unlocked" class="rounded-full bg-white/60 px-2 py-0.5 font-normal text-muted">уже разблокированные функции сохранятся</span>
                    </label>
                    <input
                        id="secret-code"
                        v-model="form.code"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        :placeholder="unlocked ? 'Новый секретный код' : 'Введите секретный код'"
                        class="h-10 w-full rounded-[12px] border border-line bg-white/70 px-3 font-mono text-[12px] text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                    />
                    <p v-if="form.errors.code" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.code }}</p>
                </div>
                <Button type="submit" size="sm" :disabled="form.processing || !form.code">
                    {{ form.processing ? 'Проверяю…' : 'Проверить код' }}
                </Button>
            </form>

            <div v-if="unlocked" class="flex flex-wrap items-center gap-2 rounded-[14px] border border-line bg-white/45 px-3.5 py-3">
                <CheckIcon class="h-4 w-4 shrink-0 text-success" />
                <p class="text-[10px] leading-relaxed text-ink-soft">
                    Windows XP разблокирована. Выберите её в блоке <strong class="font-semibold text-ink">«Тема оформления»</strong>.
                </p>
            </div>
        </div>
    </section>
</template>
