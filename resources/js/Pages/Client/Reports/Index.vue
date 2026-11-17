<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import ReportCard from '@/Components/Reports/ReportCard.vue';
import { PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

defineProps({
    reports: Object,
    unread: Number,
});

function destroy(report) {
    if (! window.confirm(`Удалить отчёт «${report.display_title}»?`)) return;

    router.delete(route('reports.destroy', report.id), { preserveScroll: true });
}
</script>

<template>
    <ClientLayout title="Отчёты">
        <template #actions>
            <Link :href="route('reports.create')">
                <Button size="sm"><PlusIcon class="h-4 w-4" />Новый отчёт</Button>
            </Link>
        </template>
        <p class="max-w-[620px] text-[12px] leading-relaxed text-muted">
            Отчёты о выполненных задачах: что сделано, где проверить и как это выглядит.
            <span v-if="unread" class="ml-1 rounded-full bg-primary-soft px-2 py-0.5 text-[10px] font-semibold text-primary-800">{{ unread }} новых</span>
        </p>

        <div v-if="reports.data.length" class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div v-for="report in reports.data" :key="report.id" class="flex flex-col gap-2">
                <ReportCard :report="report" :show-url="route('reports.show', report.id)" />
                <div v-if="report.can_edit" class="flex items-center gap-2">
                    <Link :href="route('reports.edit', report.id)">
                        <Button variant="secondary" size="sm"><PencilSquareIcon class="h-3.5 w-3.5" />Изменить</Button>
                    </Link>
                    <Button variant="ghost" size="sm" @click="destroy(report)"><TrashIcon class="h-3.5 w-3.5" />Удалить</Button>
                </div>
            </div>
        </div>
        <EmptyState v-else class="mt-6" image="/images/kiki/kiki-empty-generic.png" title="Отчётов пока нет" text="Когда исполнитель отправит отчёт, он появится здесь." />

        <Pagination :links="reports" />
    </ClientLayout>
</template>
