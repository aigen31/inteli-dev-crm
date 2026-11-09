<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TaskFormFields from '@/Components/Task/TaskFormFields.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import {
    ArrowLeftIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClipboardDocumentCheckIcon,
    FolderIcon,
    FlagIcon,
    ListBulletIcon,
    PlusIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    clients: Array,
    projects: Array,
    assignees: Array,
    templates: Array,
    types: Array,
    priorities: Array,
    statuses: Array,
    fields: Array,
});

const params = new URLSearchParams(window.location.search);

const form = useForm({
    client_id: params.get('client_id') || '',
    project_id: params.get('project_id') || '',
    task_template_id: '',
    title: '',
    description: '',
    checklist: [],
    type: 'one_time',
    status: 'pending_review',
    priority: 'medium',
    estimated_minutes: '',
    assignee_id: '',
    deadline: '',
    custom_fields: {},
    consent: false,
});

const selectedClient = computed(() => (props.clients || []).find((client) => client.id === form.client_id));
const selectedProject = computed(() => (props.projects || []).find((project) => project.id === form.project_id));
const selectedAssignee = computed(() => (props.assignees || []).find((assignee) => assignee.id === form.assignee_id));
const typeLabel = computed(() => (props.types || []).find((type) => type.value === form.type)?.label || form.type);
const priorityLabel = computed(() => (props.priorities || []).find((priority) => priority.value === form.priority)?.label || form.priority);
const completion = computed(() => {
    const checks = [form.client_id, form.title, form.description];
    return Math.round((checks.filter(Boolean).length / checks.length) * 100);
});

function update(next) {
    Object.keys(next).forEach((key) => {
        form[key] = next[key];
    });
}

function submit() {
    form.post(route('admin.tasks.store'));
}
</script>

<template>
    <AdminLayout title="Новая задача">
        <template #actions>
            <Link :href="route('admin.tasks.index')">
                <Button variant="ghost" size="sm">
                    <ArrowLeftIcon class="h-4 w-4" />
                    К задачам
                </Button>
            </Link>
        </template>

        <section class="relative mb-5 overflow-hidden rounded-[28px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-5 sm:px-7 sm:py-6">
            <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700">
                    <ClipboardDocumentCheckIcon class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Конструктор задачи</p>
                    <h1 class="mt-1 font-display text-[22px] font-medium tracking-[-0.035em] text-ink">Соберите задачу как маршрут</h1>
                    <p class="mt-1 max-w-[620px] text-[11px] leading-relaxed text-muted">Слева — содержание и детали, справа — живая карточка. Можно править в любом порядке: перед созданием всё сходится в одном месте.</p>
                </div>
                <div class="flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-3 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                    <span class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted"><span>Заполнено</span> {{ completion }}%</span>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_350px]">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">01</span> Содержание</span>
                    <span class="text-faint">→</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">02</span> Детали</span>
                    <span class="text-faint">→</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">03</span> Шаги</span>
                </div>

                <TaskFormFields
                    :model-value="form"
                    :clients="clients"
                    :projects="projects"
                    :assignees="assignees"
                    :templates="templates"
                    :types="types"
                    :priorities="priorities"
                    :statuses="statuses"
                    :fields="fields"
                    :errors="form.errors"
                    @update:model-value="update"
                />
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card overflow-hidden">
                    <div class="relative overflow-hidden border-b border-line/70 bg-ink px-5 py-4 text-canvas">
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-primary-400/30 blur-2xl"></div>
                        <div class="relative flex items-center gap-3">
                            <KikiAvatar variant="face" fit="contain" :size="34" />
                            <div class="min-w-0">
                                <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-canvas/60">Черновик</p>
                                <p class="mt-0.5 truncate text-[12px] font-semibold text-canvas">Проверьте перед созданием</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="space-y-3">
                            <div class="flex items-start gap-2.5">
                                <UserIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Клиент</p>
                                    <p class="mt-0.5 truncate text-[11px] font-semibold text-ink">{{ selectedClient?.name || 'Не выбран' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <FolderIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Проект</p>
                                    <p class="mt-0.5 truncate text-[11px] font-semibold text-ink">{{ selectedProject?.key ? selectedProject.key + ' · ' + selectedProject.name : 'Без проекта' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <FlagIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Формат</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-ink">{{ typeLabel }} · {{ priorityLabel }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <CalendarDaysIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Срок и исполнитель</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-ink">{{ form.deadline || 'без срока' }} · {{ selectedAssignee?.name || 'не назначен' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-line/70 pt-3">
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-[9px] font-semibold text-primary-800"><ListBulletIcon class="h-3 w-3" />{{ form.checklist?.length || 0 }} шагов</span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2.5 py-1 text-[9px] font-semibold text-muted"><PlusIcon class="h-3 w-3" />{{ fields?.length || 0 }} полей</span>
                        </div>

                        <div class="mt-4 border-t border-line/70 pt-4">
                            <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="admin-task-consent" variant="contract" />
                        </div>

                        <Button class="mt-4 w-full" :disabled="form.processing || !form.consent" @click="submit">
                            <CheckCircleIcon class="h-4 w-4" />
                            {{ form.processing ? 'Создаю…' : 'Создать задачу' }}
                        </Button>
                        <p class="mt-2 text-center text-[9px] leading-relaxed text-faint">После создания задачу можно дополнить в карточке.</p>
                    </div>
                </section>

                <section class="rounded-[20px] border border-line/70 bg-white/35 p-4">
                    <p class="eyebrow">Подсказка</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Начните с клиента и названия — остальное можно уточнить по ходу. Шаблон подставит описание и чек-лист автоматически.</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
