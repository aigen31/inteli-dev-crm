<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/Layout/GuestLayout.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Button from '@/Components/UI/Button.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import { ArrowRightIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    token: String,
    project: Object,
    role: String,
    role_hint: String,
    email: { type: String, default: null },
});

const form = useForm({
    name: '',
    email: props.email || '',
    password: '',
    password_confirmation: '',
    consent: false,
    privacy_ack: false,
});

const canSubmit = computed(
    () => form.consent && form.privacy_ack && form.name.trim() && form.email.trim() && form.password.length >= 8,
);

function submit() {
    form.post(route('invite.store', props.token));
}
</script>

<template>
    <GuestLayout title="Приглашение в проект">
        <Head title="Приглашение в проект" />

        <div class="mb-5">
            <p class="eyebrow">Вас пригласили</p>
            <h1 class="mt-1 font-display text-[26px] leading-tight tracking-[-0.03em] text-ink">Проект «{{ project.name }}»</h1>
            <p class="mt-1 font-mono text-[10px] uppercase tracking-[0.14em] text-muted">{{ project.key }} · {{ role }}</p>
            <p class="mt-2 text-[11px] leading-relaxed text-muted">{{ role_hint }}</p>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <TextInput v-model="form.name" label="Имя" :error="form.errors.name" placeholder="Как к вам обращаться" required />
            <TextInput
                v-model="form.email"
                label="E-mail"
                type="email"
                :error="form.errors.email"
                :disabled="!!email"
                :placeholder="email || 'you@example.com'"
                required
            />
            <TextInput v-model="form.password" label="Пароль" type="password" :error="form.errors.password" placeholder="Минимум 8 символов" required />
            <TextInput v-model="form.password_confirmation" label="Подтверждение пароля" type="password" required />

            <div class="space-y-2 pt-1">
                <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="invite-consent" />
                <ConsentCheckbox v-model="form.privacy_ack" :error="form.errors.privacy_ack" id="invite-privacy" variant="privacy" />
            </div>

            <p v-if="form.errors.invite" class="text-[10px] text-danger-ink">{{ form.errors.invite }}</p>

            <Button type="submit" class="w-full" :disabled="!canSubmit || form.processing">
                Создать аккаунт и войти
                <ArrowRightIcon class="h-3.5 w-3.5" />
            </Button>
        </form>

        <p class="mt-4 flex items-start gap-1.5 text-[9px] leading-relaxed text-faint">
            <SparklesIcon class="mt-0.5 h-3 w-3 shrink-0" />
            Ссылка действует один раз. После регистрации вы сразу попадёте в проект.
        </p>
    </GuestLayout>
</template>
