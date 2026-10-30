<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TaskFormFields from '@/Components/Task/TaskFormFields.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    clients: Array,
    assignees: Array,
    templates: Array,
    types: Array,
    priorities: Array,
    statuses: Array,
    fields: Array,
});

const params = new URLSearchParams(window.location.search);

const form = useForm({
    client_id: params.get('client_id') || '',
    task_template_id: '',
    title: '',
    description: '',
    checklist: [],
    type: 'one_time',
    status: 'pending_review',
    priority: 'medium',
    estimated_minutes: '',
    assignee_id: '',
    deadline: '',
    custom_fields: {},
});

function update(next) {
    Object.keys(next).forEach((key) => {
        form[key] = next[key];
    });
}

function submit() {
    form.post(route('admin.tasks.store'));
}
</script>

<template>
    <AdminLayout title="Новая задача">
        <template #actions>
            <Link :href="route('admin.tasks.index')">
                <Button variant="ghost" size="sm">
                    <ArrowLeftIcon class="h-4 w-4" />
                    Назад
                </Button>
            </Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Создать задачу</Button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.4fr_.7fr]">
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
                @update:model-value="update"
            />

            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <section class="surface-card p-5">
                    <p class="eyebrow">Как это работает</p>
                    <ul class="mt-3 space-y-3 text-[11px] leading-relaxed text-muted">
                        <li><strong class="text-ink">Шаблон</strong> подставит описание и чек-лист. Их можно править.</li>
                        <li><strong class="text-ink">Исполнитель и срок</strong> необязательны — для самозанятого это нормально.</li>
                        <li><strong class="text-ink">Поля</strong> помогут хранить критерии приёмки и другие детали.</li>
                    </ul>
                </section>
                <section class="surface-card p-5">
                    <p class="eyebrow">Совет</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">
                        Если задача повторяется — сохраните её как шаблон. В следующий раз останется выбрать клиента и срок.
                    </p>
                    <Link :href="route('admin.task-templates.create')" class="mt-3 inline-block text-[11px] font-semibold text-primary-700 hover:underline">
                        Создать шаблон →
                    </Link>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
