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
        <template #actions>
            <Link :href="route('admin.tasks.create')">
                <Button>Создать задачу</Button>
            </Link>
        </template>

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

        <div class="bg-surface rounded-[14px] shadow-sm border border-line overflow-hidden">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-white/[0.03]">
                    <tr>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Задача</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Клиент</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Тип</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Статус</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Дедлайн</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-line">
                    <tr v-if="!tasks.data.length">
                        <td colspan="5" class="px-6 py-10 text-center font-mono text-[11px] uppercase tracking-[0.14em] text-faint">
                            Задач пока нет
                        </td>
                    </tr>
                    <tr v-for="task in tasks.data" :key="task.id" class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 text-sm">
                            <Link :href="route('admin.tasks.show', task.id)" class="font-medium text-primary-300 hover:text-primary-200">
                                {{ task.title }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ task.client?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ taskTypeLabels[task.type] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ formatDate(task.deadline) }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="tasks" />
        </div>
    </AdminLayout>
</template>
