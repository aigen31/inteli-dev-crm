<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { taskStatusLabels, taskTypeLabels } from '@/Utils/constants.js';
import { formatDate } from '@/Utils/formatters.js';

const props = defineProps({
    tasks: Object,
    filters: Object,
    statuses: Array,
    types: Array,
});

function applyFilter(key, value) {
    router.get(route('admin.tasks.index'), {
        ...props.filters,
        [key]: value || undefined,
    }, { preserveState: true });
}
</script>

<template>
    <AdminLayout title="Задачи">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Задачи</h2>
            <Link :href="route('admin.tasks.create')">
                <Button>Создать задачу</Button>
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex gap-4 mb-4">
            <div class="w-48">
                <SelectInput
                    :model-value="filters.status || ''"
                    :options="statuses"
                    placeholder="Все статусы"
                    @update:model-value="applyFilter('status', $event)"
                />
            </div>
            <div class="w-48">
                <SelectInput
                    :model-value="filters.type || ''"
                    :options="types"
                    placeholder="Все типы"
                    @update:model-value="applyFilter('type', $event)"
                />
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Задача</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Клиент</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Тип</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дедлайн</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="task in tasks.data" :key="task.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm">
                            <Link :href="route('admin.tasks.show', task.id)" class="font-medium text-primary-600 hover:text-primary-700">
                                {{ task.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ task.client?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ taskTypeLabels[task.type] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(task.deadline) }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="tasks" />
        </div>
    </AdminLayout>
</template>
