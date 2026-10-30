<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Badge from '@/Components/UI/Badge.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { invoiceStatusLabels } from '@/Utils/constants.js';
import { formatMoney, formatDateTime } from '@/Utils/formatters.js';

const props = defineProps({
    invoices: Object,
    filters: Object,
    statuses: Array,
});

function applyFilter(key, value) {
    router.get(route('admin.invoices.index'), {
        ...props.filters,
        [key]: value || undefined,
    }, { preserveState: true });
}
</script>

<template>
    <AdminLayout title="Счета">
        <template #actions>
            <Link :href="route('admin.invoices.create')">
                <Button>Создать счёт</Button>
            </Link>
        </template>

        <div class="flex gap-4 mb-4">
            <div class="w-48">
                <SelectInput
                    :model-value="filters.status || ''"
                    :options="statuses"
                    placeholder="Все статусы"
                    @update:model-value="applyFilter('status', $event)"
                />
            </div>
        </div>

        <div class="bg-surface rounded-[14px] shadow-sm border border-line overflow-hidden">
            <table class="min-w-full divide-y divide-line">
                <thead class="bg-white/[0.03]">
                    <tr>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Описание</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Клиент</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Задача</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Сумма</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Статус</th>
                        <th class="px-6 py-3 text-left font-mono text-[11px] uppercase tracking-[0.12em] text-muted">Дата</th>
                    </tr>
                </thead>
                <tbody class="bg-surface divide-y divide-line">
                    <tr v-if="!invoices.data.length">
                        <td colspan="6" class="px-6 py-10 text-center font-mono text-[11px] uppercase tracking-[0.14em] text-faint">
                            Счетов пока нет
                        </td>
                    </tr>
                    <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-white/5 transition-colors">
                        <td class="px-6 py-4 text-sm">
                            <Link :href="route('admin.invoices.show', invoice.id)" class="font-medium text-primary-300 hover:text-primary-200">
                                {{ invoice.description }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ invoice.client?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ invoice.task?.title }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-ink-soft">{{ formatMoney(invoice.amount) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-muted">{{ formatDateTime(invoice.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="invoices" />
        </div>
    </AdminLayout>
</template>
