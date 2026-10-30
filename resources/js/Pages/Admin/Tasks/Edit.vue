<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TaskFormFields from '@/Components/Task/TaskFormFields.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: Object,
    customFields: Array,
    clients: Array,
    assignees: Array,
    templates: Array,
    types: Array,
    priorities: Array,
    statuses: Array,
    fields: Array,
});

const values = computed(() => Object.fromEntries((props.customFields || []).map((field) => [field.key, field.value ?? null])));

const form = useForm({
    client_id: props.task?.client_id || '',
    task_template_id: props.task?.task_template_id || '',
    title: props.task?.title || '',
    description: props.task?.description || '',
    checklist: (props.task?.checklist || []).map((item) => ({ ...item })),
    type: props.task?.type || 'one_time',
    priority: props.task?.priority || 'medium',
    estimated_minutes: props.task?.estimated_minutes || '',
    assignee_id: props.task?.assignee_id || '',
    deadline: props.task?.deadline || '',
    custom_fields: values.value,
});

function update(next) {
    Object.keys(next).forEach((key) => {
        form[key] = next[key];
    });
}

function submit() {
    form.put(route('admin.tasks.update', props.task.id));
}
</script>

<template>
    <AdminLayout title="Редактировать задачу">
        <template #actions>
            <Link :href="route('admin.tasks.show', task.id)">
                <Button variant="ghost" size="sm">
                    <ArrowLeftIcon class="h-4 w-4" />
                    К задаче
                </Button>
            </Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Сохранить</Button>
        </template>

        <TaskFormFields
            :model-value="form"
            :clients="clients"
            :assignees="assignees"
            :templates="templates"
            :types="types"
            :priorities="priorities"
            :statuses="statuses"
            :fields="fields"
            :errors="form.errors"
            :show-status="false"
            @update:model-value="update"
        />
    </AdminLayout>
</template>
