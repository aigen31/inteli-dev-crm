<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { formatDateTime } from '@/Utils/formatters.js';

defineProps({ reports: Object });
</script>

<template>
    <ClientLayout title="Мои отчёты">
        <p class="mb-5 max-w-[600px] text-[12px] leading-relaxed text-muted">Отчёты, которые вы отправили клиентам. Новый отчёт создаётся из карточки назначенной задачи.</p>

        <div v-if="!reports.data.length" class="surface-card">
            <EmptyState image="/images/kiki/kiki-empty-generic.png" title="Отчётов пока нет" text="Откройте задачу и отправьте клиенту первый отчёт." />
        </div>
        <div v-else class="surface-card divide-y divide-line/60">
            <Link v-for="report in reports.data" :key="report.id" :href="route('reports.show', report.id)" class="flex flex-wrap items-center gap-3 px-5 py-4 transition hover:bg-ink/[0.025] sm:px-6">
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-semibold text-ink">{{ report.title || 'Отчёт' }}</span>
                    <span class="mt-1 block truncate text-[10px] text-muted">{{ report.task?.title || 'Без задачи' }} · {{ report.recipient || '—' }}</span>
                </span>
                <span class="font-mono text-[9px] text-faint">{{ formatDateTime(report.created_at) }}</span>
            </Link>
            <Pagination :links="reports" />
        </div>
    </ClientLayout>
</template>
