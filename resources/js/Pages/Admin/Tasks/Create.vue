<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    clients: Array,
    types: Array,
    priorities: Array,
});

const form = useForm({
    client_id: '',
    title: '',
    description: '',
    type: 'one_time',
    status: 'queued',
    priority: 'medium',
    deadline: '',
});

const clientOptions = props.clients.map(c => ({ value: c.id, label: `${c.name} (${c.email})` }));

function submit() {
    form.post(route('admin.tasks.store'));
}
</script>

<template>
    <AdminLayout title="Создать задачу">
        <div class="max-w-2xl">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Создать задачу</h2>

            <Card>
                <form @submit.prevent="submit" class="space-y-4">
                    <SelectInput v-model="form.client_id" label="Клиент" :options="clientOptions" :error="form.errors.client_id" required />
                    <TextInput v-model="form.title" label="Название" :error="form.errors.title" required />
                    <TextArea v-model="form.description" label="Описание" :error="form.errors.description" required />
                    <div class="grid grid-cols-2 gap-4">
                        <SelectInput v-model="form.type" label="Тип" :options="types" :error="form.errors.type" />
                        <SelectInput v-model="form.priority" label="Приоритет" :options="priorities" :error="form.errors.priority" />
                    </div>
                    <TextInput v-model="form.deadline" label="Дедлайн" type="date" :error="form.errors.deadline" />

                    <div class="flex justify-end space-x-3 pt-4">
                        <Button variant="secondary" :href="route('admin.tasks.index')">Отмена</Button>
                        <Button type="submit" :disabled="form.processing">Создать</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
