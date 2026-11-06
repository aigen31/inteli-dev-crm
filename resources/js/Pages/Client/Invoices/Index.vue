<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { invoiceStatusLabels } from '@/Utils/constants.js';
import { formatDateTime, formatMoney } from '@/Utils/formatters.js';

const props = defineProps({
    invoices: Object,
    filters: Object,
    statuses: Array,
});

const filters = [
    { value: '', label: 'Все' },
    { value: 'pending', label: 'К оплате' },
    { value: 'paid', label: 'Оплачены' },
];

function setStatus(value) {
    router.get(route('client.invoices.index'), { status: value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <ClientLayout title="Мои счета">
        <div class="mb-4 inline-flex flex-wrap gap-1 rounded-full border border-line bg-white/50 p-1">
            <button
                v-for="item in filters"
                :key="item.value"
                type="button"
                :class="[
                    'rounded-full px-3 py-1.5 text-[11px] font-semibold transition',
                    (props.filters?.status || '') === item.value ? 'bg-ink text-canvas' : 'text-muted hover:text-ink',
                ]"
                @click="setStatus(item.value)"
            >
                {{ item.label }}
            </button>
        </div>

        <div v-if="!invoices.data.length" class="surface-card">
            <EmptyState image="/images/kiki/kiki-invoices-coin.webp" title="Счетов пока нет" text="Когда работа будет оценена, владелец выставит счёт — он появится здесь." />
        </div>

        <div v-else class="space-y-2.5">
            <Link
                v-for="invoice in invoices.data"
                :key="invoice.id"
                :href="route('client.invoices.show', invoice.id)"
                class="surface-card surface-card-hover flex flex-wrap items-center gap-3 p-4 sm:p-5"
            >
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-bold text-ink">{{ invoice.description }}</span>
                    <span class="mt-1 block text-[10px] text-muted">{{ invoice.task?.title || 'без задачи' }} · {{ formatDateTime(invoice.created_at) }}</span>
                </span>
                <span class="font-mono text-[14px] font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
            </Link>
        </div>

        <Pagination :links="invoices" />
    </ClientLayout>
</template>
