<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ProjectContextPanel from '@/Components/Projects/ProjectContextPanel.vue';
import ProjectAccessPanel from '@/Components/Projects/ProjectAccessPanel.vue';
import ProjectTaskFilter from '@/Components/Projects/ProjectTaskFilter.vue';
import BulkTaskEditor from '@/Components/Tasks/BulkTaskEditor.vue';
import { useProjectTaskFilter } from '@/Composables/useProjectTaskFilter';
import ProjectInvitesPanel from '@/Components/Projects/ProjectInvitesPanel.vue';
import ShareButton from '@/Components/Share/ShareButton.vue';
import { useAssistant } from '@/Composables/useAssistant';
import { formatDate } from '@/Utils/formatters.js';
import { taskStatusLabels } from '@/Utils/constants.js';
import {
    ArrowLeftIcon,
    ArrowTopRightOnSquareIcon,
    CheckCircleIcon,
    PencilSquareIcon,
    PlusIcon,
    SparklesIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    project: Object,
    tasks: Array,
    progress: Object,
    studies: Array,
    statuses: Array,
    priorities: Array,
    clients: Array,
    assignees: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    taskStatuses: { type: Array, default: () => [] },
    availableMembers: Array,
    inviteRoles: { type: Array, default: () => [] },
});

const { open } = useAssistant();
const filterStatuses = ['pending_review', 'queued', 'in_progress', 'awaiting_review', 'rejected_by_client', 'completed', 'rejected_by_admin'];
const { status, filteredTasks, setStatus } = useProjectTaskFilter(() => props.tasks, filterStatuses);
const selectedIds = ref([]);
const bulkOpen = ref(false);
const selectedTasks = computed(() => filteredTasks.value.filter((task) => selectedIds.value.includes(task.id)));
const allSelected = computed(() => filteredTasks.value.length > 0 && filteredTasks.value.every((task) => selectedIds.value.includes(task.id)));

watch(selectedIds, (ids) => { if (!ids.length) bulkOpen.value = false; });

function toggleTask(id) {
    selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter((taskId) => taskId !== id) : [...selectedIds.value, id];
}

function toggleAll(event) {
    selectedIds.value = event.target.checked ? filteredTasks.value.map((task) => task.id) : [];
}

function clearSelection() {
    selectedIds.value = [];
    bulkOpen.value = false;
}

const statusTone = {
    active: 'bg-primary-soft text-primary-800',
    on_hold: 'bg-warning/10 text-warning',
    completed: 'bg-success/10 text-success',
    draft: 'bg-ink/[0.06] text-muted',
    archived: 'bg-ink/[0.06] text-muted',
};

function askKiki() {
    open({ id: props.project.id, key: props.project.key, name: props.project.name });
}

function archive() {
    if (!window.confirm('Отправить проект в архив? Задачи останутся.')) return;
    router.post(route('admin.projects.archive', props.project.id));
}
</script>

