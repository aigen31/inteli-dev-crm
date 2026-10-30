<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    client: Object,
    customFields: Array,
});

const form = useForm({
    name: props.client.name,
    email: props.client.email,
    phone: props.client.phone || '',
});

function submit() {
    form.put(route('admin.clients.update', props.client.id));
}
</script>

<template>
    <AdminLayout title="Изменить клиента">
        <template #actions>
            <Link :href="route('admin.clients.show', client.id)"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />К карточке</Button></Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Сохранить</Button>
        </template>

        <section class="surface-card max-w-2xl p-5 sm:p-6">
            <p class="eyebrow">Основное</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2"><TextInput v-model="form.name" label="Имя / название" :error="form.errors.name" /></div>
                <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" />
                <TextInput v-model="form.phone" label="Телефон" :error="form.errors.phone" />
            </div>
            <p class="mt-4 text-[10px] leading-relaxed text-muted">Поля клиента и статус приёма заявок редактируются в карточке клиента.</p>
        </section>
    </AdminLayout>
</template>
