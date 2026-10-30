<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Button from '@/Components/UI/Button.vue';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('admin.clients.store'));
}
</script>

<template>
    <AdminLayout title="Создать клиента">
        <div class="max-w-2xl">
            <Card>
                <form @submit.prevent="submit" class="space-y-4">
                    <TextInput v-model="form.name" label="Имя / Название" :error="form.errors.name" required />
                    <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" required />
                    <TextInput v-model="form.phone" label="Телефон" :error="form.errors.phone" />
                    <TextInput v-model="form.password" label="Пароль" type="password" :error="form.errors.password" required />
                    <TextInput v-model="form.password_confirmation" label="Подтверждение пароля" type="password" required />

                    <div class="flex justify-end space-x-3 pt-4">
                        <Button variant="secondary" :href="route('admin.clients.index')">Отмена</Button>
                        <Button type="submit" :disabled="form.processing">Создать</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
