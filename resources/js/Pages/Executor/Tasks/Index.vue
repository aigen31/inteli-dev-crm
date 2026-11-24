<script setup>
import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import Segmented from '@/Components/UI/Segmented.vue';
import WorkloadCalendar from '@/Components/Calendar/WorkloadCalendar.vue';
import { taskPriorityLabels, taskStatusLabels } from '@/Utils/constants.js';
import { formatDate } from '@/Utils/formatters.js';
import { ArrowUpRightIcon, CalendarDaysIcon, CheckCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tasks: Object,
    filters: Object,
    statuses: Array,
    counts: Object,
    calendar: { type: Array, default: () => [] },
});

const local = reactive({ status: props.filters?.status || '' });
const chips = [
    { value: '', label: 'Все', count: props.counts?.all },
    { value: 'in_progress', label: 'В работе', count: props.counts?.in_progress },
    { value: 'awaiting_review', label: 'На проверке', count: props.counts?.awaiting_review },
];

const date = ref(props.filters?.date || null);
const calendarOpen = ref(Boolean(date.value));
const selectedLabel = computed(() => {
    if (!date.value) return null;

    const [year, month, day] = date.value.split('-').map(Number);

    return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
});

function setStatus(value) {
    local.status = value;
    router.get(route('executor.tasks.index'), {
        status: value || undefined,
        date: date.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function setDate(value) {
    date.value = value;
    router.get(route('executor.tasks.index'), {
        status: local.status || undefined,
        date: value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <ClientLayout title="Мои задачи">
        <template #actions>
            <Link :href="route('executor.dashboard')"><Button variant="ghost" size="sm">Обзор</Button></Link>
        </template>

        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <Segmented :model-value="local.status" :options="chips" @update:model-value="setStatus" />
            <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-muted">{{ tasks.total }} задач</p>
        </div>

        <div class="surface-card mb-4 p-3">
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/55 px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700"
                    :aria-expanded="calendarOpen"
                    @click="calendarOpen = !calendarOpen"
                >
                    <CalendarDaysIcon class="h-3.5 w-3.5" />
                    Календарь нагрузки
                </button>
                <button v-if="date" type="button" class="inline-flex items-center gap-1.5 rounded-full bg-primary-soft px-3 py-1.5 text-[10px] font-semibold text-primary-800" @click="setDate(null)">
                    {{ selectedLabel }}
                    <XMarkIcon class="h-3 w-3" />
                </button>
                <span v-else class="text-[9px] text-faint">Выберите день, чтобы отфильтровать задачи</span>
            </div>
            <div v-if="calendarOpen" class="mt-2">
                <WorkloadCalendar :ranges="calendar" :model-value="date" @update:model-value="setDate" />
            </div>
        </div>

        <div class="surface-card overflow-hidden">
            <div v-if="!tasks.data.length" class="p-2">
                <EmptyState image="/images/kiki/kiki-forms-avatar.webp" title="Задач пока нет" text="Назначенные вам задачи появятся здесь." />
            </div>
            <div v-else class="divide-y divide-line/60">
                <Link v-for="task in tasks.data" :key="task.id" :href="route('executor.tasks.show', task.id)" class="group flex items-center gap-4 px-5 py-4 transition hover:bg-ink/[0.025] sm:px-6">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[12px] bg-primary-soft text-primary-700"><CheckCircleIcon class="h-4 w-4" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-semibold text-ink group-hover:text-primary-800">{{ task.title }}</span>
                        <span class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-muted">
                            <span v-if="task.project" class="rounded-full border border-line bg-white/60 px-2 py-0.5 font-mono text-[8px]">{{ task.project.key }}{{ task.number ? '-' + task.number : '' }}</span>
                            {{ task.client?.name || 'Без клиента' }}
                            <span>{{ task.deadline ? formatDate(task.deadline) : 'без срока' }}</span>
                        </span>
                    </span>
                    <span class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                        <span v-if="task.priority === 'high'" class="rounded-full bg-danger/10 px-2 py-0.5 text-[9px] font-semibold text-danger-ink">{{ taskPriorityLabels[task.priority] }}</span>
                        <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                        <ArrowUpRightIcon class="h-4 w-4 text-primary-600" />
                    </span>
                </Link>
            </div>
            <Pagination :links="tasks" />
        </div>
    </ClientLayout>
</template>
