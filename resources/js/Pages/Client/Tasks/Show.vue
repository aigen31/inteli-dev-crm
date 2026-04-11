<script setup>
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import { taskStatusLabels, taskPriorityLabels, invoiceStatusLabels } from '@/Utils/constants.js';
import { formatDateTime, formatDate, formatMoney } from '@/Utils/formatters.js';

const props = defineProps({
    task: Object,
    canReview: Boolean,
});

// Review form
const reviewForm = useForm({
    status: '',
    comment: '',
});

function accept() {
    reviewForm.status = 'completed';
    reviewForm.patch(route('client.tasks.review', props.task.id));
}

function reject() {
    if (!reviewForm.comment) {
        alert('Укажите причину отклонения');
        return;
    }
    reviewForm.status = 'rejected_by_client';
    reviewForm.patch(route('client.tasks.review', props.task.id));
}

// Comment form
const commentForm = useForm({
    content: '',
    attachments: null,
});

function addComment() {
    commentForm.post(route('client.tasks.comments.store', props.task.id), {
        onSuccess: () => commentForm.reset(),
    });
}
</script>

<template>
    <ClientLayout :title="task.title">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">{{ task.title }}</h2>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <!-- Description -->
                <Card title="Описание">
                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ task.description }}</p>
                </Card>

                <!-- Review action -->
                <Card v-if="canReview" title="Оценка работы">
                    <div class="rounded-md p-4 bg-purple-50 border border-purple-200 mb-4">
                        <p class="text-sm font-medium text-purple-700">
                            Работа выполнена и ожидает вашей оценки. Примите или отклоните результат.
                        </p>
                    </div>
                    <TextArea
                        v-model="reviewForm.comment"
                        placeholder="Комментарий (обязателен при отклонении)"
                        :error="reviewForm.errors.comment"
                        :rows="3"
                    />
                    <div class="flex justify-end space-x-3 mt-4">
                        <Button variant="danger" @click="reject" :disabled="reviewForm.processing">Отклонить</Button>
                        <Button @click="accept" :disabled="reviewForm.processing">Принять работу</Button>
                    </div>
                </Card>

                <!-- Reports -->
                <Card v-if="task.reports?.length" title="Отчёты">
                    <div class="divide-y divide-gray-100">
                        <div v-for="report in task.reports" :key="report.id" class="py-3">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-sm font-medium text-gray-700">{{ report.author?.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatDateTime(report.created_at) }}</span>
                            </div>
                            <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ report.content }}</p>
                        </div>
                    </div>
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

                    <form v-if="task.status !== 'completed' && task.status !== 'rejected_by_admin'"
                        @submit.prevent="addComment"
                        class="space-y-3 border-t border-gray-200 pt-4"
                    >
                        <TextArea v-model="commentForm.content" placeholder="Добавить комментарий..." :error="commentForm.errors.content" :rows="3" />
                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="commentForm.processing || !commentForm.content">
                                Отправить
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <Card title="Информация">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-500">Статус</dt>
                            <dd><Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge></dd>
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

                <!-- Invoices -->
                <Card v-if="task.invoices?.length" title="Счета">
                    <div class="space-y-2">
                        <a
                            v-for="invoice in task.invoices"
                            :key="invoice.id"
                            :href="route('client.invoices.show', invoice.id)"
                            class="flex items-center justify-between p-2 -mx-2 rounded hover:bg-gray-50 transition-colors"
                        >
                            <span class="text-sm font-medium text-gray-700">{{ formatMoney(invoice.amount) }}</span>
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </a>
                    </div>
                </Card>
            </div>
        </div>
    </ClientLayout>
</template>
