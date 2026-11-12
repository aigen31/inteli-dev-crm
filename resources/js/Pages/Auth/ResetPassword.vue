<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { ArrowLeftIcon, KeyIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('password.reset.store'));
}
</script>

<template>
    <Head title="Новый пароль" />
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-canvas px-4 py-8">
        <div class="pointer-events-none absolute -left-32 top-1/4 h-[380px] w-[380px] rounded-full bg-accent/[0.12] blur-3xl"></div>
        <div class="pointer-events-none absolute -right-32 -top-32 h-[480px] w-[480px] rounded-full bg-primary-500/[0.12] blur-3xl"></div>
        <div class="relative w-full max-w-[440px] rounded-[28px] border border-line bg-surface p-6 shadow-[0_24px_70px_rgba(28,24,35,.10)] sm:p-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2"><span class="font-display text-[17px] font-medium tracking-[-0.04em]">inteli dev</span><span class="rounded-full border border-line bg-white/60 px-2 py-[2px] font-mono text-[8px] uppercase tracking-[0.14em] text-muted">crm</span></div>
                <KikiAvatar :size="42" />
            </div>
            <div class="mt-7 flex h-11 w-11 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700"><KeyIcon class="h-5 w-5" /></div>
            <p class="eyebrow mt-4">Одноразовая ссылка</p>
            <h1 class="mt-2 font-display text-[27px] font-medium leading-tight tracking-[-0.04em] text-ink">Придумайте новый пароль</h1>
            <p class="mt-2 text-[12px] leading-relaxed text-muted">После сохранения старый пароль перестанет действовать. Ссылка сработает один раз.</p>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <TextInput v-model="form.email" label="Email" type="email" readonly :error="form.errors.email" />
                <TextInput v-model="form.password" label="Новый пароль" type="password" autocomplete="new-password" placeholder="Минимум 8 символов" :error="form.errors.password" required />
                <TextInput v-model="form.password_confirmation" label="Повторите пароль" type="password" autocomplete="new-password" :error="form.errors.password_confirmation" required />
                <Button class="w-full" type="submit" :disabled="form.processing">{{ form.processing ? 'Сохраняю…' : 'Сохранить пароль' }}</Button>
            </form>
            <Link :href="route('login')" class="mt-5 inline-flex items-center gap-1.5 text-[10px] font-semibold text-muted hover:text-ink"><ArrowLeftIcon class="h-3 w-3" />Вернуться ко входу</Link>
        </div>
    </div>
</template>
