<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import CustomFieldsView from '@/Components/CustomFields/CustomFieldsView.vue';
import { invoiceStatusLabels, paymentMethodLabels, recurringIntervalLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    invoice: Object,
    customFields: Array,
});

function cancelInvoice() {
    if (window.confirm('Отменить счёт?')) router.patch(route('admin.invoices.cancel', props.invoice.id));
}

function markPaid() {
    if (window.confirm('Подтвердить поступление денег по этому счёту?')) router.patch(route('admin.invoices.mark-paid', props.invoice.id));
}

function refundInvoice() {
    if (window.confirm('Инициировать возврат средств?')) router.post(route('admin.invoices.refund', props.invoice.id));
}
</script>

<template>
    <AdminLayout title="Счёт">
        <template #actions>
            <Link :href="route('admin.invoices.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Все счета</Button></Link>
            <Button v-if="invoice.status === 'pending' && invoice.payment_method === 'transfer'" size="sm" @click="markPaid">Подтвердить оплату</Button>
            <Button v-if="invoice.status === 'pending'" variant="danger" size="sm" @click="cancelInvoice">Отменить</Button>
            <Button v-if="invoice.status === 'paid' && invoice.payment_method === 'yookassa'" variant="secondary" size="sm" @click="refundInvoice">Возврат</Button>
        </template>

        <section class="surface-card mb-5 flex flex-wrap items-end justify-between gap-4 p-5 sm:p-6">
            <div>
                <p class="eyebrow">Счёт INV-{{ invoice.id.slice(0, 8).toUpperCase() }}</p>
                <p class="mt-2 font-display text-[32px] font-medium tracking-[-0.04em] text-ink">{{ formatMoney(invoice.amount, invoice.currency) }}</p>
                <p class="mt-1 text-[12px] text-muted">{{ invoice.description }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                <Badge :status="invoice.payment_method" type="payment">{{ paymentMethodLabels[invoice.payment_method] }}</Badge>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.4fr_.8fr]">
            <div class="space-y-5">
                <Card title="Детали">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Клиент</dt><dd class="mt-1 text-[12px] text-ink"><Link :href="route('admin.clients.show', invoice.client_id)" class="text-primary-700 hover:underline">{{ invoice.client?.name }}</Link></dd></div>
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Задача</dt><dd class="mt-1 text-[12px] text-ink"><Link :href="route('admin.tasks.show', invoice.task_id)" class="text-primary-700 hover:underline">{{ invoice.task?.title }}</Link></dd></div>
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Создан</dt><dd class="mt-1 text-[12px] text-ink">{{ formatDateTime(invoice.created_at) }}</dd></div>
                        <div v-if="invoice.paid_at"><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Оплачен</dt><dd class="mt-1 text-[12px] text-success">{{ formatDateTime(invoice.paid_at) }}</dd></div>
                        <div v-if="invoice.is_recurring"><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Интервал</dt><dd class="mt-1 text-[12px] text-ink">{{ recurringIntervalLabels[invoice.recurring_interval] }}</dd></div>
                        <div v-if="invoice.recurring_next_date"><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Следующий платёж</dt><dd class="mt-1 text-[12px] text-ink">{{ formatDate(invoice.recurring_next_date) }}</dd></div>
                    </dl>
                </Card>

                <Card v-if="customFields?.length" title="Поля счёта">
                    <CustomFieldsView :fields="customFields" />
                </Card>
            </div>

            <aside class="space-y-5">
                <Card v-if="invoice.payment_method === 'yookassa'" title="Платёж ЮКасса">
                    <dl class="space-y-3 text-[12px]">
                        <div><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">ID платежа</dt><dd class="mt-1 break-all font-mono text-[10px] text-ink">{{ invoice.yookassa_payment_id || '—' }}</dd></div>
                        <div v-if="invoice.yookassa_payment_method_id"><dt class="text-[10px] uppercase tracking-[0.08em] text-muted">Способ</dt><dd class="mt-1 break-all font-mono text-[10px] text-ink">{{ invoice.yookassa_payment_method_id }}</dd></div>
                    </dl>
                </Card>
                <Card v-else title="Прямой перевод">
                    <p class="text-[12px] leading-relaxed text-ink-soft">Клиент переводит сумму на ваши реквизиты. Автоматического подтверждения нет — отметьте поступление вручную.</p>
                    <div class="mt-3 rounded-2xl bg-surface-2/60 px-4 py-3">
                        <p class="text-[9px] uppercase tracking-[0.08em] text-muted">Статус</p>
                        <p :class="['mt-1 text-[12px] font-semibold', invoice.status === 'paid' ? 'text-success' : 'text-warning']">{{ invoice.status === 'paid' ? 'Поступление подтверждено' : 'Ожидает подтверждения' }}</p>
                    </div>
                </Card>
            </aside>
        </div>
    </AdminLayout>
</template>
