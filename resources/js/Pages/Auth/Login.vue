<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRightIcon, CheckCircleIcon, EyeIcon, EyeSlashIcon, LockClosedIcon } from '@heroicons/vue/24/outline';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Вход" />

    <div class="relative min-h-screen overflow-hidden bg-canvas">
        <div class="pointer-events-none absolute -left-40 top-1/3 h-[420px] w-[420px] rounded-full bg-accent/[0.12] blur-3xl"></div>
        <div class="pointer-events-none absolute -right-40 -top-40 h-[520px] w-[520px] rounded-full bg-primary-500/[0.12] blur-3xl"></div>

        <div class="relative mx-auto grid min-h-screen max-w-[1240px] items-stretch gap-0 px-4 py-4 sm:px-6 lg:grid-cols-[1fr_1.05fr] lg:px-8 lg:py-6">
            <div class="flex flex-col justify-between rounded-[30px] bg-surface/70 px-5 py-6 backdrop-blur-sm sm:px-10 lg:rounded-r-none lg:border lg:border-line/80 lg:px-12 lg:py-10">
                <div class="flex items-center gap-2">
                    <span class="font-display text-[18px] font-medium tracking-[-0.04em] text-ink">inteli dev</span>
                    <span class="rounded-full border border-line bg-white/60 px-2 py-[3px] font-mono text-[8px] uppercase tracking-[0.18em] text-muted">crm</span>
                    <span class="rounded-full border border-warning/25 bg-warning/10 px-2 py-[3px] font-mono text-[8px] uppercase tracking-[0.14em] text-warning">beta</span>
                </div>

                <div class="mx-auto w-full max-w-[380px] py-10">
                    <div class="mb-7 flex items-center gap-3">
                        <KikiAvatar :size="54" class="kiki-ring lg:hidden" />
                        <div>
                            <p class="eyebrow">Личный штаб</p>
                            <h1 class="mt-2 font-display text-[30px] font-medium leading-[1.05] tracking-[-0.04em] text-ink sm:text-[34px]">
                                Спокойный штаб вашей практики
                            </h1>
                        </div>
                    </div>
                    <p class="text-[13px] leading-relaxed text-muted">
                        Клиенты, задачи, шаблоны и счета — в одном тихом месте. Без регистрации: аккаунт создаёт владелец.
                    </p>

                    <form class="mt-8 space-y-4" @submit.prevent="submit">
                        <div>
                            <label for="email" class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="email"
                                autofocus
                                required
                                class="h-11 w-full rounded-[14px] border border-line bg-white/75 px-4 text-[13px] text-ink placeholder-faint transition focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                                placeholder="you@example.com"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="password" class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Пароль</label>
                            <div class="relative">
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    class="h-11 w-full rounded-[14px] border border-line bg-white/75 px-4 pr-11 text-[13px] text-ink placeholder-faint transition focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                                    placeholder="••••••••"
                                />
                                <button
                                    type="button"
                                    class="absolute right-1.5 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-[10px] text-muted transition hover:bg-ink/[0.05] hover:text-ink focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                    :aria-label="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                                    :title="showPassword ? 'Скрыть пароль' : 'Показать пароль'"
                                    @click="showPassword = !showPassword"
                                >
                                    <EyeSlashIcon v-if="showPassword" class="h-[17px] w-[17px]" />
                                    <EyeIcon v-else class="h-[17px] w-[17px]" />
                                </button>
                            </div>
                            <p v-if="form.errors.password" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.password }}</p>
                        </div>

                        <label class="flex items-center gap-2 text-[12px] text-ink-soft">
                            <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30" />
                            Запомнить меня
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-[14px] bg-primary-500 text-[13px] font-bold text-white shadow-[0_12px_28px_rgba(109,90,230,0.24)] transition hover:-translate-y-px hover:bg-primary-600 disabled:opacity-50"
                        >
                            Войти
                            <ArrowRightIcon class="h-4 w-4" />
                        </button>
                    </form>

                    <div class="mt-5 rounded-[12px] border border-warning/20 bg-warning/[0.07] px-3 py-2.5 text-[10px] leading-relaxed text-ink-soft">
                        <span class="font-semibold text-ink">Ранний доступ.</span>
                        Приложение в бете — возможны ошибки. Если что-то сломалось, напишите в
                        <a href="https://t.me/AiGen31" target="_blank" rel="noopener noreferrer" class="font-semibold text-warning underline decoration-warning/40 underline-offset-2">Telegram</a>.
                    </div>

                    <div class="mt-4 flex items-center gap-2 text-[10px] text-muted">
                        <LockClosedIcon class="h-3.5 w-3.5" />
                        Данные видны только вам и вашим клиентам.
                    </div>
                </div>

                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">inteli dev · crm · 2026</p>
            </div>

            <div class="relative hidden overflow-hidden rounded-[30px] bg-[#efe8f8] lg:block lg:rounded-l-none lg:border lg:border-l-0 lg:border-line/80">
                <div class="absolute inset-0 bg-[radial-gradient(90%_80%_at_70%_20%,#fff9f2_0%,#efe6fb_55%,#e6dcf7_100%)]"></div>
                <div class="absolute inset-x-0 bottom-0 h-[76%]">
                    <img src="/images/kiki/kiki-login-user-approved.webp" alt="Кики медитирует с фиолетовым шаром" class="h-full w-full object-contain object-bottom mix-blend-multiply" />
                </div>

                <div class="relative flex h-full flex-col p-10">
                    <div class="flex items-center gap-2">
                        <span class="rounded-full border border-white/70 bg-white/50 px-3 py-1 font-mono text-[9px] uppercase tracking-[0.15em] text-primary-800 backdrop-blur">Кики на связи</span>
                        <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                    </div>

                    <h2 class="mt-6 max-w-[390px] font-display text-[30px] font-medium leading-[1.1] tracking-[-0.04em] text-ink">
                        Не просто CRM.<br />Тихий помощник, который помнит всё.
                    </h2>
                    <p class="mt-3 max-w-[360px] text-[12px] leading-relaxed text-ink-soft">
                        Шаблоны задач вместо хаоса в описаниях. Поля, которые видны в API. Кики, которая знает контекст и делает рутину.
                    </p>

                    <div class="mt-auto pb-4"></div>
                </div>
            </div>
        </div>
    </div>
</template>
