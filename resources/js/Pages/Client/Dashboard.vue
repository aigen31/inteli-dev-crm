<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import { taskStatusLabels, invoiceStatusLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime } from '@/Utils/formatters.js';
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
    { label: 'Всего задач', value: totalTasks, icon: ClipboardDocumentListIcon, color: 'bg-primary-100 text-primary-600' },
    { label: 'Ожидают оценки', value: props.taskStats?.awaiting_review ?? 0, icon: ExclamationTriangleIcon, color: 'bg-purple-100 text-purple-600' },
    { label: 'Неоплаченных счетов', value: props.pendingInvoices?.length ?? 0, icon: CurrencyDollarIcon, color: 'bg-amber-100 text-amber-600' },
];
</script>

<template>
    <ClientLayout title="Дашборд">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
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
            <Card title="Ожидают оценки">
                <div v-if="!awaitingReview?.length" class="text-sm text-gray-400 text-center py-4">
                    Нет задач для оценки
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <Link
                        v-for="task in awaitingReview"
                        :key="task.id"
                        :href="route('client.tasks.show', task.id)"
                        class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded transition-colors"
                    >
                        <p class="text-sm font-medium text-gray-700">{{ task.title }}</p>
                        <Badge status="awaiting_review" type="task">{{ taskStatusLabels.awaiting_review }}</Badge>
                    </Link>
                </div>
            </Card>

            <Card title="Неоплаченные счета">
                <div v-if="!pendingInvoices?.length" class="text-sm text-gray-400 text-center py-4">
                    Нет неоплаченных счетов
                </div>
                <div v-else class="divide-y divide-gray-100">
                    <Link
                        v-for="invoice in pendingInvoices"
                        :key="invoice.id"
                        :href="route('client.invoices.show', invoice.id)"
                        class="flex items-center justify-between py-3 hover:bg-gray-50 -mx-2 px-2 rounded transition-colors"
                    >
                        <div>
                            <p class="text-sm font-medium text-gray-700">{{ invoice.task?.title }}</p>
                            <p class="text-xs text-gray-400">{{ invoice.description }}</p>
                        </div>
                        <span class="text-sm font-semibold text-gray-800">{{ formatMoney(invoice.amount) }}</span>
                    </Link>
                </div>
            </Card>
        </div>
    </ClientLayout>
</template>
