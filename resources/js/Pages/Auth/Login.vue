<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '@/Components/Layout/GuestLayout.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Button from '@/Components/UI/Button.vue';

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
    <GuestLayout title="Вход">
        <div class="mb-8 text-center">
            <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">Вход в систему</p>
            <h2 class="mt-2 text-xl font-semibold text-ink">CRM для самозанятого</h2>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <TextInput
                v-model="form.email"
                label="Email"
                type="email"
                :error="form.errors.email"
                autocomplete="email"
                autofocus
                required
            />

            <TextInput
                v-model="form.password"
                label="Пароль"
                type="password"
                :error="form.errors.password"
                autocomplete="current-password"
                required
            />

            <div class="flex items-center">
                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-line-strong bg-surface-2 text-primary-500 focus:ring-primary-500/50"
                    />
                    Запомнить меня
                </label>
            </div>

            <Button type="submit" class="w-full" :disabled="form.processing">
                Войти
            </Button>
        </form>
    </GuestLayout>
</template>
