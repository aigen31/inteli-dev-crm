<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    task: Object,
    clients: Array,
    types: Array,
    priorities: Array,
});

const form = useForm({
    client_id: props.task.client_id,
    title: props.task.title,
    description: props.task.description,
    type: props.task.type,
    priority: props.task.priority,
    deadline: props.task.deadline || '',
});

const clientOptions = props.clients.map(c => ({ value: c.id, label: `${c.name} (${c.email})` }));

function submit() {
    form.put(route('admin.tasks.update', props.task.id));
}
</script>

<template>
    <AdminLayout title="Редактировать задачу">
        <div class="max-w-2xl">
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
                        <Button variant="secondary" :href="route('admin.tasks.show', task.id)">Отмена</Button>
                        <Button type="submit" :disabled="form.processing">Сохранить</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
