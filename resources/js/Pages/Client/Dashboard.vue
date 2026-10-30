<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatMoney } from '@/Utils/formatters.js';
import {
    ClipboardDocumentListIcon,
    ExclamationTriangleIcon,
    CurrencyDollarIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    taskStats: Object,
    awaitingReview: Array,
    pendingInvoices: Array,
});

const totalTasks = Object.values(props.taskStats || {}).reduce((a, b) => a + b, 0);

const statCards = [
    {
        label: 'Всего задач',
        value: totalTasks,
        icon: ClipboardDocumentListIcon,
        tile: 'bg-primary-soft text-primary-300',
    },
    {
        label: 'Ожидают оценки',
        value: props.taskStats?.awaiting_review ?? 0,
        icon: ExclamationTriangleIcon,
        tile: 'bg-[#d7b7ff]/10 text-[#d7b7ff]',
    },
    {
        label: 'Неоплаченных счетов',
        value: props.pendingInvoices?.length ?? 0,
        icon: CurrencyDollarIcon,
        tile: 'bg-warning/10 text-warning',
    },
];
</script>

<template>
    <ClientLayout title="Дашборд">
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="rounded-[14px] border border-line bg-surface p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-muted">{{ stat.label }}</p>
                        <p class="mt-3 text-[28px] font-semibold tracking-tight text-ink tabular-nums">
                            {{ stat.value }}
                        </p>
                    </div>
                    <div :class="['flex h-10 w-10 items-center justify-center rounded-[10px]', stat.tile]">
                        <component :is="stat.icon" class="h-5 w-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Работа</p>
                            <h3 class="mt-1 text-base font-semibold text-ink">Ожидают оценки</h3>
                        </div>
                        <span class="rounded-full border border-line px-2.5 py-0.5 font-mono text-[11px] text-ink-soft">
                            {{ awaitingReview?.length ?? 0 }}
                        </span>
                    </div>
                </template>
                <div
                    v-if="!awaitingReview?.length"
                    class="py-6 text-center font-mono text-[11px] uppercase tracking-[0.14em] text-faint"
                >
                    Нет задач для оценки
                </div>
                <div v-else class="divide-y divide-line">
                    <Link
                        v-for="task in awaitingReview"
                        :key="task.id"
                        :href="route('client.tasks.show', task.id)"
                        class="-mx-2 flex items-center justify-between rounded-[10px] px-2 py-3 transition-colors hover:bg-white/5"
                    >
                        <p class="text-sm font-medium text-ink-soft">{{ task.title }}</p>
                        <Badge status="awaiting_review" type="task">{{ taskStatusLabels.awaiting_review }}</Badge>
                    </Link>
                </div>
            </Card>

            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Оплаты</p>
                            <h3 class="mt-1 text-base font-semibold text-ink">Неоплаченные счета</h3>
                        </div>
                        <span class="rounded-full border border-line px-2.5 py-0.5 font-mono text-[11px] text-ink-soft">
                            {{ pendingInvoices?.length ?? 0 }}
                        </span>
                    </div>
                </template>
                <div
                    v-if="!pendingInvoices?.length"
                    class="py-6 text-center font-mono text-[11px] uppercase tracking-[0.14em] text-faint"
                >
                    Нет неоплаченных счетов
                </div>
                <div v-else class="divide-y divide-line">
                    <Link
                        v-for="invoice in pendingInvoices"
                        :key="invoice.id"
                        :href="route('client.invoices.show', invoice.id)"
                        class="-mx-2 flex items-center justify-between rounded-[10px] px-2 py-3 transition-colors hover:bg-white/5"
                    >
                        <div>
                            <p class="text-sm font-medium text-ink-soft">{{ invoice.task?.title }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-faint">{{ invoice.description }}</p>
                        </div>
                        <span class="font-mono text-sm font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                    </Link>
                </div>
            </Card>
        </div>
    </ClientLayout>
</template>
