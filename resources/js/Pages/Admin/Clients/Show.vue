<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDateTime } from '@/Utils/formatters.js';

const props = defineProps({
    client: Object,
    tasks: Array,
});

function deactivate() {
    if (confirm('Вы уверены, что хотите деактивировать клиента?')) {
        router.patch(route('admin.clients.deactivate', props.client.id));
    }
}
</script>

<template>
    <AdminLayout :title="client.name">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ client.name }}</h2>
            <div class="flex space-x-3">
                <Link :href="route('admin.clients.edit', client.id)">
                    <Button variant="secondary">Редактировать</Button>
                </Link>
                <Button v-if="client.is_active" variant="danger" @click="deactivate">Деактивировать</Button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Client info -->
            <Card title="Информация">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-gray-500">Email</dt>
                        <dd class="text-sm text-gray-700">{{ client.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Телефон</dt>
                        <dd class="text-sm text-gray-700">{{ client.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Статус</dt>
                        <dd>
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    client.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600',
                                ]"
                            >
                                {{ client.is_active ? 'Активен' : 'Деактивирован' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Создан</dt>
                        <dd class="text-sm text-gray-700">{{ formatDateTime(client.created_at) }}</dd>
                    </div>
                </dl>
            </Card>

            <!-- Tasks -->
            <div class="lg:col-span-2">
                <Card title="Задачи клиента">
                    <div v-if="!tasks?.length" class="text-sm text-gray-400 text-center py-4">
                        Нет задач
                    </div>
                    <div v-else class="divide-y divide-gray-100">
                        <Link
                            v-for="task in tasks"
                            :key="task.id"
                            :href="route('admin.tasks.show', task.id)"
                            class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded transition-colors"
                        >
                            <div>
                                <p class="text-sm font-medium text-gray-700">{{ task.title }}</p>
                                <p class="text-xs text-gray-400">{{ formatDateTime(task.created_at) }}</p>
                            </div>
                            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
