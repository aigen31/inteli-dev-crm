<script setup>
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { invoiceStatusLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime } from '@/Utils/formatters.js';

const props = defineProps({
    invoice: Object,
});
</script>

<template>
    <ClientLayout title="Счёт">
        <div class="max-w-2xl">
            <Card>
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs text-muted">Описание</dt>
                        <dd class="text-sm text-ink-soft">{{ invoice.description }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Задача</dt>
                        <dd class="text-sm text-ink-soft">{{ invoice.task?.title }}</dd>
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
                        <dt class="text-xs text-muted">Дата выставления</dt>
                        <dd class="text-sm text-ink-soft">{{ formatDateTime(invoice.created_at) }}</dd>
                    </div>
                    <div v-if="invoice.paid_at">
                        <dt class="text-xs text-muted">Дата оплаты</dt>
                        <dd class="text-sm text-ink-soft">{{ formatDateTime(invoice.paid_at) }}</dd>
                    </div>
                </dl>

                <div v-if="invoice.status === 'pending'" class="mt-6 pt-4 border-t border-line">
                    <a :href="route('client.invoices.pay', invoice.id)">
                        <Button class="w-full">Оплатить {{ formatMoney(invoice.amount) }}</Button>
                    </a>
                </div>
            </Card>
        </div>
    </ClientLayout>
</template>
