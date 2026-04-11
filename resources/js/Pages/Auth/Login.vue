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
        <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center">Вход в систему</h2>

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
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="rounded border-gray-300 text-primary-500 focus:ring-primary-500"
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
