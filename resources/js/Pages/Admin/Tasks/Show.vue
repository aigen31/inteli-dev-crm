<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { taskStatusLabels, taskTypeLabels, taskPriorityLabels, invoiceStatusLabels } from '@/Utils/constants.js';
import { formatDateTime, formatDate, formatMoney } from '@/Utils/formatters.js';

const props = defineProps({
    task: Object,
    allowedTransitions: Array,
});

// Status change form
const statusForm = useForm({
    status: '',
    comment: '',
});

function changeStatus() {
    if (!statusForm.status) return;
    statusForm.patch(route('admin.tasks.status', props.task.id), {
        onSuccess: () => statusForm.reset(),
    });
}

// Comment form
const commentForm = useForm({
    content: '',
    attachments: null,
});

function addComment() {
    commentForm.post(route('admin.tasks.comments.store', props.task.id), {
        onSuccess: () => commentForm.reset(),
    });
}

// Report form
const showReportForm = ref(false);
const reportForm = useForm({
    content: '',
    attachments: null,
});

function addReport() {
    reportForm.post(route('admin.tasks.reports.store', props.task.id), {
        onSuccess: () => {
            reportForm.reset();
            showReportForm.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout :title="task.title">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">{{ task.title }}</h2>
            <div class="flex space-x-3">
                <Link :href="route('admin.tasks.edit', task.id)">
                    <Button variant="secondary">Редактировать</Button>
                </Link>
                <Link :href="route('admin.invoices.create', { task_id: task.id })">
                    <Button>Выставить счёт</Button>
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Description -->
                <Card title="Описание">
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ task.description }}</p>
                </Card>

                <!-- Comments -->
                <Card title="Комментарии">
                    <div v-if="task.comments?.length" class="divide-y divide-gray-100 mb-4">
                        <div v-for="comment in task.comments" :key="comment.id" class="py-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-700">{{ comment.author?.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatDateTime(comment.created_at) }}</span>
                            </div>
                            <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ comment.content }}</p>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 mb-4">Нет комментариев</div>

                    <form @submit.prevent="addComment" class="space-y-3 border-t border-gray-200 pt-4">
                        <TextArea v-model="commentForm.content" placeholder="Добавить комментарий..." :error="commentForm.errors.content" :rows="3" />
                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="commentForm.processing || !commentForm.content">
                                Отправить
                            </Button>
                        </div>
                    </form>
                </Card>

                <!-- Reports -->
                <Card title="Отчёты">
                    <div v-if="task.reports?.length" class="divide-y divide-gray-100 mb-4">
                        <div v-for="report in task.reports" :key="report.id" class="py-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-700">{{ report.author?.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatDateTime(report.created_at) }}</span>
                            </div>
                            <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ report.content }}</p>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-400 mb-4">Нет отчётов</div>

                    <div v-if="!showReportForm" class="border-t border-gray-200 pt-4">
                        <Button size="sm" variant="secondary" @click="showReportForm = true">Добавить отчёт</Button>
                    </div>
                    <form v-else @submit.prevent="addReport" class="space-y-3 border-t border-gray-200 pt-4">
                        <TextArea v-model="reportForm.content" placeholder="Текст отчёта..." :error="reportForm.errors.content" />
                        <div class="flex justify-end space-x-3">
                            <Button variant="secondary" size="sm" @click="showReportForm = false">Отмена</Button>
                            <Button type="submit" size="sm" :disabled="reportForm.processing || !reportForm.content">
                                Сохранить отчёт
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Task info -->
                <Card title="Информация">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-500">Статус</dt>
                            <dd><Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Клиент</dt>
                            <dd>
                                <Link :href="route('admin.clients.show', task.client_id)" class="text-sm text-primary-600 hover:text-primary-700">
                                    {{ task.client?.name }}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Тип</dt>
                            <dd class="text-sm text-gray-700">{{ taskTypeLabels[task.type] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Приоритет</dt>
                            <dd><Badge :status="task.priority" type="priority">{{ taskPriorityLabels[task.priority] }}</Badge></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Дедлайн</dt>
                            <dd class="text-sm text-gray-700">{{ formatDate(task.deadline) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Создана</dt>
                            <dd class="text-sm text-gray-700">{{ formatDateTime(task.created_at) }}</dd>
                        </div>
                    </dl>
                </Card>

                <!-- Change status -->
                <Card v-if="allowedTransitions.length" title="Сменить статус">
                    <form @submit.prevent="changeStatus" class="space-y-3">
                        <SelectInput
                            v-model="statusForm.status"
                            :options="allowedTransitions"
                            placeholder="Выберите статус..."
                            :error="statusForm.errors.status"
                        />
                        <TextArea
                            v-model="statusForm.comment"
                            placeholder="Комментарий (опционально)"
                            :rows="2"
                            :error="statusForm.errors.comment"
                        />
                        <Button type="submit" class="w-full" :disabled="statusForm.processing || !statusForm.status">
                            Применить
                        </Button>
                    </form>
                </Card>

                <!-- Invoices -->
                <Card title="Счета">
                    <div v-if="!task.invoices?.length" class="text-sm text-gray-400">Нет счетов</div>
                    <div v-else class="space-y-2">
                        <Link
                            v-for="invoice in task.invoices"
                            :key="invoice.id"
                            :href="route('admin.invoices.show', invoice.id)"
                            class="flex items-center justify-between p-2 -mx-2 rounded hover:bg-gray-50 transition-colors"
                        >
                            <div>
                                <span class="text-sm font-medium text-gray-700">{{ formatMoney(invoice.amount) }}</span>
                            </div>
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </Link>
                    </div>
                </Card>

                <!-- Status history -->
                <Card title="История статусов">
                    <div v-if="!task.status_history?.length" class="text-sm text-gray-400">Нет истории</div>
                    <div v-else class="space-y-3">
                        <div v-for="entry in task.status_history" :key="entry.id" class="text-sm">
                            <div class="flex items-center gap-2">
                                <Badge v-if="entry.new_status" :status="entry.new_status" type="task">
                                    {{ taskStatusLabels[entry.new_status] }}
                                </Badge>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">
                                {{ entry.changed_by?.name }} &middot; {{ formatDateTime(entry.created_at) }}
                            </p>
                            <p v-if="entry.comment" class="text-xs text-gray-500 mt-1">{{ entry.comment }}</p>
                        </div>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
