<script setup>
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate } from '@/Utils/formatters.js';
import { PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tasks: Object,
    filters: Object,
    statuses: Array,
    counts: Object,
});

const statusFilters = [
    { value: '', label: 'Все', count: props.counts?.all },
    { value: 'in_progress', label: 'В работе' },
    { value: 'awaiting_review', label: 'Ждут оценки' },
    { value: 'completed', label: 'Завершены' },
];

function setStatus(value) {
    router.get(route('client.tasks.index'), { status: value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <ClientLayout title="Мои задачи">
        <template #actions>
            <Link :href="route('client.tasks.create')">
                <Button><PlusIcon class="h-4 w-4" />Новая заявка</Button>
            </Link>
        </template>

        <div class="mb-4 inline-flex flex-wrap gap-1 rounded-full border border-line bg-white/50 p-1">
            <button
                v-for="item in statusFilters"
                :key="item.value"
                type="button"
                :class="[
                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold transition',
                    (props.filters?.status || '') === item.value ? 'bg-ink text-canvas' : 'text-muted hover:text-ink',
                ]"
                @click="setStatus(item.value)"
            >
                {{ item.label }}
                <span v-if="item.count != null" class="font-mono text-[9px] opacity-70">{{ item.count }}</span>
            </button>
        </div>

        <div v-if="!tasks.data.length" class="surface-card">
            <EmptyState title="Заявок пока нет" text="Выберите шаблон или опишите задачу своими словами — владелец увидит её и ответит.">
                <Link :href="route('client.tasks.create')"><Button size="sm">Создать заявку</Button></Link>
            </EmptyState>
        </div>

        <div v-else class="space-y-2.5">
            <Link
                v-for="task in tasks.data"
                :key="task.id"
                :href="route('client.tasks.show', task.id)"
                class="surface-card surface-card-hover flex flex-wrap items-center gap-3 p-4 sm:p-5"
            >
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-bold text-ink">{{ task.title }}</span>
                    <span class="mt-1 block text-[10px] text-muted">
                        {{ task.template?.name ? `по шаблону «${task.template.name}» · ` : '' }}
                        {{ task.deadline ? `до ${formatDate(task.deadline)}` : 'срок не задан' }}
                    </span>
                </span>
                <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
            </Link>
        </div>

        <Pagination :links="tasks" />
    </ClientLayout>
</template>
