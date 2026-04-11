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
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Счета</h2>
            <Link :href="route('admin.invoices.create')">
                <Button>Создать счёт</Button>
            </Link>
        </div>

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

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Описание</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Клиент</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Задача</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Сумма</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Статус</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Дата</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm">
                            <Link :href="route('admin.invoices.show', invoice.id)" class="font-medium text-primary-600 hover:text-primary-700">
                                {{ invoice.description }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ invoice.client?.name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ invoice.task?.title }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-700">{{ formatMoney(invoice.amount) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <Badge :status="invoice.status" type="invoice">{{ invoiceStatusLabels[invoice.status] }}</Badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDateTime(invoice.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <Pagination :links="invoices" />
        </div>
    </AdminLayout>
</template>
