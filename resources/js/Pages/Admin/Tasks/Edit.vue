<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import Card from '@/Components/UI/Card.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TaskFormFields from '@/Components/Task/TaskFormFields.vue';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: Object,
    customFields: Array,
    clients: Array,
    projects: Array,
    assignees: Array,
    assigneeId: { type: String, default: null },
    types: Array,
    priorities: Array,
    statuses: Array,
    fields: Array,
    allowedTransitions: { type: Array, default: () => [] },
});

const values = computed(() => Object.fromEntries((props.customFields || []).map((field) => [field.key, field.value ?? null])));

const form = useForm({
    client_id: props.task?.client_id || '',
    project_id: props.task?.project_id || '',
    title: props.task?.title || '',
    description: props.task?.description || '',
    checklist: (props.task?.checklist || []).map((item) => ({ ...item })),
    type: props.task?.type || 'one_time',
    priority: props.task?.priority || 'medium',
    strict_control: props.task?.strict_control ?? null,
    estimated_minutes: props.task?.estimated_minutes || '',
    budget: props.task?.budget || '',
    assignee_id: props.task?.assignee_id || '',
    starts_at: props.task?.starts_at || '',
    deadline: props.task?.deadline || '',
    custom_fields: values.value,
    form_data: props.task?.form_data || { fields: {}, groups: {} },
});

const hasTemplate = computed(() => Boolean(props.task?.form_template));

function update(next) {
    Object.keys(next).forEach((key) => {
        form[key] = next[key];
    });
}

const statusForm = useForm({ status: '', comment: '' });

function changeStatus() {
    if (! statusForm.status) return;

    statusForm.patch(route('admin.tasks.status', props.task.id), { onSuccess: () => statusForm.reset() });
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

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0">
        <section v-if="hasTemplate" class="surface-card mb-5 p-5 sm:p-6">
            <p class="eyebrow">Заявка из шаблона</p>
            <h2 class="mt-1 text-[15px] font-bold text-ink">{{ task.form_template.name }}</h2>
            <p class="mt-1 text-[11px] text-muted">Редактируются только поля этого шаблона — обычные поля задачи скрыты.</p>
            <div class="mt-5">
                <TextInput v-model="form.title" label="Название задачи" :error="form.errors.title" />
            </div>
            <StructuredForm class="mt-5" :template="task.form_template" :model-value="form.form_data" :errors="form.errors" />
        </section>

        <TaskFormFields
            :model-value="form"
            :clients="clients"
            :projects="projects"
            :assignees="assignees"
            :assignee-id="form.assignee_id || null"
            :types="types"
            :priorities="priorities"
            :statuses="statuses"
            :fields="fields"
            :errors="form.errors"
            :show-status="false"
            :details-only="hasTemplate"
            @update:model-value="update"
        />
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <Card v-if="allowedTransitions.length" title="Сменить статус">
                    <form class="space-y-3" @submit.prevent="changeStatus">
                        <SelectInput v-model="statusForm.status" :options="allowedTransitions" placeholder="Выберите статус" :error="statusForm.errors.status" />
                        <TextArea v-model="statusForm.comment" placeholder="Комментарий (необязательно)" :rows="2" :error="statusForm.errors.comment" />
                        <Button type="submit" class="w-full" size="sm" :disabled="statusForm.processing || !statusForm.status">Применить</Button>
                    </form>
                </Card>

                <Card title="Сохранение">
                    <p class="text-[11px] leading-relaxed text-muted">Изменения полей задачи применяются кнопкой «Сохранить».</p>
                    <Button class="mt-3 w-full" size="sm" :disabled="form.processing" @click="submit">Сохранить изменения</Button>
                </Card>
            </aside>
        </div>
    </AdminLayout>
</template>
