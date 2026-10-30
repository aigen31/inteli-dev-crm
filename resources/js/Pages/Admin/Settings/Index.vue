<script setup>
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Card from '@/Components/UI/Card.vue';

defineProps({
    yookassa: Object,
});
</script>

<template>
    <AdminLayout title="Настройки">
        <div class="max-w-2xl">
            <Card title="ЮКасса">
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs text-muted">Shop ID</dt>
                        <dd class="text-sm text-ink-soft font-mono">
                            {{ yookassa?.is_configured ? yookassa.shop_id : 'Не настроен' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted">Статус</dt>
                        <dd>
                            <span
                                :class="[
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                    yookassa?.is_configured ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning',
                                ]"
                            >
                                {{ yookassa?.is_configured ? 'Настроен' : 'Требуется настройка' }}
                            </span>
                        </dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-faint">
                    Для изменения настроек ЮКасса отредактируйте переменные YOOKASSA_SHOP_ID и YOOKASSA_SECRET_KEY в файле .env
                </p>
            </Card>
        </div>
    </AdminLayout>
</template>
