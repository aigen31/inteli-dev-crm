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
        <template #actions>
            <div class="flex space-x-3">
                <Link :href="route('admin.clients.edit', client.id)">
                    <Button variant="secondary">Редактировать</Button>
                </Link>
                <Button v-if="client.is_active" variant="danger" @click="deactivate">Деактивировать</Button>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Client info -->
            <Card title="Информация">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-muted">Email</dt>
                        <dd class="text-sm text-ink-soft">{{ client.email }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Телефон</dt>
                        <dd class="text-sm text-ink-soft">{{ client.phone || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Статус</dt>
                        <dd>
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    client.is_active ? 'bg-success/10 text-success' : 'bg-white/5 text-ink-soft',
                                ]"
                            >
                                {{ client.is_active ? 'Активен' : 'Деактивирован' }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Создан</dt>
                        <dd class="text-sm text-ink-soft">{{ formatDateTime(client.created_at) }}</dd>
                    </div>
                </dl>
            </Card>

            <!-- Tasks -->
            <div class="lg:col-span-2">
                <Card title="Задачи клиента">
                    <div v-if="!tasks?.length" class="text-sm text-faint text-center py-4">
                        Нет задач
                    </div>
                    <div v-else class="divide-y divide-line">
                        <Link
                            v-for="task in tasks"
                            :key="task.id"
                            :href="route('admin.tasks.show', task.id)"
                            class="flex items-center justify-between py-3 hover:bg-white/5 -mx-2 px-2 rounded transition-colors"
                        >
                            <div>
                                <p class="text-sm font-medium text-ink-soft">{{ task.title }}</p>
                                <p class="text-xs text-faint">{{ formatDateTime(task.created_at) }}</p>
                            </div>
                            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                        </Link>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
