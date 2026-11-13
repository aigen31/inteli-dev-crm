<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import ReportDocument from '@/Components/Reports/ReportDocument.vue';
import ReportExportButtons from '@/Components/Reports/ReportExportButtons.vue';
import { ArrowLeftIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    report: Object,
});

function destroy() {
    if (!window.confirm('Удалить отчёт? Это действие нельзя отменить.')) return;
    router.delete(route('admin.reports.destroy', props.report.id), { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Отчёт">
        <template #actions>
            <Link :href="route('admin.reports.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Все отчёты</Button></Link>
            <ReportExportButtons :report-id="report.id" />
            <Link :href="route('admin.reports.edit', report.id)"><Button variant="secondary" size="sm"><PencilSquareIcon class="h-4 w-4" />Редактировать</Button></Link>
            <Button variant="ghost" size="sm" @click="destroy"><TrashIcon class="h-4 w-4" />Удалить</Button>
        </template>

        <ReportDocument :report="report" />
    </AdminLayout>
</template>
