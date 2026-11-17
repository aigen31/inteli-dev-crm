<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TaskFormFields from '@/Components/Task/TaskFormFields.vue';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import FormTemplateCard from '@/Components/FormTemplates/FormTemplateCard.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import TaskDraftNotice from '@/Components/Task/TaskDraftNotice.vue';
import { useTaskDraft } from '@/Composables/useTaskDraft';
import {
    ArrowLeftIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClipboardDocumentCheckIcon,
    ClipboardDocumentListIcon,
    ChevronDownIcon,
    BugAntIcon,
    SparklesIcon,
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
    assigneeId: { type: String, default: null },
    types: Array,
    priorities: Array,
    statuses: Array,
    fields: Array,
    forms: { type: Array, default: () => [] },
});

const params = new URLSearchParams(window.location.search);
const page = usePage();

const form = useForm({
    client_id: params.get('client_id') || '',
    project_id: params.get('project_id') || '',
    title: '',
    description: '',
    checklist: [],
    type: 'one_time',
    status: 'pending_review',
    priority: 'medium',
    strict_control: null,
    estimated_minutes: '',
    budget: '',
    assignee_id: '',
    starts_at: '',
    deadline: '',
    custom_fields: {},
    form_template_id: null,
    form_data: { fields: {}, groups: {} },
    consent: false,
});

const selectedTemplate = computed(() => (props.forms || []).find((template) => template.id === form.form_template_id) || null);
const templatePickerOpen = ref(false);
const mainKeys = ['task-description-images', 'bug-report'];

const mainForms = computed(() => mainKeys.map((key) => props.forms.find((candidate) => candidate.key === key)).filter(Boolean));
const secondaryForms = computed(() => props.forms.filter((candidate) => !mainKeys.includes(candidate.key)));

const mainTitles = {
    'task-description-images': 'Описание задачи + изображения',
    'bug-report': 'Баг-репорт',
};

const mainDescriptions = {
    'task-description-images': 'Задача своими словами и скриншоты — для нового запроса или правки.',
    'bug-report': 'Что сломалось, как воспроизвести и что ожидалось.',
};

const mainIcons = {
    'task-description-images': ClipboardDocumentListIcon,
    'bug-report': BugAntIcon,
};

function emptyFormData(template) {
    const schema = template?.schema || {};
    const fields = {};
    (schema.fields || []).forEach((field) => {
        fields[field.key] = field.type === 'checkbox' ? false : '';
    });
    const groups = {};
    (schema.groups || []).forEach((group) => {
        const count = Math.max(Number(group.min || 0), 0);
        groups[group.key] = Array.from({ length: count }, () => {
            const row = {};
            (group.fields || []).forEach((field) => {
                row[field.key] = field.type === 'checkbox' ? false : '';
            });
            return row;
        });
    });
    return { fields, groups };
}

function chooseTemplate(template) {
    form.form_template_id = template.id;
    form.form_data = emptyFormData(template);
    templatePickerOpen.value = false;
}

function clearTemplate() {
    form.form_template_id = null;
    form.form_data = { fields: {}, groups: {} };
}

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

const draft = useTaskDraft({
    key: `admin:create:${page.props.auth?.user?.id || 'anonymous'}`,
    snapshot: () => ({
        client_id: form.client_id,
        project_id: form.project_id,
        title: form.title,
        description: form.description,
        checklist: form.checklist,
        type: form.type,
        status: form.status,
        priority: form.priority,
        estimated_minutes: form.estimated_minutes,
        budget: form.budget,
        assignee_id: form.assignee_id,
        starts_at: form.starts_at,
        deadline: form.deadline,
        custom_fields: form.custom_fields,
    }),
    restore: (data) => {
        Object.entries(data).forEach(([key, value]) => {
            if (Object.prototype.hasOwnProperty.call(form, key)) form[key] = value;
        });
        form.consent = false;
    },
    isEmpty: (data) => !data.title && !data.description && !data.client_id && !data.project_id && !data.checklist?.length && !Object.keys(data.custom_fields || {}).length,
});

