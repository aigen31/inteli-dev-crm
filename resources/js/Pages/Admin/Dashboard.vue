<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import { taskStatusLabels, invoiceStatusLabels } from '@/Utils/constants.js';
import { formatMoney, formatDate, formatDateTime } from '@/Utils/formatters.js';
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
    { label: 'Всего задач', value: Object.values(props.taskStats || {}).reduce((a, b) => a + b, 0), icon: ClipboardDocumentListIcon, color: 'bg-primary-100 text-primary-600' },
    { label: 'В работе', value: props.taskStats?.in_progress ?? 0, icon: ClockIcon, color: 'bg-indigo-100 text-indigo-600' },
    { label: 'Ожидают оценки', value: props.taskStats?.awaiting_review ?? 0, icon: ExclamationTriangleIcon, color: 'bg-purple-100 text-purple-600' },
    { label: 'Доход за месяц', value: formatMoney(props.monthlyRevenue ?? 0), icon: CurrencyDollarIcon, color: 'bg-green-100 text-green-600' },
];
</script>

<template>
    <AdminLayout title="Дашборд">
        <!-- Stat cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-6"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ stat.label }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800">{{ stat.value }}</p>
                    </div>
                    <div :class="['w-10 h-10 rounded-full flex items-center justify-center', stat.color]">
                        <component :is="stat.icon" class="w-5 h-5" />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Pending tasks -->
            <Card title="Заявки на рассмотрении">
                <div v-if="!pendingTasks?.length" class="text-sm text-gray-400 text-center py-4">
                    Нет новых заявок
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <Link
                        v-for="task in pendingTasks"
                        :key="task.id"
                        :href="route('admin.tasks.show', task.id)"
                        class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded transition-colors"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ task.title }}</p>
                            <p class="text-xs text-gray-400">{{ task.client?.name }} &middot; {{ formatDateTime(task.created_at) }}</p>
                        </div>
                        <Badge status="pending_review" type="task">{{ taskStatusLabels.pending_review }}</Badge>
                    </Link>
                </div>
            </Card>

            <!-- Pending invoices -->
            <Card title="Ожидают оплаты">
                <div v-if="!pendingInvoices?.length" class="text-sm text-gray-400 text-center py-4">
                    Нет неоплаченных счетов
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <Link
                        v-for="invoice in pendingInvoices"
                        :key="invoice.id"
                        :href="route('admin.invoices.show', invoice.id)"
                        class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded transition-colors"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ invoice.description }}</p>
                            <p class="text-xs text-gray-400">{{ invoice.client?.name }}</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-800">{{ formatMoney(invoice.amount) }}</span>
                    </Link>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
