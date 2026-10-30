<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime } from '@/Utils/formatters.js';
import {
    ClipboardDocumentListIcon,
    ClockIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    taskStats: Object,
    pendingTasks: Array,
    pendingInvoices: Array,
    monthlyRevenue: Number,
    upcomingDeadlines: Array,
});

const statCards = [
    {
        label: 'Всего задач',
        value: Object.values(props.taskStats || {}).reduce((a, b) => a + b, 0),
        icon: ClipboardDocumentListIcon,
        tile: 'bg-primary-soft text-primary-300',
    },
    {
        label: 'В работе',
        value: props.taskStats?.in_progress ?? 0,
        icon: ClockIcon,
        tile: 'bg-[#8fd0ff]/10 text-[#8fd0ff]',
    },
    {
        label: 'Ожидают оценки',
        value: props.taskStats?.awaiting_review ?? 0,
        icon: ExclamationTriangleIcon,
        tile: 'bg-[#d7b7ff]/10 text-[#d7b7ff]',
    },
    {
        label: 'Доход за месяц',
        value: formatMoney(props.monthlyRevenue ?? 0),
        icon: CurrencyDollarIcon,
        tile: 'bg-success/10 text-success',
        accent: true,
        mono: true,
    },
];
</script>

<template>
    <AdminLayout title="Дашборд">
        <!-- KPI cards -->
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                :class="[
                    'rounded-[14px] border bg-surface p-6',
                    stat.accent
                        ? 'border-primary-500/30 shadow-[0_0_40px_rgba(139,127,245,0.16)]'
                        : 'border-line',
                ]"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-muted">{{ stat.label }}</p>
                        <p
                            :class="[
                                'mt-3 font-semibold tracking-tight text-ink tabular-nums',
                                stat.mono ? 'font-mono text-[26px]' : 'text-[28px]',
                            ]"
                        >
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
            <!-- Pending tasks -->
            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Очередь</p>
                            <h3 class="mt-1 text-base font-semibold text-ink">Заявки на рассмотрении</h3>
                        </div>
                        <span class="rounded-full border border-line px-2.5 py-0.5 font-mono text-[11px] text-ink-soft">
                            {{ pendingTasks?.length ?? 0 }}
                        </span>
                    </div>
                </template>
                <div
                    v-if="!pendingTasks?.length"
                    class="py-6 text-center font-mono text-[11px] uppercase tracking-[0.14em] text-faint"
                >
                    Нет новых заявок
                </div>
                <div v-else class="divide-y divide-line">
                    <Link
                        v-for="task in pendingTasks"
                        :key="task.id"
                        :href="route('admin.tasks.show', task.id)"
                        class="-mx-2 flex items-center justify-between rounded-[10px] px-2 py-3 transition-colors hover:bg-white/5"
                    >
                        <div>
                            <p class="text-sm font-medium text-ink-soft">{{ task.title }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-faint">
                                {{ task.client?.name }} · {{ formatDateTime(task.created_at) }}
                            </p>
                        </div>
                        <Badge status="pending_review" type="task">{{ taskStatusLabels.pending_review }}</Badge>
                    </Link>
                </div>
            </Card>

            <!-- Pending invoices -->
            <Card>
                <template #header>
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Оплаты</p>
                            <h3 class="mt-1 text-base font-semibold text-ink">Ожидают оплаты</h3>
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
                        :href="route('admin.invoices.show', invoice.id)"
                        class="-mx-2 flex items-center justify-between rounded-[10px] px-2 py-3 transition-colors hover:bg-white/5"
                    >
                        <div>
                            <p class="text-sm font-medium text-ink-soft">{{ invoice.description }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-faint">{{ invoice.client?.name }}</p>
                        </div>
                        <span class="font-mono text-sm font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                    </Link>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
