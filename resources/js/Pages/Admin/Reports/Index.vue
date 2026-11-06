<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import ReportCard from '@/Components/Reports/ReportCard.vue';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    reports: Object,
    filters: Object,
    recipients: Array,
    counts: Object,
});

const local = reactive({
    recipient_id: props.filters?.recipient_id || '',
    unread: !!props.filters?.unread,
});

function apply() {
    router.get(route('admin.reports.index'), {
        recipient_id: local.recipient_id || undefined,
        unread: local.unread ? 1 : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <AdminLayout title="Отчёты">
        <template #actions>
            <Link :href="route('admin.reports.create')"><Button size="sm"><PlusIcon class="h-4 w-4" />Новый отчёт</Button></Link>
        </template>

        <div class="flex flex-wrap items-end justify-between gap-3">
            <p class="max-w-[620px] text-[12px] leading-relaxed text-muted">
                Краткое объяснение работы, ссылка для тестирования и скриншот — в одном сообщении получателю.
            </p>
            <div class="flex items-center gap-2 text-[10px] text-muted">
                <span class="rounded-full border border-line bg-surface px-3 py-1.5">всего {{ counts?.all ?? 0 }}</span>
                <span class="rounded-full border border-line bg-surface px-3 py-1.5">непрочитанных {{ counts?.unread ?? 0 }}</span>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <div class="w-[240px]">
                <SelectInput v-model="local.recipient_id" :options="recipients.map((u) => ({ value: u.id, label: u.name + ' · ' + u.email }))" placeholder="Все получатели" @update:model-value="apply()" />
            </div>
            <label class="flex items-center gap-2 text-[11px] text-muted">
                <input v-model="local.unread" type="checkbox" class="h-3.5 w-3.5 rounded border-line-strong" @change="apply()" />
                только непрочитанные
            </label>
        </div>

        <div v-if="reports.data.length" class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <ReportCard v-for="report in reports.data" :key="report.id" :report="report" :show-url="route('reports.show', report.id)" />
        </div>
        <EmptyState v-else class="mt-6" image="/images/kiki/kiki-empty-generic.png" title="Отчётов пока нет" text="Создайте первый отчёт — его увидит выбранный получатель.">
            <Link :href="route('admin.reports.create')"><Button size="sm">Создать отчёт</Button></Link>
        </EmptyState>

        <Pagination :links="reports" />
    </AdminLayout>
</template>
