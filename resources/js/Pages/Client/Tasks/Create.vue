<script setup>
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Button from '@/Components/UI/Button.vue';

defineProps({
    priorities: Array,
});

const form = useForm({
    title: '',
    description: '',
    priority: 'medium',
    deadline: '',
});

function submit() {
    form.post(route('client.tasks.store'));
}
</script>

<template>
    <ClientLayout title="Создать заявку">
        <div class="max-w-2xl">
            <Card>
                <form @submit.prevent="submit" class="space-y-4">
                    <TextInput v-model="form.title" label="Название" :error="form.errors.title" required />
                    <TextArea v-model="form.description" label="Описание" :error="form.errors.description" required />
                    <div class="grid grid-cols-2 gap-4">
                        <SelectInput v-model="form.priority" label="Приоритет" :options="priorities" :error="form.errors.priority" />
                        <TextInput v-model="form.deadline" label="Желаемый срок" type="date" :error="form.errors.deadline" />
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <Button variant="secondary" :href="route('client.tasks.index')">Отмена</Button>
                        <Button type="submit" :disabled="form.processing">Отправить</Button>
                    </div>
                </form>
            </Card>
        </div>
    </ClientLayout>
</template>
