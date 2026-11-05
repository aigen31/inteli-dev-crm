<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import ReportDocument from '@/Components/Reports/ReportDocument.vue';
import { ArrowLeftIcon, PlusIcon } from '@heroicons/vue/24/outline';

defineProps({
    report: Object,
});
</script>

<template>
    <AdminLayout title="Отчёт">
        <template #actions>
            <Link :href="route('admin.reports.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Все отчёты</Button></Link>
            <Link :href="route('admin.reports.create')"><Button size="sm"><PlusIcon class="h-4 w-4" />Новый отчёт</Button></Link>
        </template>

        <ReportDocument :report="report" />
    </AdminLayout>
</template>
