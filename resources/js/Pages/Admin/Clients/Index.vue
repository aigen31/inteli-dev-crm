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
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Клиенты</h2>
            <Link :href="route('admin.clients.create')">
                <Button>Создать клиента</Button>
            </Link>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Имя</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Телефон</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="client in clients.data" :key="client.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700">
                            <Link :href="route('admin.clients.show', client.id)" class="text-primary-600 hover:text-primary-700">
                                {{ client.name }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ client.email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ client.phone || '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    client.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600',
                                ]"
                            >
                                {{ client.is_active ? 'Активен' : 'Деактивирован' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <Link :href="route('admin.clients.edit', client.id)" class="text-primary-600 hover:text-primary-700">
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
