<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    client: Object,
});

const form = useForm({
    name: props.client.name,
    email: props.client.email,
    phone: props.client.phone || '',
});

function submit() {
    form.put(route('admin.clients.update', props.client.id));
}
</script>

<template>
    <AdminLayout title="Редактировать клиента">
        <div class="max-w-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Редактировать клиента</h2>

            <Card>
                <form @submit.prevent="submit" class="space-y-4">
                    <TextInput v-model="form.name" label="Имя / Название" :error="form.errors.name" required />
                    <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" required />
                    <TextInput v-model="form.phone" label="Телефон" :error="form.errors.phone" />

                    <div class="flex justify-end space-x-3 pt-4">
                        <Button variant="secondary" :href="route('admin.clients.show', client.id)">Отмена</Button>
                        <Button type="submit" :disabled="form.processing">Сохранить</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
