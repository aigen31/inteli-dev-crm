<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import ExecutorLayout from '@/Components/Layout/ExecutorLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { taskPriorityLabels, taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime } from '@/Utils/formatters.js';
import { ArrowLeftIcon, CheckIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: { type: Object, required: true },
    allowedTransitions: { type: Array, default: () => [] },
    strictControl: { type: Object, default: () => ({ override: null, active: true }) },
});

const checklist = computed(() => props.task.checklist || []);
const commentForm = useForm({ content: '', attachments: null });
const statusForm = useForm({ status: '', comment: '' });
const reportForm = useForm({ title: '', content: '', test_url: '', screenshot: null });

function toggleChecklist(index) {
    const next = checklist.value.map((item, i) => (i === index ? { ...item, done: !item.done } : item));
    router.patch(route('executor.tasks.checklist', props.task.id), { checklist: next }, { preserveScroll: true });
}

function addComment() {
    commentForm.post(route('executor.tasks.comments.store', props.task.id), { onSuccess: () => commentForm.reset() });
}

function changeStatus() {
    statusForm.patch(route('executor.tasks.status', props.task.id), { preserveScroll: true, onSuccess: () => statusForm.reset() });
}

function sendReport() {
    reportForm.post(route('executor.tasks.reports.store', props.task.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => reportForm.reset(),
    });
}
</script>

