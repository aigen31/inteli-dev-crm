<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import CustomFieldsView from '@/Components/CustomFields/CustomFieldsView.vue';
import StructuredFormSummary from '@/Components/FormTemplates/StructuredFormSummary.vue';
import { invoiceStatusLabels, taskPriorityLabels, taskSourceLabels, taskStatusLabels, taskTypeLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { ArrowTopRightOnSquareIcon, CheckIcon, PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: Object,
    allowedTransitions: Array,
    customFields: Array,
});

const statusForm = useForm({ status: '', comment: '' });
const commentForm = useForm({ content: '', attachments: null });

const checklist = computed(() => props.task.checklist || []);

function toggleChecklist(index) {
    const next = checklist.value.map((item, i) => (i === index ? { ...item, done: !item.done } : item));
    router.patch(route('admin.tasks.checklist', props.task.id), { checklist: next }, { preserveScroll: true });
}

function changeStatus() {
    if (!statusForm.status) return;
    statusForm.patch(route('admin.tasks.status', props.task.id), { onSuccess: () => statusForm.reset() });
}

function addComment() {
    commentForm.post(route('admin.tasks.comments.store', props.task.id), { onSuccess: () => commentForm.reset() });
}

</script>

<template>
    <AdminLayout :title="task.title" eyebrow="Задача">
        <template #actions>
            <Link :href="route('admin.tasks.edit', task.id)">
                <Button variant="secondary" size="sm">
                    <PencilSquareIcon class="h-4 w-4" />
                    Изменить
                </Button>
            </Link>
            <Link :href="route('admin.invoices.create', { task_id: task.id })">
                <Button size="sm">Выставить счёт</Button>
            </Link>
        </template>

        <div class="mb-6 flex flex-wrap items-center gap-2.5">
            <Link
                v-if="task.project"
                :href="route('admin.projects.show', task.project.id)"
                class="rounded-full border border-line bg-surface px-2.5 py-1 font-mono text-[9px] tracking-[0.06em] text-ink-soft hover:border-primary-300"
            >
                {{ task.project.key }}{{ task.number ? '-' + task.number : '' }} · {{ task.project.name }}
            </Link>
            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
            <Badge :status="task.priority" type="priority">{{ taskPriorityLabels[task.priority] }}</Badge>
            <span v-if="task.template" class="rounded-full bg-primary-soft px-2.5 py-1 text-[9px] font-semibold text-primary-800">из шаблона «{{ task.template.name }}»</span>
            <span class="text-[11px] text-muted">{{ task.client?.name }} · создана {{ formatDateTime(task.created_at) }}</span>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.45fr_.75fr]">
            <div class="space-y-5">
                <section v-if="task.form_template" class="surface-card p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="eyebrow">Структурированная заявка</p>
                            <h2 class="mt-1 text-[15px] font-bold text-ink">{{ task.form_template.name }}</h2>
                        </div>
                        <span class="rounded-full bg-primary-soft px-2.5 py-1 text-[9px] font-semibold text-primary-800">форма</span>
                    </div>
                    <StructuredFormSummary class="mt-4" :template="task.form_template" :data="task.form_data || {}" />
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Описание</p>
                    <div v-if="task.description" class="prose-kiki mt-3" v-html="task.description"></div>
                    <p v-else class="mt-3 text-[12px] text-faint">Описание не заполнено.</p>
                </section>

                <section v-if="checklist.length" class="surface-card p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="eyebrow">Чек-лист</p>
                            <h2 class="mt-1 text-[14px] font-bold text-ink">{{ checklist.filter((i) => i.done).length }} из {{ checklist.length }} готово</h2>
                        </div>
                        <div class="h-1.5 w-28 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: `${(checklist.filter((i) => i.done).length / checklist.length) * 100}%` }"></div>
                        </div>
                    </div>
                    <div class="mt-4 space-y-1.5">
                        <button
                            v-for="(item, index) in checklist"
                            :key="index"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-ink/[0.03]"
                            @click="toggleChecklist(index)"
                        >
                            <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-md border transition', item.done ? 'border-primary-500 bg-primary-500 text-white' : 'border-line-strong bg-white']">
                                <CheckIcon v-if="item.done" class="h-3 w-3" />
                            </span>
                            <span :class="['text-[12px]', item.done ? 'text-faint line-through' : 'text-ink-soft']">{{ item.text }}</span>
                        </button>
                    </div>
                </section>

                <Card title="Комментарии">
                    <div v-if="task.comments?.length" class="divide-y divide-line/60">
                        <div v-for="comment in task.comments" :key="comment.id" class="py-3 first:pt-0">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-[12px] font-semibold text-ink">{{ comment.author?.name }}</span>
                                <span class="font-mono text-[9px] text-faint">{{ formatDateTime(comment.created_at) }}</span>
                            </div>
                            <p class="whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">{{ comment.content }}</p>
                        </div>
                    </div>
                    <p v-else class="text-[12px] text-muted">Пока нет комментариев.</p>

                    <form class="mt-4 space-y-3 border-t border-line/70 pt-4" @submit.prevent="addComment">
                        <TextArea v-model="commentForm.content" placeholder="Добавить комментарий…" :error="commentForm.errors.content" :rows="3" />
                        <div class="flex justify-end">
                            <Button type="submit" size="sm" :disabled="commentForm.processing || !commentForm.content">Отправить</Button>
                        </div>
                    </form>
                </Card>

                <Card title="Отчёты">
                    <div v-if="task.reports?.length" class="space-y-3">
                        <article v-for="report in task.reports" :key="report.id" class="rounded-[16px] border border-line/70 bg-white/50 p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[11px] font-semibold text-ink">{{ report.author?.name }}</span>
                                <span v-if="report.recipient" class="text-[10px] text-muted">→ {{ report.recipient.name }}</span>
                                <span class="ml-auto font-mono text-[9px] text-faint">{{ formatDateTime(report.sent_at || report.created_at) }}</span>
                            </div>
                            <p v-if="report.title" class="mt-1 text-[12px] font-semibold text-ink">{{ report.title }}</p>
                            <div v-for="(item, index) in report.items" :key="item.id" class="mt-2 rounded-[12px] border border-line/60 bg-surface/60 p-2.5">
                                <p class="font-mono text-[8px] uppercase tracking-[0.12em] text-faint">Группа {{ index + 1 }}</p>
                                <p class="mt-1 whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">{{ item.content }}</p>
                                <a v-if="item.test_url" :href="item.test_url" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1 text-[10px] font-semibold text-primary-700 hover:underline">
                                    Страница для тестирования
                                    <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                                </a>
                                <a v-if="item.screenshot_id" :href="route('reports.screenshot', { report: report.id, item: item.id })" target="_blank" rel="noopener" class="mt-2 block">
                                    <img :src="route('reports.screenshot', { report: report.id, item: item.id })" alt="Скриншот отчёта" class="max-h-[240px] w-full rounded-[12px] border border-line object-cover" loading="lazy" />
                                </a>
                            </div>
                            <Link :href="route('reports.show', report.id)" class="mt-2 inline-flex text-[10px] font-semibold text-primary-700 hover:underline">Открыть отчёт</Link>
                        </article>
                    </div>
                    <p v-else class="text-[12px] text-muted">Пока нет отчётов.</p>

                    <div class="mt-4 border-t border-line/70 pt-4">
                        <Link :href="route('admin.reports.create', { task_id: task.id })">
                            <Button size="sm" variant="secondary">
                                <PlusIcon class="h-4 w-4" />
                                Создать отчёт
                            </Button>
                        </Link>
                    </div>
                </Card>
            </div>

            <aside class="space-y-5">
                <Card title="Информация">
                    <dl class="space-y-3 text-[12px]">
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Клиент</dt><dd><Link :href="route('admin.clients.show', task.client_id)" class="font-semibold text-primary-700 hover:underline">{{ task.client?.name }}</Link></dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Исполнитель</dt><dd class="text-ink">{{ task.assignee?.name || 'не назначен' }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Тип</dt><dd class="text-ink">{{ taskTypeLabels[task.type] }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Дедлайн</dt><dd class="font-mono text-[11px] text-ink">{{ formatDate(task.deadline) }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Оценка</dt><dd class="text-ink">{{ task.estimated_minutes ? `${task.estimated_minutes} мин` : '—' }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Источник</dt><dd class="text-ink">{{ taskSourceLabels[task.source] || task.source }}</dd></div>
                    </dl>
                </Card>

                <Card v-if="allowedTransitions.length" title="Сменить статус">
                    <form class="space-y-3" @submit.prevent="changeStatus">
                        <SelectInput v-model="statusForm.status" :options="allowedTransitions" placeholder="Выберите статус" :error="statusForm.errors.status" />
                        <TextArea v-model="statusForm.comment" placeholder="Комментарий (необязательно)" :rows="2" :error="statusForm.errors.comment" />
                        <Button type="submit" class="w-full" size="sm" :disabled="statusForm.processing || !statusForm.status">Применить</Button>
                    </form>
                </Card>

                <Card v-if="customFields?.length" title="Поля задачи">
                    <CustomFieldsView :fields="customFields" />
                </Card>

                <Card title="Счета" :padded="false">
                    <div v-if="!task.invoices?.length" class="px-5 py-5 text-[11px] text-muted">Счетов пока нет.</div>
                    <div v-else class="divide-y divide-line/60">
                        <Link v-for="invoice in task.invoices" :key="invoice.id" :href="route('admin.invoices.show', invoice.id)" class="flex items-center justify-between px-5 py-3 hover:bg-ink/[0.025]">
                            <span class="font-mono text-[12px] font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </Link>
                    </div>
                </Card>

                <Card title="История">
                    <div v-if="!task.status_history?.length" class="text-[11px] text-muted">Нет истории.</div>
                    <div v-else class="space-y-3">
                        <div v-for="entry in task.status_history" :key="entry.id">
                            <Badge v-if="entry.new_status" :status="entry.new_status" type="task">{{ taskStatusLabels[entry.new_status] }}</Badge>
                            <p class="mt-1 font-mono text-[9px] text-faint">{{ entry.changed_by?.name }} · {{ formatDateTime(entry.created_at) }}</p>
                            <p v-if="entry.comment" class="mt-1 text-[10px] text-muted">{{ entry.comment }}</p>
                        </div>
                    </div>
                </Card>
            </aside>
        </div>
    </AdminLayout>
</template>
