<script setup>
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    user: Object,
});

const profileForm = useForm({
    name: props.user.name,
    phone: props.user.phone || '',
    messenger_contacts: props.user.messenger_contacts || {},
});

function updateProfile() {
    profileForm.put(route('client.profile.update'));
}

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    passwordForm.put(route('password.update'), {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <ClientLayout title="Профиль">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Профиль</h2>

        <div class="max-w-2xl space-y-6">
            <Card title="Контактные данные">
                <form @submit.prevent="updateProfile" class="space-y-4">
                    <TextInput v-model="profileForm.name" label="Имя" :error="profileForm.errors.name" required />
                    <TextInput :model-value="user.email" label="Email" disabled />
                    <TextInput v-model="profileForm.phone" label="Телефон" :error="profileForm.errors.phone" />

                    <div class="flex justify-end pt-4">
                        <Button type="submit" :disabled="profileForm.processing">Сохранить</Button>
                    </div>
                </form>
            </Card>

            <Card title="Смена пароля">
                <form @submit.prevent="updatePassword" class="space-y-4">
                    <TextInput v-model="passwordForm.current_password" label="Текущий пароль" type="password" :error="passwordForm.errors.current_password" required />
                    <TextInput v-model="passwordForm.password" label="Новый пароль" type="password" :error="passwordForm.errors.password" required />
                    <TextInput v-model="passwordForm.password_confirmation" label="Подтверждение пароля" type="password" required />

                    <div class="flex justify-end pt-4">
                        <Button type="submit" :disabled="passwordForm.processing">Сменить пароль</Button>
                    </div>
                </form>
            </Card>
        </div>
    </ClientLayout>
</template>
