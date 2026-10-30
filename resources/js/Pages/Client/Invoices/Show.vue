<script setup>
import { ref } from 'vue';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { invoiceStatusLabels, paymentMethodLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime } from '@/Utils/formatters.js';

const props = defineProps({
    invoice: Object,
    transfer: Object,
});

const copied = ref(null);

async function copy(value) {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        setTimeout(() => {
            if (copied.value === value) copied.value = null;
        }, 1500);
    } catch {
        copied.value = null;
    }
}
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
                        <dt class="text-xs text-muted">Способ оплаты</dt>
                        <dd>
                            <Badge :status="invoice.payment_method" type="payment">
                                {{ paymentMethodLabels[invoice.payment_method] }}
                            </Badge>
                        </dd>
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

                <!-- ЮКасса: онлайн-оплата -->
                <div
                    v-if="invoice.status === 'pending' && invoice.payment_method === 'yookassa'"
                    class="mt-6 border-t border-line pt-4"
                >
                    <a :href="route('client.invoices.pay', invoice.id)">
                        <Button class="w-full">Оплатить {{ formatMoney(invoice.amount) }}</Button>
                    </a>
                    <p class="mt-2 text-center text-xs text-faint">Оплата картой на стороне ЮКассы</p>
                </div>

                <!-- Прямой перевод: реквизиты, оплата вне кассы -->
                <div
                    v-if="invoice.status === 'pending' && invoice.payment_method === 'transfer'"
                    class="mt-6 border-t border-line pt-5"
                >
                    <p class="font-mono text-[10px] uppercase tracking-[0.16em] text-muted">Прямой перевод</p>
                    <h3 class="mt-1 text-base font-semibold text-ink">Оплата по реквизитам</h3>
                    <p class="mt-2 text-sm text-ink-soft">
                        Переведите {{ formatMoney(invoice.amount) }} на реквизиты ниже. После поступления
                        администратор отметит счёт оплаченным.
                    </p>

                    <div v-if="transfer?.configured" class="mt-4 overflow-hidden rounded-[10px] border border-line">
                        <div
                            v-for="item in transfer.items"
                            :key="item.label"
                            class="flex items-center justify-between gap-4 border-b border-line px-4 py-3 last:border-b-0"
                        >
                            <div class="min-w-0">
                                <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-muted">{{ item.label }}</p>
                                <p class="mt-1 break-all font-mono text-sm text-ink">{{ item.value }}</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-lg border border-line px-2.5 py-1 text-xs text-ink-soft transition-colors hover:border-line-strong hover:text-white"
                                @click="copy(item.value)"
                            >
                                {{ copied === item.value ? 'Скопировано' : 'Копировать' }}
                            </button>
                        </div>
                    </div>

                    <p v-else class="mt-4 rounded-[10px] border border-warning/30 bg-warning/10 px-4 py-3 text-sm text-warning">
                        Реквизиты для перевода ещё не заполнены — уточните их у администратора.
                    </p>

                    <p v-if="transfer?.note" class="mt-3 whitespace-pre-wrap text-xs text-faint">{{ transfer.note }}</p>
                    <p class="mt-3 font-mono text-[10px] uppercase tracking-[0.12em] text-faint">
                        Оплата принимается вне кассы, автоматического подтверждения нет
                    </p>
                </div>
            </Card>
        </div>
    </ClientLayout>
</template>