function submit() {
    form.post(route('admin.tasks.store'), { onSuccess: () => draft.clear() });
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
            <Link :href="route('admin.tasks.forms.create')">
                <Button variant="secondary" size="sm">Из шаблона</Button>
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

        <TaskDraftNotice v-if="draft.savedAt || draft.restored" :saved-at="draft.savedAt" :restored="draft.restored" @discard="draft.discard" />

        <section class="surface-card mb-5 overflow-visible p-5 sm:p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <p class="eyebrow">01 / С чего начнём</p>
                    <h2 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em] text-ink">Что нужно сделать?</h2>
                    <p class="mt-1 max-w-[620px] text-[12px] leading-relaxed text-muted">
                        Можно взять шаблон — он подставит поля и подсказки, — или создать обычную задачу.
                    </p>
                </div>
                <span class="rounded-full border border-line bg-surface-2/60 px-3 py-1 font-mono text-[9px] uppercase tracking-[0.12em] text-muted">шаг 1 из 3</span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <button
                    v-for="template in mainForms"
                    :key="template.id"
                    type="button"
                    :class="[
                        'group flex min-h-[132px] flex-col items-start rounded-[18px] border p-4 text-left transition',
                        form.form_template_id === template.id
                            ? 'border-primary-400 bg-primary-soft/60 shadow-[0_10px_30px_rgba(109,90,230,.12)]'
                            : 'border-line bg-surface/70 hover:-translate-y-0.5 hover:border-primary-300',
                    ]"
                    @click="chooseTemplate(template)"
                >
                    <span :class="['flex h-10 w-10 items-center justify-center rounded-[13px]', form.form_template_id === template.id ? 'bg-primary-600 text-white' : 'bg-surface-2 text-primary-700']">
                        <component :is="mainIcons[template.key]" class="h-5 w-5" />
                    </span>
                    <span class="mt-3 block text-[13px] font-bold text-ink">{{ mainTitles[template.key] || template.name }}</span>
                    <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ mainDescriptions[template.key] || template.description }}</span>
                </button>

                <button
                    type="button"
                    :class="[
                        'flex min-h-[132px] flex-col items-start rounded-[18px] border p-4 text-left transition',
                        ! form.form_template_id
                            ? 'border-primary-400 bg-primary-soft/60'
                            : 'border-dashed border-line-strong bg-surface/50 hover:border-primary-300',
                    ]"
                    @click="clearTemplate"
                >
                    <span :class="['flex h-10 w-10 items-center justify-center rounded-[13px]', ! form.form_template_id ? 'bg-primary-600 text-white' : 'bg-surface-2 text-primary-700']">
                        <SparklesIcon class="h-5 w-5" />
                    </span>
                    <span class="mt-3 block text-[13px] font-bold text-ink">Обычная задача</span>
                    <span class="mt-1 block text-[11px] leading-relaxed text-muted">Свободное описание, чек-лист и поля — без шаблона.</span>
                </button>
            </div>

            <div v-if="secondaryForms.length" class="mt-4 border-t border-line/70 pt-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-primary-700 transition hover:underline"
                    @click="templatePickerOpen = ! templatePickerOpen"
                >
                    {{ templatePickerOpen ? 'Скрыть другие шаблоны' : 'Другие шаблоны' }}
                    <span class="font-mono text-[10px] text-faint">{{ secondaryForms.length }}</span>
                    <ChevronDownIcon :class="['h-3.5 w-3.5 transition', templatePickerOpen ? 'rotate-180' : '']" />
                </button>
                <div v-if="templatePickerOpen" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <FormTemplateCard
                        v-for="template in secondaryForms"
                        :key="template.id"
                        :template="template"
                        :selected="form.form_template_id === template.id"
                        @select="chooseTemplate"
                    />
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
                    :assignee-id="form.assignee_id || null"
                    :types="types"
                    :priorities="priorities"
                    :statuses="statuses"
                    :fields="fields"
                    :errors="form.errors"
                    @update:model-value="update"
                />

                <section v-if="selectedTemplate" class="surface-card mt-4 p-5 sm:p-6">
                    <p class="eyebrow">Структурированная заявка</p>
                    <h2 class="mt-1 text-[15px] font-bold text-ink">{{ selectedTemplate.name }}</h2>
                    <p class="mt-1 text-[11px] text-muted">Поля шаблона — то же, что заполняет клиент.</p>
                    <StructuredForm class="mt-4" :template="selectedTemplate" :model-value="form.form_data" :errors="form.errors" />
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card overflow-hidden">
                    <div class="relative overflow-hidden border-b border-line/70 bg-surface-2 px-5 py-4 text-ink">
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-primary-400/30 blur-2xl"></div>
                        <div class="relative flex items-center gap-3">
                            <KikiAvatar variant="face" fit="contain" :size="34" />
                            <div class="min-w-0">
                                <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted">Черновик</p>
                                <p class="mt-0.5 truncate text-[12px] font-semibold text-ink">Проверьте перед созданием</p>
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
                                <span class="mt-0.5 font-mono text-[11px] text-faint">₽</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Бюджет</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-ink">{{ form.budget ? Number(form.budget).toLocaleString('ru-RU') + ' ₽' : 'не указан' }}</p>
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

                        <Button class="mt-4 w-full" :disabled="form.processing || !form.consent || !form.project_id" @click="submit">
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
