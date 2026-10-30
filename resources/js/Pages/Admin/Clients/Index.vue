<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Pagination from '@/Components/UI/Pagination.vue';

defineProps({
    clients: Object,
});
</script>

<template>
    <AdminLayout title="Клиенты">
        <template #actions>
            <Link :href="route('admin.clients.create')">
                <Button>Создать клиента</Button>
            </Link>
        </template>

        <div class="bg-surface rounded-[14px] shadow-sm border border-line overflow-hidden">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-white/[0.03]">
                    <tr>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Имя</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Email</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Телефон</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Статус</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-line">
                    <tr v-if="!clients.data.length">
                        <td colspan="5" class="px-6 py-10 text-center font-mono text-[11px] uppercase tracking-[0.14em] text-faint">
                            Клиентов пока нет
                        </td>
                    </tr>
                    <tr v-for="client in clients.data" :key="client.id" class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink-soft">
                            <Link :href="route('admin.clients.show', client.id)" class="text-primary-300 hover:text-primary-200">
                                {{ client.name }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ client.email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ client.phone || '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    client.is_active ? 'bg-success/10 text-success' : 'bg-white/5 text-ink-soft',
                                ]"
                            >
                                {{ client.is_active ? 'Активен' : 'Деактивирован' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <Link :href="route('admin.clients.edit', client.id)" class="text-primary-300 hover:text-primary-200">
                                Редактировать
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="clients" />
        </div>
    </AdminLayout>
</template>
