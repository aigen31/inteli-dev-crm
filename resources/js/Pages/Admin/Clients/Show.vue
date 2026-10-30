<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import CustomFieldsForm from '@/Components/CustomFields/CustomFieldsForm.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { invoiceStatusLabels, taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { PencilSquareIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    client: Object,
    tasks: Array,
    invoices: Array,
    customFields: Array,
});

const fieldForm = useForm({
    fields: Object.fromEntries((props.customFields || []).map((field) => [field.key, field.value ?? null])),
});

const intake = computed(() => (props.customFields || []).find((field) => field.key === 'intake_status'));
const activeTasks = computed(() => (props.tasks || []).filter((task) => !['completed', 'rejected_by_admin'].includes(task.status)));

function saveFields() {
    fieldForm.put(route('admin.clients.custom-fields', props.client.id), { preserveScroll: true });
}

function deactivate() {
    if (window.confirm('Деактивировать клиента? Он потеряет доступ к кабинету.')) {
        router.patch(route('admin.clients.deactivate', props.client.id));
    }
}
</script>

<template>
    <AdminLayout :title="client.name">
        <template #actions>
            <Link :href="route('admin.tasks.create', { client_id: client.id })">
                <Button variant="secondary">
                    <PlusIcon class="h-4 w-4" />
                    Задача
                </Button>
            </Link>
            <Link :href="route('admin.clients.edit', client.id)">
                <Button variant="secondary">
                    <PencilSquareIcon class="h-4 w-4" />
                    Изменить
                </Button>
            </Link>
            <Button v-if="client.is_active" variant="ghost" @click="deactivate">Деактивировать</Button>
        </template>

        <section class="surface-card mb-5 flex flex-wrap items-center gap-4 p-5 sm:p-6">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-soft text-[15px] font-bold text-primary-800">
                {{ (client.name || '?').split(' ').slice(0, 2).map((part) => part[0]).join('').toUpperCase() }}
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-display text-[21px] font-medium tracking-[-0.03em] text-ink">{{ client.name }}</h2>
                    <span :class="['rounded-full px-2.5 py-1 text-[9px] font-semibold', client.is_active ? 'bg-success/10 text-success' : 'bg-ink/[0.06] text-muted']">
                        {{ client.is_active ? 'Активен' : 'Деактивирован' }}
                    </span>
                </div>
                <p class="mt-1 text-[12px] text-muted">{{ client.email }} <span v-if="client.phone"> · {{ client.phone }}</span></p>
            </div>
            <div v-if="intake" class="rounded-2xl bg-surface-2/60 px-4 py-3">
                <p class="text-[9px] font-semibold uppercase tracking-[0.08em] text-muted">{{ intake.label }}</p>
                <p class="mt-1 text-[12px] font-bold text-ink">{{ intake.display || '—' }}</p>
            </div>
            <div class="hidden gap-6 sm:flex">
                <div><p class="font-display text-[20px] font-medium text-ink">{{ activeTasks.length }}</p><p class="text-[10px] text-muted">в работе</p></div>
                <div><p class="font-display text-[20px] font-medium text-ink">{{ invoices?.length || 0 }}</p><p class="text-[10px] text-muted">счетов</p></div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.4fr_.9fr]">
            <div class="space-y-5">
                <Card :padded="false">
                    <template #header>
                        <div class="flex w-full items-center justify-between gap-3">
                            <h3 class="text-[14px] font-bold text-ink">Задачи</h3>
                            <Link :href="route('admin.tasks.index', { client_id: client.id })" class="text-[11px] font-semibold text-primary-700 hover:underline">Все</Link>
                        </div>
                    </template>
                    <div v-if="!tasks?.length" class="p-2">
                        <EmptyState compact title="Задач пока нет" text="Создайте задачу вручную или из шаблона.">
                            <Link :href="route('admin.task-templates.index')"><Button size="sm" variant="secondary">Шаблоны</Button></Link>
                        </EmptyState>
                    </div>
                    <div v-else class="divide-y divide-line/60">
                        <Link v-for="task in tasks" :key="task.id" :href="route('admin.tasks.show', task.id)" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-ink/[0.025]">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[12px] font-semibold text-ink">{{ task.title }}</p>
                                <p class="mt-0.5 text-[10px] text-muted">{{ task.deadline ? `до ${formatDate(task.deadline)}` : 'без срока' }} · {{ formatDateTime(task.created_at) }}</p>
                            </div>
                            <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                        </Link>
                    </div>
                </Card>

                <Card :padded="false">
                    <template #header>
                        <div class="flex w-full items-center justify-between gap-3">
                            <h3 class="text-[14px] font-bold text-ink">Счета</h3>
                            <Link :href="route('admin.invoices.index', { client_id: client.id })" class="text-[11px] font-semibold text-primary-700 hover:underline">Все</Link>
                        </div>
                    </template>
                    <div v-if="!invoices?.length" class="px-5 py-6 text-[11px] text-muted">Счетов пока нет.</div>
                    <div v-else class="divide-y divide-line/60">
                        <Link v-for="invoice in invoices" :key="invoice.id" :href="route('admin.invoices.show', invoice.id)" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-ink/[0.025]">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-[12px] font-semibold text-ink">{{ invoice.description }}</p>
                                <p class="mt-0.5 text-[10px] text-muted">{{ formatDate(invoice.created_at) }}</p>
                            </div>
                            <span class="font-mono text-[12px] font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </Link>
                    </div>
                </Card>
            </div>

            <div class="space-y-5">
                <Card title="Контакты">
                    <dl class="space-y-3 text-[12px]">
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Email</dt><dd class="mt-0.5 text-ink">{{ client.email }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Телефон</dt><dd class="mt-0.5 text-ink">{{ client.phone || '—' }}</dd></div>
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Создан</dt><dd class="mt-0.5 text-ink">{{ formatDateTime(client.created_at) }}</dd></div>
                    </dl>
                </Card>

                <Card title="Поля клиента">
                    <form @submit.prevent="saveFields">
                        <CustomFieldsForm v-model="fieldForm.fields" :fields="customFields" :errors="fieldForm.errors" />
                        <div class="mt-4 flex justify-end">
                            <Button type="submit" size="sm" :disabled="fieldForm.processing">Сохранить поля</Button>
                        </div>
                    </form>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
