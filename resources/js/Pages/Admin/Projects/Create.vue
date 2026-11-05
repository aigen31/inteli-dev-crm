<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import ProjectForm from '@/Components/Projects/ProjectForm.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    clients: Array,
    statuses: Array,
    priorities: Array,
    colors: Array,
});

const params = new URLSearchParams(window.location.search);

const form = useForm({
    name: '',
    key: '',
    description: '',
    client_id: params.get('client_id') || null,
    status: 'active',
    priority: 'medium',
    color: 'violet',
    site_url: '',
    instructions: '',
    agents_md: '',
    starts_at: null,
    due_at: null,
});

function submit() {
    form.post(route('admin.projects.store'));
}
</script>

<template>
    <AdminLayout title="Новый проект">
        <template #actions>
            <Link :href="route('admin.projects.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Назад</Button></Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Создать проект</Button>
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