<template>
    <ExecutorLayout :title="task.title">
        <template #actions>
            <Link :href="route('executor.tasks.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Мои задачи</Button></Link>
        </template>

        <div class="mb-5 flex flex-wrap items-center gap-2.5">
            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
            <Badge :status="task.priority" type="priority">{{ taskPriorityLabels[task.priority] }}</Badge>
            <span class="text-[11px] text-muted">{{ task.starts_at ? `старт ${formatDate(task.starts_at)} · ` : '' }}{{ task.deadline ? `срок ${formatDate(task.deadline)}` : 'срок не задан' }}</span>
            <span v-if="task.project" class="rounded-full border border-line bg-white/60 px-2.5 py-1 font-mono text-[9px] text-muted">{{ task.project.key }}{{ task.number ? '-' + task.number : '' }}</span>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.45fr_.75fr]">
            <div class="space-y-5">
                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Описание</p>
                    <div v-if="task.description" class="prose-kiki mt-3" v-html="task.description"></div>
                    <p v-else class="mt-3 text-[12px] text-faint">Описание не заполнено.</p>
                </section>

                <section v-if="checklist.length" class="surface-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div><p class="eyebrow">Чек-лист</p><h2 class="mt-1 text-[14px] font-bold text-ink">{{ checklist.filter((i) => i.done).length }} из {{ checklist.length }}</h2></div>
                        <div class="h-1.5 w-28 overflow-hidden rounded-full bg-surface-2"><div class="h-full rounded-full bg-primary-500" :style="{ width: `${(checklist.filter((i) => i.done).length / checklist.length) * 100}%` }"></div></div>
                    </div>
                    <div class="mt-4 space-y-2">
                        <button v-for="(item, index) in checklist" :key="index" type="button" class="flex w-full items-center gap-3 rounded-[12px] px-2 py-1.5 text-left transition hover:bg-ink/[0.03]" @click="toggleChecklist(index)">
                            <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-md border', item.done ? 'border-primary-500 bg-primary-500 text-white' : 'border-line-strong bg-white']"><CheckIcon v-if="item.done" class="h-3 w-3" /></span>
                            <span :class="['text-[12px]', item.done ? 'text-faint line-through' : 'text-ink-soft']">{{ item.text }}</span>
                        </button>
                    </div>
                </section>

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

                <Card title="Отчёты клиенту">
                    <div v-if="task.reports?.length" class="divide-y divide-line/60">
                        <div v-for="report in task.reports" :key="report.id" class="py-3 first:pt-0">
                            <p class="text-[11px] font-semibold text-ink">{{ report.author?.name }} <span class="font-normal text-faint">· {{ formatDateTime(report.sent_at || report.created_at) }}</span></p>
                            <p v-if="report.title" class="mt-1 text-[12px] font-semibold text-ink">{{ report.title }}</p>
                            <div v-for="(item, index) in report.items" :key="item.id" class="mt-2">
                                <p class="font-mono text-[8px] uppercase tracking-[0.12em] text-faint">Группа {{ index + 1 }}</p>
                                <p class="mt-1 whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">{{ item.content }}</p>
                                <a v-if="item.test_url" :href="item.test_url" target="_blank" rel="noopener" class="mt-2 inline-flex text-[10px] font-semibold text-primary-700 hover:underline">Страница для тестирования</a>
                                <a v-if="item.screenshot_id" :href="route('reports.screenshot', { report: report.id, item: item.id })" target="_blank" rel="noopener" class="mt-2 block">
                                    <img :src="route('reports.screenshot', { report: report.id, item: item.id })" alt="Скриншот отчёта" class="max-h-[220px] w-full rounded-[12px] border border-line object-cover" loading="lazy" />
                                </a>
                            </div>
                            <Link :href="route('reports.show', report.id)" class="mt-2 inline-flex text-[10px] font-semibold text-primary-700 hover:underline">Открыть отчёт</Link>
                        </div>
                    </div>
                    <p v-else class="text-[12px] text-muted">Отчётов пока нет.</p>

                    <form class="mt-5 space-y-3 border-t border-line/70 pt-4" @submit.prevent="sendReport">
                        <p class="eyebrow">Новый отчёт</p>
                        <TextInput v-model="reportForm.title" label="Заголовок (необязательно)" :error="reportForm.errors.title" placeholder="Что проверить" />
                        <TextArea v-model="reportForm.content" label="Что сделано и как проверить" :error="reportForm.errors.content" :rows="4" placeholder="Коротко объясните результат и дайте ссылку для проверки." />
                        <TextInput v-model="reportForm.test_url" label="Ссылка для тестирования" type="url" :error="reportForm.errors.test_url" placeholder="https://…" />
                        <label class="block text-[11px] font-semibold text-ink-soft">Скриншот (необязательно)<input type="file" accept="image/*" class="mt-1.5 block w-full text-[10px] text-muted" @input="reportForm.screenshot = $event.target.files[0]" /></label>
                        <div class="flex justify-end"><Button type="submit" size="sm" :disabled="reportForm.processing || !reportForm.content"><PlusIcon class="h-3.5 w-3.5" />Отправить отчёт</Button></div>
                    </form>
                </Card>
            </div>

            <aside class="space-y-5">
                <Card title="Статус">
                    <form v-if="allowedTransitions.length" class="space-y-3" @submit.prevent="changeStatus">
                        <SelectInput v-model="statusForm.status" :options="allowedTransitions" placeholder="Новый статус" :error="statusForm.errors.status" />
                        <TextArea v-model="statusForm.comment" placeholder="Комментарий к переходу (необязательно)" :rows="2" :error="statusForm.errors.comment" />
                        <Button type="submit" class="w-full" size="sm" :disabled="statusForm.processing || !statusForm.status">Применить</Button>
                    </form>
                    <p v-else class="text-[11px] leading-relaxed text-muted">Сейчас статус меняет клиент или администратор.</p>
                    <p v-if="!strictControl.active && task.status === 'awaiting_review'" class="mt-2 rounded-[10px] bg-surface-2 px-3 py-2 text-[10px] leading-relaxed text-muted">
                        Мягкий режим: задачу можно закрыть без подтверждения клиента.
                    </p>
                    <p v-else-if="strictControl.active && task.status === 'awaiting_review'" class="mt-2 text-[10px] leading-relaxed text-faint">
                        Строгий контроль: закрыть задачу может только клиент.
                    </p>
                </Card>

                <Card title="Информация">
                    <dl class="space-y-3 text-[12px]">
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Клиент</dt><dd class="text-right text-ink">{{ task.client?.name || '—' }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Проект</dt><dd class="text-right text-ink">{{ task.project?.name || 'Без проекта' }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Приоритет</dt><dd class="text-ink">{{ taskPriorityLabels[task.priority] }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Старт</dt><dd class="font-mono text-[11px] text-ink">{{ formatDate(task.starts_at) }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Дедлайн</dt><dd class="font-mono text-[11px] text-ink">{{ formatDate(task.deadline) }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-muted">Создана</dt><dd class="font-mono text-[10px] text-ink">{{ formatDateTime(task.created_at) }}</dd></div>
                    </dl>
                </Card>

                <Card v-if="task.status_history?.length" title="История">
                    <div class="space-y-3">
                        <div v-for="entry in task.status_history" :key="entry.id">
                            <Badge v-if="entry.new_status" :status="entry.new_status" type="task">{{ taskStatusLabels[entry.new_status] }}</Badge>
                            <p class="mt-1 font-mono text-[9px] text-faint">{{ entry.changed_by?.name }} · {{ formatDateTime(entry.created_at) }}</p>
                            <p v-if="entry.comment" class="mt-1 text-[10px] text-muted">{{ entry.comment }}</p>
                        </div>
                    </div>
                </Card>
            </aside>
        </div>
    </ExecutorLayout>
</template>
