<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import CustomFieldsView from '@/Components/CustomFields/CustomFieldsView.vue';
import { invoiceStatusLabels, taskPriorityLabels, taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { CheckIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: Object,
    canReview: Boolean,
    customFields: Array,
});

const reviewForm = useForm({ status: '', comment: '' });
const commentForm = useForm({ content: '', attachments: null });

const checklist = computed(() => props.task.checklist || []);

function accept() {
    reviewForm.status = 'completed';
    reviewForm.patch(route('client.tasks.review', props.task.id));
}

function reject() {
    if (!reviewForm.comment) {
        reviewForm.setError('comment', 'Опишите, что нужно поправить.');
        return;
    }
    reviewForm.status = 'rejected_by_client';
    reviewForm.patch(route('client.tasks.review', props.task.id));
}

function addComment() {
    commentForm.post(route('client.tasks.comments.store', props.task.id), { onSuccess: () => commentForm.reset() });
}
</script>

<template>
    <ClientLayout :title="task.title" eyebrow="Моя задача">
        <div class="mb-5 flex flex-wrap items-center gap-2.5">
            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
            <Badge :status="task.priority" type="priority">{{ taskPriorityLabels[task.priority] }}</Badge>
            <span class="text-[11px] text-muted">{{ task.deadline ? `срок ${formatDate(task.deadline)}` : 'срок не задан' }}</span>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.45fr_.75fr]">
            <div class="space-y-5">
                <section v-if="canReview" class="surface-card border-warning/30 bg-warning/[0.05] p-5">
                    <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-warning">Работа ждёт вашей оценки</p>
                    <p class="mt-2 text-[12px] leading-relaxed text-ink-soft">Примите результат или верните с комментарием — что именно поправить.</p>
                    <TextArea v-model="reviewForm.comment" class="mt-3" placeholder="Комментарий (обязателен при возврате)" :error="reviewForm.errors.comment" :rows="3" />
                    <div class="mt-3 flex justify-end gap-2">
                        <Button variant="secondary" size="sm" :disabled="reviewForm.processing" @click="reject">Вернуть</Button>
                        <Button size="sm" :disabled="reviewForm.processing" @click="accept">Принять работу</Button>
                    </div>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Описание</p>
                    <div v-if="task.description" class="prose-kiki mt-3" v-html="task.description"></div>
                    <p v-else class="mt-3 text-[12px] text-faint">Описание не заполнено.</p>
                </section>

                <section v-if="checklist.length" class="surface-card p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div><p class="eyebrow">Чек-лист</p><h2 class="mt-1 text-[14px] font-bold text-ink">{{ checklist.filter((i) => i.done).length }} из {{ checklist.length }}</h2></div>
                        <div class="h-1.5 w-28 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-primary-500" :style="{ width: `${(checklist.filter((i) => i.done).length / checklist.length) * 100}%` }"></div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div v-for="(item, index) in checklist" :key="index" class="flex items-center gap-3">
                            <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-md border', item.done ? 'border-primary-500 bg-primary-500 text-white' : 'border-line-strong bg-white']">
                                <CheckIcon v-if="item.done" class="h-3 w-3" />
                            </span>
                            <span :class="['text-[12px]', item.done ? 'text-faint line-through' : 'text-ink-soft']">{{ item.text }}</span>
                        </div>
                    </div>
                </section>

                <Card v-if="task.reports?.length" title="Отчёты">
                    <div class="divide-y divide-line/60">
                        <div v-for="report in task.reports" :key="report.id" class="py-3 first:pt-0">
                            <p class="text-[11px] font-semibold text-ink">{{ report.author?.name }} <span class="font-normal text-faint">· {{ formatDateTime(report.created_at) }}</span></p>
                            <p class="mt-1 whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">{{ report.content }}</p>
                        </div>
                    </div>
                </Card>

                <Card title="Комментарии">
                    <div v-if="task.comments?.length" class="divide-y divide-line/60">
                        <div v-for="comment in task.comments" :key="comment.id" class="py-3 first:pt-0">
                            <p class="text-[11px] font-semibold text-ink">{{ comment.author?.name }} <span class="font-normal text-faint">· {{ formatDateTime(comment.created_at) }}</span></p>
                            <p class="mt-1 whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">{{ comment.content }}</p>
                        </div>
                    </div>
                    <p v-else class="text-[12px] text-muted">Пока нет комментариев.</p>

                    <form v-if="!['completed', 'rejected_by_admin'].includes(task.status)" class="mt-4 space-y-3 border-t border-line/70 pt-4" @submit.prevent="addComment">
                        <TextArea v-model="commentForm.content" placeholder="Добавить комментарий…" :error="commentForm.errors.content" :rows="3" />
                        <div class="flex justify-end"><Button type="submit" size="sm" :disabled="commentForm.processing || !commentForm.content">Отправить</Button></div>
                    </form>
                </Card>
            </div>

            <aside class="space-y-5">
                <Card title="Информация">
                    <dl class="space-y-3 text-[12px]">
                        <div class="flex items-center justify-between"><dt class="text-muted">Статус</dt><dd><Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge></dd></div>
                        <div class="flex items-center justify-between"><dt class="text-muted">Приоритет</dt><dd>{{ taskPriorityLabels[task.priority] }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-muted">Дедлайн</dt><dd class="font-mono text-[11px]">{{ formatDate(task.deadline) }}</dd></div>
                        <div class="flex items-center justify-between"><dt class="text-muted">Создана</dt><dd class="font-mono text-[10px]">{{ formatDateTime(task.created_at) }}</dd></div>
                    </dl>
                </Card>

                <Card v-if="customFields?.length" title="Поля">
                    <CustomFieldsView :fields="customFields" />
                </Card>

                <Card v-if="task.invoices?.length" title="Счета" :padded="false">
                    <div class="divide-y divide-line/60">
                        <Link v-for="invoice in task.invoices" :key="invoice.id" :href="route('client.invoices.show', invoice.id)" class="flex items-center justify-between px-5 py-3 hover:bg-ink/[0.025]">
                            <span class="font-mono text-[12px] font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </Link>
                    </div>
                </Card>

                <Card v-if="task.status_history?.length" title="История">
                    <div class="space-y-3">
                        <div v-for="entry in task.status_history" :key="entry.id">
                            <Badge v-if="entry.new_status" :status="entry.new_status" type="task">{{ taskStatusLabels[entry.new_status] }}</Badge>
                            <p class="mt-1 font-mono text-[9px] text-faint">{{ formatDateTime(entry.created_at) }}</p>
                        </div>
                    </div>
                </Card>
            </aside>
        </div>
    </ClientLayout>
</template>
