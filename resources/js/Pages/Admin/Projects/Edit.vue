<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import ProjectForm from '@/Components/Projects/ProjectForm.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    project: Object,
    clients: Array,
    statuses: Array,
    priorities: Array,
    colors: Array,
});

const form = useForm({
    name: props.project.name,
    key: props.project.key,
    description: props.project.description || '',
    client_id: props.project.client?.id || null,
    status: props.project.status,
    priority: props.project.priority,
    color: props.project.color,
    site_url: props.project.site_url || '',
    instructions: props.project.instructions || '',
    agents_md: props.project.agents_md || '',
    starts_at: props.project.starts_at || null,
    due_at: props.project.due_at || null,
});

function submit() {
    form.put(route('admin.projects.update', props.project.id));
}
</script>

<template>
    <AdminLayout :title="'Проект: ' + project.name">
        <template #actions>
            <Link :href="route('admin.projects.show', project.id)"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />К проекту</Button></Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Сохранить</Button>
        </template>

        <ProjectForm
            :model-value="form"
            :clients="clients"
            :statuses="statuses"
            :priorities="priorities"
            :colors="colors"
            :errors="form.errors"
            @update:model-value="(next) => Object.assign(form, next)"
        />
    </AdminLayout>
</template>
