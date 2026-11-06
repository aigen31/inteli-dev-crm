<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import { invoiceStatusLabels, paymentMethodLabels } from '@/Utils/constants.js';
import { formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { BanknotesIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    invoices: Object,
    filters: Object,
    statuses: Array,
    summary: Object,
});

const chips = [
    { value: '', label: 'Все' },
    { value: 'pending', label: 'Ждут оплаты' },
    { value: 'paid', label: 'Оплачены' },
    { value: 'cancelled', label: 'Отменены' },
];

function setStatus(value) {
    router.get(route('admin.invoices.index'), { status: value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <AdminLayout title="Счета">
        <template #actions>
            <Link :href="route('admin.invoices.create')"><Button><PlusIcon class="h-4 w-4" />Создать счёт</Button></Link>
        </template>

        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-[1fr_1fr_1fr_230px]">
            <StatCard label="Ждут оплаты" :value="formatMoney(summary?.pending ?? 0)" hint="сумма по pending" :icon="BanknotesIcon" tone="peach" mono />
            <StatCard label="Оплачено за месяц" :value="formatMoney(summary?.paid_month ?? 0)" hint="подтверждённые поступления" :icon="BanknotesIcon" tone="sage" mono />
            <StatCard label="Всего счетов" :value="invoices.total" hint="во всех статусах" tone="violet" />
            <div class="relative hidden overflow-hidden rounded-[22px] border border-line/70 bg-white/35 lg:block">
                <img src="/images/kiki/kiki-invoices-coin.webp" alt="Кики разглядывает монету через монокль" class="pointer-events-none absolute inset-0 h-full w-full select-none object-cover object-[50%_38%]" />
            </div>
        </div>

        <div class="mb-4 inline-flex flex-wrap gap-1 rounded-full border border-line bg-white/50 p-1">
            <button
                v-for="chip in chips"
                :key="chip.value"
                type="button"
                :class="['rounded-full px-3 py-1.5 text-[11px] font-semibold transition', (props.filters?.status || '') === chip.value ? 'bg-ink text-canvas' : 'text-muted hover:text-ink']"
                @click="setStatus(chip.value)"
            >
                {{ chip.label }}
            </button>
        </div>

        <div v-if="!invoices.data.length" class="surface-card">
            <EmptyState image="/images/kiki/kiki-invoices-envelope.png" title="Счетов пока нет" text="Счёт создаётся по задаче. Выберите задачу, сумму и способ оплаты.">
                <Link :href="route('admin.invoices.create')"><Button size="sm">Создать счёт</Button></Link>
            </EmptyState>
        </div>

        <div v-else class="surface-card overflow-hidden">
            <div class="divide-y divide-line/60">
                <Link v-for="invoice in invoices.data" :key="invoice.id" :href="route('admin.invoices.show', invoice.id)" class="grid grid-cols-1 gap-3 px-5 py-4 transition hover:bg-ink/[0.025] sm:grid-cols-[1.5fr_.9fr_.6fr_auto] sm:items-center sm:px-6">
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold text-ink">{{ invoice.description }}</p>
                        <p class="mt-0.5 truncate text-[10px] text-muted">{{ invoice.client?.name }} · {{ invoice.task?.title }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        <Badge :status="invoice.payment_method" type="payment">{{ paymentMethodLabels[invoice.payment_method] }}</Badge>
                    </div>
                    <p class="font-mono text-[13px] font-semibold text-ink">{{ formatMoney(invoice.amount) }}</p>
                    <p class="font-mono text-[9px] text-faint">{{ formatDateTime(invoice.created_at) }}</p>
                </Link>
            </div>
            <Pagination :links="invoices" />
        </div>
    </AdminLayout>
</template>
