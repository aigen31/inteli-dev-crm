<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import ReportDocument from '@/Components/Reports/ReportDocument.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

defineProps({
    report: Object,
});
</script>

<template>
    <ClientLayout title="Отчёт">
        <template #actions>
            <Link :href="route('client.reports.index')" class="inline-flex items-center gap-1.5 rounded-[14px] border border-line-strong bg-white/60 px-3 py-2 text-[11px] font-semibold text-ink">
                <ArrowLeftIcon class="h-3.5 w-3.5" />Все отчёты
            </Link>
        </template>

        <ReportDocument :report="report" />
    </ClientLayout>
</template>
