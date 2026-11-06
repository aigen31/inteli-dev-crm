<script setup>
import { computed, watch } from 'vue';
import { localeTag } from '@/i18n';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';

const props = defineProps({
    tasks: Array,
    selectedTaskId: String,
});

const form = useForm({
    task_id: props.selectedTaskId || '',
    amount: '',
    description: '',
    payment_method: 'yookassa',
    is_recurring: false,
    recurring_interval: '',
    consent: false,
});

const taskOptions = props.tasks.map((task) => ({ value: task.id, label: `${task.title} (${task.client?.name || ''})` }));
const intervalOptions = [
    { value: 'monthly', label: 'Ежемесячно' },
    { value: 'quarterly', label: 'Ежеквартально' },
    { value: 'yearly', label: 'Ежегодно' },
];
const paymentOptions = [
    { value: 'yookassa', label: 'ЮКасса — онлайн-оплата' },
    { value: 'transfer', label: 'Прямой перевод — на мои реквизиты' },
];

const isTransfer = computed(() => form.payment_method === 'transfer');

watch(isTransfer, (transfer) => {
    if (transfer) {
        form.is_recurring = false;
        form.recurring_interval = '';
    }
});

function submit() {
    form.post(route('admin.invoices.store'));
}
</script>

<template>
    <AdminLayout title="Новый счёт">
        <template #actions>
            <Link :href="route('admin.invoices.index')"><Button variant="ghost" size="sm">Назад</Button></Link>
            <Button size="sm" :disabled="form.processing || !form.consent" @click="submit">Создать счёт</Button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.2fr_.8fr]">
            <section class="surface-card p-5 sm:p-6">
                <p class="eyebrow">Основание</p>
                <div class="mt-4 space-y-4">
                    <SelectInput v-model="form.task_id" label="Задача" :options="taskOptions" :error="form.errors.task_id" placeholder="Выберите задачу" />
                    <TextInput v-model="form.amount" label="Сумма, ₽" type="number" step="0.01" min="0.01" :error="form.errors.amount" />
                    <TextArea v-model="form.description" label="Назначение" :error="form.errors.description" placeholder="За что выставлен счёт" />
                    <SelectInput v-model="form.payment_method" label="Способ оплаты" :options="paymentOptions" :error="form.errors.payment_method" placeholder="Выберите способ" />
                    <p class="text-[10px] leading-relaxed text-muted">
                        <template v-if="isTransfer">Клиент увидит реквизиты из <Link :href="route('admin.settings.edit', { tab: 'payments' })" class="text-primary-700 underline">настроек</Link>. Поступление подтвердите вручную.</template>
                        <template v-else>Клиент оплатит картой на стороне ЮКассы, статус обновится автоматически.</template>
                    </p>
                    <template v-if="!isTransfer">
                        <label class="flex items-center gap-2 text-[12px] text-ink-soft">
                            <input v-model="form.is_recurring" type="checkbox" class="h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30" />
                            Рекуррентный платёж
                        </label>
                        <SelectInput v-if="form.is_recurring" v-model="form.recurring_interval" label="Интервал" :options="intervalOptions" :error="form.errors.recurring_interval" />
                    </template>
                    <p v-if="form.errors.is_recurring" class="text-[10px] text-danger-ink">{{ form.errors.is_recurring }}</p>
                    <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="admin-invoice-consent" variant="contract" />
                </div>
            </section>

            <aside class="space-y-4">
                <section class="surface-card p-5">
                    <p class="eyebrow">Подсказка</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">Счёт привязан к задаче и клиенту. После оплаты статус можно подтвердить вручную — для прямого перевода это нормальный путь.</p>
                </section>
                <section class="surface-card p-5">
                    <p class="eyebrow">Итого</p>
                    <p class="mt-2 font-display text-[26px] font-medium tracking-[-0.04em] text-ink">{{ form.amount ? `${Number(form.amount).toLocaleString(localeTag())} ₽` : '—' }}</p>
                    <p class="mt-1 text-[10px] text-muted">{{ isTransfer ? 'прямой перевод' : 'ЮКасса' }}</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
