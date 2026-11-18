<script setup>
import { useForm } from '@inertiajs/vue3';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';

const props = defineProps({
    taskId: { type: String, required: true },
    transitions: { type: Array, default: () => [] },
    action: { type: String, required: true },
    title: { type: String, default: 'Сменить статус' },
    placeholder: { type: String, default: 'Выберите статус' },
});

const form = useForm({ status: '', comment: '' });

function submit() {
    if (! form.status) return;

    form.patch(route(props.action, props.taskId), { onSuccess: () => form.reset() });
}
</script>

<template>
    <Card v-if="transitions.length || $slots.empty" :title="title">
        <form v-if="transitions.length" class="space-y-3" @submit.prevent="submit">
            <SelectInput v-model="form.status" :options="transitions" :placeholder="placeholder" :error="form.errors.status" />
            <TextArea v-model="form.comment" placeholder="Комментарий (необязательно)" :rows="2" :error="form.errors.comment" />
            <Button type="submit" class="w-full" size="sm" :disabled="form.processing || ! form.status">Применить</Button>
        </form>
        <slot v-else name="empty" />
        <slot name="footer" />
    </Card>
</template>
