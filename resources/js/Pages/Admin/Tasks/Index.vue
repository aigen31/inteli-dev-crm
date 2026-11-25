<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import Segmented from '@/Components/UI/Segmented.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import BulkTaskEditor from '@/Components/Tasks/BulkTaskEditor.vue';
import TaskDateFilter from '@/Components/Tasks/TaskDateFilter.vue';
import WorkloadCalendar from '@/Components/Calendar/WorkloadCalendar.vue';
import { useFilterMemory } from '@/Composables/useFilterMemory';
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
    assignees: Array,
    priorities: Array,
    counts: Object,
    calendar: { type: Array, default: () => [] },
});

const memory = useFilterMemory('admin.tasks');
const saved = memory.load();

const local = reactive({
    q: props.filters?.q || saved.q || '',
    status: props.filters?.status || saved.status || '',
    type: props.filters?.type || saved.type || '',
    project_id: props.filters?.project_id || saved.project_id || '',
});

const selectedIds = ref([]);
const bulkOpen = ref(false);
const date = ref(props.filters?.date || saved.date || null);
const calendarOpen = ref(false);

watch(
    () => props.filters?.date,
    (value) => {
        date.value = value || null;
    },
);

watch(
    () => props.filters?.status,
    (value) => {
        local.status = value || '';
    },
);

const selectedTasks = computed(() => (props.tasks?.data || []).filter((task) => selectedIds.value.includes(task.id)));
const allSelected = computed(() => (props.tasks?.data || []).length > 0 && props.tasks.data.every((task) => selectedIds.value.includes(task.id)));

watch(selectedIds, (ids) => {
    if (!ids.length) bulkOpen.value = false;
});

watch(
    () => props.tasks?.data,
    () => {
        selectedIds.value = [];
    },
);

function toggleTask(id) {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((taskId) => taskId !== id)
        : [...selectedIds.value, id];
}

function toggleAll(event) {
    selectedIds.value = event.target.checked ? (props.tasks?.data || []).map((task) => task.id) : [];
}

function clearSelection() {
    selectedIds.value = [];
    bulkOpen.value = false;
}

const quickStatuses = [
    { value: '', label: 'Все', count: props.counts?.all },
    { value: 'pending_review', label: 'Новые' },
    { value: 'in_progress', label: 'В работе' },
    { value: 'awaiting_review', label: 'Ждут оценки', count: props.counts?.awaiting_review },
];

function apply(extra = {}) {
    const params = {
        ...local,
        ...extra,
        status: local.status || undefined,
        type: local.type || undefined,
        project_id: local.project_id || undefined,
        q: local.q || undefined,
        date: date.value || undefined,
    };

    memory.save(params);

    router.get(route('admin.tasks.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

onMounted(() => {
    const hasUrlDate = Boolean(props.filters?.date);
    if (!hasUrlDate && saved.date) {
        apply({ date: saved.date });
    }
});

function setStatus(value) {
    local.status = value;
    apply({ status: value || undefined });
}

function setDate(value) {
    date.value = value;
    calendarOpen.value = false;
    apply({ date: value || undefined });
}
</script>

<template>
    <AdminLayout title="Задачи">
        <template #actions>
            <Link :href="route('admin.tasks.forms.create')">
                <Button variant="secondary">
                    <Squares2X2Icon class="h-4 w-4" />
                    Из шаблона
                </Button>
            </Link>
            <Link :href="route('admin.forms.index')">
                <Button variant="secondary">
                    <Squares2X2Icon class="h-4 w-4" />
                    Шаблоны
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

        <div
            class="surface-card mb-4 p-3 transition"
            :class="calendarOpen ? 'ring-1 ring-primary-200/60' : ''"
            @keydown.esc="calendarOpen = false"
        >
            <div class="grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_150px_170px_210px]">
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
                <SelectInput v-model="local.project_id" :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))" placeholder="Все проекты" @update:model-value="apply()" />
                <div class="relative min-w-0">
                    <TaskDateFilter
                        :ranges="calendar"
                        :model-value="date"
                        :open="calendarOpen"
                        @toggle="calendarOpen = !calendarOpen"
                        @update:model-value="setDate"
                    />

                    <div v-if="calendarOpen" class="fixed inset-0 z-30" aria-hidden="true" @click="calendarOpen = false"></div>
                    <div
                        v-if="calendarOpen"
                        id="task-date-calendar"
                        role="dialog"
                        aria-label="Нагрузка по датам"
                        class="absolute right-0 top-[calc(100%+8px)] z-40 w-[min(92vw,380px)] rounded-[18px] border border-line bg-surface p-1.5 shadow-[0_18px_50px_rgba(40,32,65,0.16)] max-sm:fixed max-sm:inset-x-3 max-sm:bottom-20 max-sm:top-auto max-sm:max-h-[72vh] max-sm:w-auto max-sm:overflow-y-auto"
                    >
                        <WorkloadCalendar :ranges="calendar" :model-value="date" @update:model-value="setDate" />
                    </div>
                </div>
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

            <div v-else>
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line/70 bg-surface-2/30 px-4 py-2.5 sm:px-6">
                    <label class="inline-flex cursor-pointer items-center gap-2 text-[10px] font-semibold text-muted">
                        <input type="checkbox" :checked="allSelected" class="h-3.5 w-3.5 rounded border-line-strong text-primary-600 focus:ring-primary-500/30" @change="toggleAll" />
                        Выбрать страницу
                    </label>
                    <div v-if="selectedIds.length" class="flex items-center gap-2">
                        <span class="font-mono text-[9px] uppercase tracking-[0.1em] text-primary-700">{{ $t('выбрано') }} {{ selectedIds.length }}</span>
                        <Button size="sm" @click="bulkOpen = true">Изменить</Button>
                    </div>
                </div>
                <div class="divide-y divide-line/70">
                <div
                    v-for="task in tasks.data"
                    :key="task.id"
                    :class="['group flex items-stretch transition', selectedIds.includes(task.id) ? 'bg-primary-500/[0.045]' : 'hover:bg-ink/[0.025]']"
                >
                    <label class="flex shrink-0 cursor-pointer items-center pl-4 pr-1 sm:pl-6" :aria-label="`Выбрать задачу ${task.title}`">
                        <input type="checkbox" :checked="selectedIds.includes(task.id)" class="h-4 w-4 rounded border-line-strong text-primary-600 focus:ring-primary-500/30" @change="toggleTask(task.id)" />
                    </label>
                    <Link
                        :href="route('admin.tasks.show', task.id)"
                        class="grid min-w-0 flex-1 grid-cols-1 gap-3 py-4 pr-5 sm:grid-cols-[1.6fr_.8fr_.7fr_auto] sm:items-center sm:pr-6"
                    >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-[13px] font-semibold text-ink group-hover:text-primary-800">{{ task.title }}</p>
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
                </div>
            </div>

            <Pagination :links="tasks" />
        </div>

        <BulkTaskEditor
            v-if="selectedTasks.length"
            :open="bulkOpen"
            :endpoint="route('admin.tasks.bulk-update')"
            :selected-tasks="selectedTasks"
            :projects="projects"
            :clients="clients"
            :assignees="assignees"
            :types="types"
            :priorities="priorities"
            :statuses="statuses"
            @done="clearSelection"
            @clear="clearSelection"
        />
    </AdminLayout>
</template>
