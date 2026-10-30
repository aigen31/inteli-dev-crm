<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import Button from '@/Components/UI/Button.vue';

const props = defineProps({
    tasks: Array,
    selectedTaskId: String,
});

const form = useForm({
    task_id: props.selectedTaskId || '',
    amount: '',
    description: '',
    is_recurring: false,
    recurring_interval: '',
});

const taskOptions = props.tasks.map(t => ({ value: t.id, label: `${t.title} (${t.client?.name || ''})` }));
const intervalOptions = [
    { value: 'monthly', label: 'Ежемесячно' },
    { value: 'quarterly', label: 'Ежеквартально' },
    { value: 'yearly', label: 'Ежегодно' },
];

function submit() {
    form.post(route('admin.invoices.store'));
}
</script>

<template>
    <AdminLayout title="Создать счёт">
        <div class="max-w-2xl">
            <Card>
                <form @submit.prevent="submit" class="space-y-4">
                    <SelectInput v-model="form.task_id" label="Задача" :options="taskOptions" :error="form.errors.task_id" required />
                    <TextInput v-model="form.amount" label="Сумма (₽)" type="number" step="0.01" min="0.01" :error="form.errors.amount" required />
                    <TextArea v-model="form.description" label="Описание" :error="form.errors.description" required />

                    <div class="flex items-center gap-2">
                        <input
                            v-model="form.is_recurring"
                            type="checkbox"
                            id="is_recurring"
                            class="h-4 w-4 rounded border-line-strong bg-surface-2 text-primary-500 focus:ring-primary-500/50"
                        />
                        <label for="is_recurring" class="text-sm font-medium text-ink-soft">Рекуррентный платёж</label>
                    </div>

                    <SelectInput
                        v-if="form.is_recurring"
                        v-model="form.recurring_interval"
                        label="Интервал"
                        :options="intervalOptions"
                        :error="form.errors.recurring_interval"
                    />

                    <div class="flex justify-end space-x-3 pt-4">
                        <Button variant="secondary" :href="route('admin.invoices.index')">Отмена</Button>
                        <Button type="submit" :disabled="form.processing">Создать</Button>
                    </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>
