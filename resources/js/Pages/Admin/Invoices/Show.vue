<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { invoiceStatusLabels, recurringIntervalLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime, formatDate } from '@/Utils/formatters.js';

const props = defineProps({
    invoice: Object,
});

function cancelInvoice() {
    if (confirm('Отменить счёт?')) {
        router.patch(route('admin.invoices.cancel', props.invoice.id));
    }
}

function refundInvoice() {
    if (confirm('Инициировать возврат средств?')) {
        router.post(route('admin.invoices.refund', props.invoice.id));
    }
}
</script>

<template>
    <AdminLayout title="Счёт">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Счёт</h2>
            <div class="flex space-x-3">
                <Button v-if="invoice.status === 'pending'" variant="danger" @click="cancelInvoice">Отменить</Button>
                <Button v-if="invoice.status === 'paid'" variant="secondary" @click="refundInvoice">Возврат</Button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <Card title="Детали счёта">
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-gray-500">Описание</dt>
                            <dd class="text-sm text-gray-700">{{ invoice.description }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Сумма</dt>
                            <dd class="text-lg font-bold text-gray-800">{{ formatMoney(invoice.amount, invoice.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Статус</dt>
                            <dd><Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Задача</dt>
                            <dd class="text-sm text-gray-700">{{ invoice.task?.title }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Клиент</dt>
                            <dd class="text-sm text-gray-700">{{ invoice.client?.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Создан</dt>
                            <dd class="text-sm text-gray-700">{{ formatDateTime(invoice.created_at) }}</dd>
                        </div>
                        <div v-if="invoice.paid_at">
                            <dt class="text-xs text-gray-500">Оплачен</dt>
                            <dd class="text-sm text-gray-700">{{ formatDateTime(invoice.paid_at) }}</dd>
                        </div>
                        <div v-if="invoice.is_recurring">
                            <dt class="text-xs text-gray-500">Интервал</dt>
                            <dd class="text-sm text-gray-700">{{ recurringIntervalLabels[invoice.recurring_interval] }}</dd>
                        </div>
                        <div v-if="invoice.recurring_next_date">
                            <dt class="text-xs text-gray-500">Следующий платёж</dt>
                            <dd class="text-sm text-gray-700">{{ formatDate(invoice.recurring_next_date) }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>

            <div>
                <Card title="Платёж ЮКасса">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-gray-500">ID платежа</dt>
                            <dd class="text-sm text-gray-700 font-mono">{{ invoice.yookassa_payment_id || '—' }}</dd>
                        </div>
                        <div v-if="invoice.yookassa_payment_method_id">
                            <dt class="text-xs text-gray-500">Способ оплаты</dt>
                            <dd class="text-sm text-gray-700 font-mono">{{ invoice.yookassa_payment_method_id }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
