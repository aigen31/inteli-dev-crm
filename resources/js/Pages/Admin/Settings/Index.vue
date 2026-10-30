<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';

const props = defineProps({
    yookassa: Object,
    transfer: Object,
});

const form = useForm({
    transfer_recipient: props.transfer?.transfer_recipient || '',
    transfer_inn: props.transfer?.transfer_inn || '',
    transfer_bank: props.transfer?.transfer_bank || '',
    transfer_account: props.transfer?.transfer_account || '',
    transfer_bik: props.transfer?.transfer_bik || '',
    transfer_corr_account: props.transfer?.transfer_corr_account || '',
    transfer_phone: props.transfer?.transfer_phone || '',
    transfer_note: props.transfer?.transfer_note || '',
});

function submit() {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <AdminLayout title="Настройки">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <Card title="Реквизиты для прямого перевода">
                    <p class="mb-5 text-sm text-ink-soft">
                        Эти данные клиент увидит в счетах со способом оплаты «Прямой перевод». Оплата приходит
                        напрямую на ваши реквизиты, вне кассы.
                    </p>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <TextInput v-model="form.transfer_recipient" label="Получатель" :error="form.errors.transfer_recipient" placeholder="ФИО или название" />
                            <TextInput v-model="form.transfer_inn" label="ИНН" :error="form.errors.transfer_inn" />
                            <div class="sm:col-span-2">
                                <TextInput v-model="form.transfer_bank" label="Банк" :error="form.errors.transfer_bank" placeholder="Название банка" />
                            </div>
                            <TextInput v-model="form.transfer_account" label="Счёт получателя" :error="form.errors.transfer_account" />
                            <TextInput v-model="form.transfer_bik" label="БИК" :error="form.errors.transfer_bik" />
                            <TextInput v-model="form.transfer_corr_account" label="Корр. счёт" :error="form.errors.transfer_corr_account" />
                            <TextInput v-model="form.transfer_phone" label="Телефон для СБП" :error="form.errors.transfer_phone" placeholder="+7 ..." />
                            <div class="sm:col-span-2">
                                <TextArea
                                    v-model="form.transfer_note"
                                    label="Комментарий к переводу"
                                    :error="form.errors.transfer_note"
                                    :rows="3"
                                    placeholder="Например: в назначении платежа укажите номер счёта"
                                />
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <Button type="submit" :disabled="form.processing">Сохранить реквизиты</Button>
                        </div>
                    </form>
                </Card>
            </div>

            <div>
                <Card title="ЮКасса">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs text-muted">Shop ID</dt>
                            <dd class="font-mono text-sm text-ink-soft">
                                {{ yookassa?.is_configured ? yookassa.shop_id : 'Не настроен' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Статус</dt>
                            <dd>
                                <span
                                    :class="[
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium',
                                        yookassa?.is_configured ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning',
                                    ]"
                                >
                                    {{ yookassa?.is_configured ? 'Настроен' : 'Требуется настройка' }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                    <p class="mt-4 text-xs text-faint">
                        Для изменения настроек ЮКассы отредактируйте переменные YOOKASSA_SHOP_ID и
                        YOOKASSA_SECRET_KEY в файле .env.
                    </p>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