<template>
    <AdminLayout title="Проект">
        <template #actions>
            <Link :href="route('admin.projects.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Проекты</Button></Link>
            <ShareButton
                kind="project"
                :href="route('client.projects.show', project.id)"
                :title="project.name"
                :recipient="project.client?.name || null"
            />
            <Button variant="secondary" size="sm" @click="askKiki"><SparklesIcon class="h-4 w-4" />Спросить Кики</Button>
            <Link :href="route('admin.projects.edit', project.id)"><Button variant="secondary" size="sm"><PencilSquareIcon class="h-4 w-4" />Изменить</Button></Link>
            <Link :href="route('admin.tasks.create', { project_id: project.id })"><Button size="sm"><PlusIcon class="h-4 w-4" />Задача</Button></Link>
        </template>

        <section class="relative overflow-hidden rounded-[26px] border border-line bg-surface p-5 shadow-[0_18px_45px_rgba(28,24,35,0.06)] sm:p-6">
            <div class="absolute inset-0 bg-[radial-gradient(120%_130%_at_100%_-10%,rgba(143,154,255,0.16),transparent_60%)]"></div>
            <div class="relative flex flex-col items-stretch gap-4 sm:flex-row sm:items-start">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-line bg-white/70 px-2.5 py-1 font-mono text-[10px] tracking-[0.08em] text-muted">{{ project.key }}</span>
                        <span :class="['rounded-full px-2.5 py-1 text-[10px] font-semibold', statusTone[project.status] || statusTone.draft]">{{ project.status_label }}</span>
                        <span v-if="project.client" class="text-[11px] text-muted">{{ project.client.name }}</span>
                    </div>
                    <h1 class="mt-2 break-words font-display text-[26px] font-medium leading-tight tracking-[-0.04em] text-ink sm:text-[32px]">{{ project.name }}</h1>
                    <p v-if="project.site_url" class="mt-1 text-[11px] text-muted">
                        <a :href="project.site_url" target="_blank" rel="noopener" class="inline-flex items-center gap-1 hover:text-primary-700">
                            {{ project.site_url }}
                            <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                        </a>
                    </p>
                    <p v-if="project.description" class="mt-3 max-w-[640px] text-[12px] leading-relaxed text-ink-soft">{{ project.description }}</p>
                </div>

                <div class="w-full rounded-[18px] border border-line bg-white/70 p-4 sm:max-w-[280px]">
                    <div class="flex items-end justify-between">
                        <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-faint">Прогресс</p>
                        <p class="font-display text-[28px] leading-none text-ink">{{ progress.percent }}<span class="text-[14px] text-muted">%</span></p>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink/[0.06]">
                        <div class="h-full rounded-full bg-primary-500" :style="{ width: progress.percent + '%' }"></div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2 text-[10px] text-muted">
                        <span><span class="font-semibold text-ink">{{ progress.groups.backlog }}</span> в очереди</span>
                        <span><span class="font-semibold text-ink">{{ progress.groups.started }}</span> в работе</span>
                        <span><span class="font-semibold text-ink">{{ progress.completed }}</span> готово</span>
                        <span v-if="project.due_at">до {{ formatDate(project.due_at) }}</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,.9fr)]">
            <div class="min-w-0 space-y-4">
                <section class="surface-card">
                    <header class="flex flex-wrap items-center gap-2 border-b border-line/80 px-5 py-3.5">
                        <CheckCircleIcon class="h-4 w-4 text-primary-600" />
                        <p class="text-[12px] font-bold text-ink">Задачи</p>
                        <span class="font-mono text-[9px] text-faint">{{ filteredTasks.length }}<template v-if="status"> / {{ progress.total }}</template></span>
                        <label class="inline-flex cursor-pointer items-center gap-1.5 text-[10px] font-semibold text-muted">
                            <input type="checkbox" :checked="allSelected" class="h-3.5 w-3.5 rounded border-line-strong text-primary-600 focus:ring-primary-500/30" @change="toggleAll" />
                            Все
                        </label>
                        <Button v-if="selectedIds.length" size="sm" @click="bulkOpen = true">Изменить · {{ selectedIds.length }}</Button>
                        <ProjectTaskFilter class="sm:ml-auto" :model-value="status" :statuses="filterStatuses" @update:model-value="setStatus" />
                        <Link :href="route('admin.projects.edit', project.id)" class="text-[10px] text-muted hover:text-ink">настройки</Link>
                    </header>

                    <div v-if="filteredTasks.length" class="divide-y divide-line/70">
                        <div v-for="task in filteredTasks" :key="task.id" :class="['flex items-stretch transition', selectedIds.includes(task.id) ? 'bg-primary-500/[0.045]' : 'hover:bg-primary-soft/30']">
                            <label class="flex shrink-0 cursor-pointer items-center pl-5 pr-1" :aria-label="`Выбрать задачу ${task.title}`">
                                <input type="checkbox" :checked="selectedIds.includes(task.id)" class="h-4 w-4 rounded border-line-strong text-primary-600 focus:ring-primary-500/30" @change="toggleTask(task.id)" />
                            </label>
                            <Link :href="route('admin.tasks.show', task.id)" class="flex min-w-0 flex-1 items-center gap-3 py-3 pr-5">
                                <span class="w-[54px] shrink-0 font-mono text-[9px] text-faint">{{ task.number ? project.key + '-' + task.number : '—' }}</span>
                                <span class="min-w-0 flex-1 truncate text-[12px] font-medium text-ink">{{ task.title }}</span>
                                <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                                <span v-if="task.deadline" class="hidden font-mono text-[9px] text-faint sm:block">{{ formatDate(task.deadline) }}</span>
                            </Link>
                        </div>
                    </div>

                    <div v-else class="flex flex-col items-center gap-3 px-5 py-10 text-center">
                        <p class="text-[11px] leading-relaxed text-muted">{{ status ? 'Нет задач с таким статусом.' : 'В проекте пока нет задач.' }}</p>
                        <div class="flex flex-wrap items-center justify-center gap-2">
                            <button v-if="status" type="button" class="text-[10px] font-semibold text-primary-700 hover:underline" @click="setStatus('')">Сбросить фильтр</button>
                            <Link :href="route('admin.tasks.create', { project_id: project.id })">
                                <Button size="sm" variant="secondary"><PlusIcon class="h-3.5 w-3.5" />Добавить первую</Button>
                            </Link>
                        </div>
                    </div>
                </section>

                <section v-if="project.instructions" class="surface-card p-5">
                    <p class="eyebrow">Указания владельца</p>
                    <p class="mt-2 whitespace-pre-wrap text-[11px] leading-relaxed text-ink-soft">{{ project.instructions }}</p>
                </section>

                <ProjectAccessPanel
                    v-if="project.can?.manage_members"
                    :project="project"
                    :available="availableMembers"
                />

                <ProjectInvitesPanel
                    v-if="project.can?.manage_members"
                    :project="project"
                    :invites="project.invites || []"
                    :roles="inviteRoles"
                />

                <div class="flex justify-end">
                    <button type="button" class="text-[10px] text-faint hover:text-danger-ink" @click="archive">В архив</button>
                </div>
            </div>

            <ProjectContextPanel class="min-w-0" :project="project" :studies="studies" />
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
            :statuses="taskStatuses"
            @done="clearSelection"
            @clear="clearSelection"
        />
    </AdminLayout>
</template>
