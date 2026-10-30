<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import CustomFieldsView from '@/Components/CustomFields/CustomFieldsView.vue';
import { invoiceStatusLabels, paymentMethodLabels } from '@/Utils/constants.js';
import { formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { ArrowLeftIcon, CheckIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    invoice: Object,
    transfer: Object,
    customFields: Array,
});

const copied = ref(null);

async function copy(value) {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = value;
        window.setTimeout(() => {
            if (copied.value === value) copied.value = null;
        }, 1500);
    } catch {
        copied.value = null;
    }
}
</script>

<template>
    <ClientLayout title="Счёт">
        <template #actions>
            <Link :href="route('client.invoices.index')">
                <Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Все счета</Button>
            </Link>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.3fr_.8fr]">
            <div class="space-y-5">
                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">К оплате</p>
                    <p class="mt-2 font-display text-[34px] font-medium tracking-[-0.04em] text-ink">{{ formatMoney(invoice.amount, invoice.currency) }}</p>
                    <p class="mt-1 text-[12px] text-muted">{{ invoice.description }}</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        <Badge :status="invoice.payment_method" type="payment">{{ paymentMethodLabels[invoice.payment_method] }}</Badge>
                        <span class="rounded-full bg-surface-2 px-2.5 py-1 font-mono text-[9px] text-muted">{{ invoice.task?.title || 'без задачи' }}</span>
                    </div>
                    <p v-if="invoice.paid_at" class="mt-3 text-[11px] text-success">Оплачен {{ formatDateTime(invoice.paid_at) }}</p>
                </section>

                <section v-if="invoice.status === 'pending' && invoice.payment_method === 'yookassa'" class="surface-card p-5">
                    <p class="eyebrow">Онлайн-оплата</p>
                    <p class="mt-2 text-[12px] text-ink-soft">Оплата картой на стороне ЮКассы. После подтверждения статус обновится автоматически.</p>
                    <a :href="route('client.invoices.pay', invoice.id)" class="mt-4 block"><Button class="w-full">Оплатить {{ formatMoney(invoice.amount) }}</Button></a>
                </section>

                <section v-if="invoice.status === 'pending' && invoice.payment_method === 'transfer'" class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Прямой перевод</p>
                    <h2 class="mt-1 text-[16px] font-bold text-ink">Оплата по реквизитам</h2>
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">Переведите сумму на реквизиты ниже. После поступления владелец отметит счёт оплаченным.</p>

                    <div v-if="transfer?.configured" class="mt-4 divide-y divide-line/60 overflow-hidden rounded-2xl border border-line">
                        <div v-for="item in transfer.items" :key="item.label" class="flex items-center justify-between gap-3 px-4 py-3">
                            <div class="min-w-0">
                                <p class="text-[9px] uppercase tracking-[0.08em] text-muted">{{ item.label }}</p>
                                <p class="mt-0.5 break-all font-mono text-[11px] text-ink">{{ item.value }}</p>
                            </div>
                            <button type="button" class="shrink-0 rounded-lg p-1.5 text-muted hover:bg-ink/[0.05] hover:text-ink" @click="copy(item.value)">
                                <CheckIcon v-if="copied === item.value" class="h-4 w-4 text-success" />
                                <ClipboardDocumentIcon v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <p v-else class="mt-4 rounded-2xl border border-warning/30 bg-warning/[0.07] px-4 py-3 text-[11px] text-warning">Реквизиты ещё не заполнены — уточните у владельца.</p>
                    <p v-if="transfer?.note" class="mt-3 whitespace-pre-wrap text-[10px] text-faint">{{ transfer.note }}</p>
                </section>

                <Card v-if="customFields?.length" title="Детали счёта">
                    <CustomFieldsView :fields="customFields" />
                </Card>
            </div>

            <aside class="space-y-5">
                <Card title="Детали">
                    <dl class="space-y-3 text-[12px]">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Счёт</dt><dd class="font-mono text-[10px] text-ink">INV-{{ invoice.id.slice(0, 8).toUpperCase() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Выставлен</dt><dd class="text-ink">{{ formatDateTime(invoice.created_at) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Задача</dt><dd class="max-w-[180px] truncate text-ink">{{ invoice.task?.title || '—' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">Способ</dt><dd class="text-ink">{{ paymentMethodLabels[invoice.payment_method] }}</dd></div>
                    </dl>
                </Card>
                <div class="surface-card flex items-center gap-3 p-4">
                    <img src="/images/kiki/kiki-avatar.webp" alt="Кики" class="h-9 w-9 rounded-full object-cover" />
                    <p class="text-[11px] leading-relaxed text-muted">Вопрос по счёту? Спросите Кики в панели справа — она знает ваши данные.</p>
                </div>
            </aside>
        </div>
    </ClientLayout>
</template>
