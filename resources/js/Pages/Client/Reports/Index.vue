<script setup>
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import ReportCard from '@/Components/Reports/ReportCard.vue';

defineProps({
    reports: Object,
    unread: Number,
});
</script>

<template>
    <ClientLayout title="Отчёты">
        <p class="max-w-[620px] text-[12px] leading-relaxed text-muted">
            Отчёты о выполненных задачах: что сделано, где проверить и как это выглядит.
            <span v-if="unread" class="ml-1 rounded-full bg-primary-soft px-2 py-0.5 text-[10px] font-semibold text-primary-800">{{ unread }} новых</span>
        </p>

        <div v-if="reports.data.length" class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <ReportCard v-for="report in reports.data" :key="report.id" :report="report" :show-url="route('reports.show', report.id)" />
        </div>
        <EmptyState v-else class="mt-6" image="/images/kiki/kiki-reports-client.png" title="Отчётов пока нет" text="Когда исполнитель отправит отчёт, он появится здесь." />

        <Pagination :links="reports" />
    </ClientLayout>
</template>
