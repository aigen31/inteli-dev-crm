<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import Segmented from '@/Components/UI/Segmented.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { taskPriorityLabels, taskStatusLabels, taskTypeLabels } from '@/Utils/constants.js';
import { formatDate } from '@/Utils/formatters.js';
import { MagnifyingGlassIcon, PlusIcon, Squares2X2Icon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tasks: Object,
    filters: Object,
    statuses: Array,
    types: Array,
    clients: Array,
    projects: Array,
    counts: Object,
});

const local = reactive({
    q: props.filters?.q || '',
    status: props.filters?.status || '',
    type: props.filters?.type || '',
    client_id: props.filters?.client_id || '',
    project_id: props.filters?.project_id || '',
});

const quickStatuses = [
    { value: '', label: 'Все', count: props.counts?.all },
    { value: 'pending_review', label: 'Новые' },
    { value: 'in_progress', label: 'В работе' },
    { value: 'awaiting_review', label: 'Ждут оценки', count: props.counts?.awaiting_review },
];

function apply(extra = {}) {
    router.get(
        route('admin.tasks.index'),
        {
            ...local,
            ...extra,
            status: local.status || undefined,
            type: local.type || undefined,
            client_id: local.client_id || undefined,
            project_id: local.project_id || undefined,
            q: local.q || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function setStatus(value) {
    local.status = value;
    apply({ status: value || undefined });
}
</script>

<template>
    <AdminLayout title="Задачи">
        <template #actions>
            <Link :href="route('admin.forms.index')">
                <Button variant="secondary">
                    <Squares2X2Icon class="h-4 w-4" />
                    Формы
                </Button>
            </Link>
            <Link :href="route('admin.tasks.create')">
                <Button>
                    <PlusIcon class="h-4 w-4" />
                    Новая задача
                </Button>
            </Link>
        </template>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <Segmented :model-value="local.status" :options="quickStatuses" @update:model-value="setStatus" />
            <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-muted">{{ tasks.total }} задач</p>
        </div>

        <div class="surface-card mb-4 p-3">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_150px_150px_180px]">
                <form class="relative" @submit.prevent="apply()">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                    <input
                        v-model="local.q"
                        type="search"
                        placeholder="Найти задачу…"
                        class="h-10 w-full rounded-[13px] border border-line bg-white/70 pl-10 pr-3 text-[12px] text-ink placeholder-faint focus:border-primary-400 focus:outline-none focus:ring-2 focus:ring-primary-500/10"
                    />
                </form>
                <SelectInput v-model="local.type" :options="types" placeholder="Все типы" @update:model-value="apply()" />
                <SelectInput v-model="local.client_id" :options="clients.map((c) => ({ value: c.id, label: c.name }))" placeholder="Все клиенты" @update:model-value="apply()" />
                <SelectInput v-model="local.project_id" :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))" placeholder="Все проекты" @update:model-value="apply()" />
            </div>
        </div>

        <div class="surface-card overflow-hidden">
            <div v-if="!tasks.data.length" class="p-2">
                <EmptyState image="/images/kiki/kiki-forms-avatar.webp" title="Задач пока нет" text="Создайте задачу вручную или начните с шаблона — так описание будет понятным с первого раза.">
                    <div class="flex gap-2">
                        <Link :href="route('admin.tasks.create')"><Button size="sm">Создать задачу</Button></Link>
                        <Link :href="route('admin.forms.index')"><Button size="sm" variant="secondary">Открыть формы</Button></Link>
                    </div>
                </EmptyState>
            </div>

            <div v-else class="divide-y divide-line/70">
                <Link
                    v-for="task in tasks.data"
                    :key="task.id"
                    :href="route('admin.tasks.show', task.id)"
                    class="group grid grid-cols-1 gap-3 px-5 py-4 transition hover:bg-ink/[0.025] sm:grid-cols-[1.6fr_.8fr_.7fr_auto] sm:items-center sm:px-6"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-[13px] font-semibold text-ink group-hover:text-primary-800">{{ task.title }}</p>
                            <span v-if="task.template" class="rounded-full bg-primary-soft px-2 py-0.5 font-mono text-[8px] uppercase tracking-[0.1em] text-primary-700">шаблон</span>
                        </div>
                        <p class="mt-1 flex flex-wrap items-center gap-1.5 truncate text-[11px] text-muted">
                            <span v-if="task.project" class="rounded-full border border-line bg-white/70 px-2 py-0.5 font-mono text-[9px] text-ink-soft">
                                {{ task.project.key }}{{ task.number ? '-' + task.number : '' }}
                            </span>
                            {{ task.client?.name || 'Без клиента' }}
                            <span v-if="task.assignee"> · {{ task.assignee.name }}</span>
                            <span v-if="task.checklist?.length"> · {{ task.checklist.length }} шагов</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-muted">
                        <span class="sm:hidden">Статус:</span>
                        <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-muted">
                        <span class="font-mono text-[10px]">{{ formatDate(task.deadline) }}</span>
                        <span v-if="task.priority === 'high'" class="rounded-full bg-danger/10 px-2 py-0.5 text-[9px] font-semibold text-danger-ink">{{ taskPriorityLabels[task.priority] }}</span>
                    </div>
                    <div class="hidden font-mono text-[9px] uppercase tracking-[0.08em] text-faint sm:block">{{ taskTypeLabels[task.type] }}</div>
                </Link>
            </div>

            <Pagination :links="tasks" />
        </div>
    </AdminLayout>
</template>
