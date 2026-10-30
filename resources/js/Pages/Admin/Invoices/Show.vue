<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { invoiceStatusLabels, paymentMethodLabels, recurringIntervalLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime, formatDate } from '@/Utils/formatters.js';

const props = defineProps({
    invoice: Object,
});

function cancelInvoice() {
    if (confirm('Отменить счёт?')) {
        router.patch(route('admin.invoices.cancel', props.invoice.id));
    }
}

function markPaid() {
    if (confirm('Подтвердить поступление денег по этому счёту?')) {
        router.patch(route('admin.invoices.mark-paid', props.invoice.id));
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
        <template #actions>
            <div class="flex flex-wrap gap-3">
                <Button
                    v-if="invoice.status === 'pending' && invoice.payment_method === 'transfer'"
                    @click="markPaid"
                >
                    Подтвердить оплату
                </Button>
                <Button v-if="invoice.status === 'pending'" variant="danger" @click="cancelInvoice">Отменить</Button>
                <Button
                    v-if="invoice.status === 'paid' && invoice.payment_method === 'yookassa'"
                    variant="secondary"
                    @click="refundInvoice"
                >
                    Возврат
                </Button>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <Card title="Детали счёта">
                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs text-muted">Описание</dt>
                            <dd class="text-sm text-ink-soft">{{ invoice.description }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Сумма</dt>
                            <dd class="text-lg font-bold text-ink">{{ formatMoney(invoice.amount, invoice.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Статус</dt>
                            <dd><Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge></dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Способ оплаты</dt>
                            <dd>
                                <Badge :status="invoice.payment_method" type="payment">
                                    {{ paymentMethodLabels[invoice.payment_method] }}
                                </Badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Задача</dt>
                            <dd class="text-sm text-ink-soft">{{ invoice.task?.title }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Клиент</dt>
                            <dd class="text-sm text-ink-soft">{{ invoice.client?.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Создан</dt>
                            <dd class="text-sm text-ink-soft">{{ formatDateTime(invoice.created_at) }}</dd>
                        </div>
                        <div v-if="invoice.paid_at">
                            <dt class="text-xs text-muted">Оплачен</dt>
                            <dd class="text-sm text-ink-soft">{{ formatDateTime(invoice.paid_at) }}</dd>
                        </div>
                        <div v-if="invoice.is_recurring">
                            <dt class="text-xs text-muted">Интервал</dt>
                            <dd class="text-sm text-ink-soft">{{ recurringIntervalLabels[invoice.recurring_interval] }}</dd>
                        </div>
                        <div v-if="invoice.recurring_next_date">
                            <dt class="text-xs text-muted">Следующий платёж</dt>
                            <dd class="text-sm text-ink-soft">{{ formatDate(invoice.recurring_next_date) }}</dd>
                        </div>
                    </dl>
                </Card>
            </div>

            <div>
                <Card v-if="invoice.payment_method === 'yookassa'" title="Платёж ЮКасса">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-muted">ID платежа</dt>
                            <dd class="font-mono text-sm text-ink-soft">{{ invoice.yookassa_payment_id || '—' }}</dd>
                        </div>
                        <div v-if="invoice.yookassa_payment_method_id">
                            <dt class="text-xs text-muted">Способ оплаты</dt>
                            <dd class="font-mono text-sm text-ink-soft">{{ invoice.yookassa_payment_method_id }}</dd>
                        </div>
                    </dl>
                </Card>

                <Card v-else title="Прямой перевод">
                    <p class="font-mono text-[10px] uppercase tracking-[0.14em] text-muted">Оплата вне кассы</p>
                    <p class="mt-3 text-sm text-ink-soft">
                        Клиент переводит сумму на ваши реквизиты из настроек. Автоматического подтверждения нет —
                        отметьте поступление вручную.
                    </p>
                    <div class="mt-4 rounded-[10px] border border-line bg-white/[0.03] px-4 py-3">
                        <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Статус подтверждения</p>
                        <p class="mt-1 text-sm font-medium" :class="invoice.status === 'paid' ? 'text-success' : 'text-warning'">
                            {{ invoice.status === 'paid' ? 'Поступление подтверждено' : 'Ожидает подтверждения' }}
                        </p>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
