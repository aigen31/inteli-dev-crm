<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import FormTemplateCard from '@/Components/FormTemplates/FormTemplateCard.vue';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TaskDateCalendar from '@/Components/Form/TaskDateCalendar.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import TaskDraftNotice from '@/Components/Task/TaskDraftNotice.vue';
import { useTaskDraft } from '@/Composables/useTaskDraft';
import { ArrowLeftIcon, ArrowRightIcon, BugAntIcon, CalendarDaysIcon, CheckCircleIcon, ChevronDownIcon, ClipboardDocumentListIcon, FolderIcon, RectangleStackIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { useAssistant } from '@/Composables/useAssistant';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';

const props = defineProps({
    forms: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },

});

const { open } = useAssistant();
const page = usePage();

const preferredForm = props.forms.find((form) => form.key === 'task-description-images') || props.forms[0];
const selectedKey = ref(preferredForm?.key || null);
const freeform = ref(props.forms.length === 0);
const pickerOpen = ref(false);
const selected = computed(() => props.forms.find((form) => form.key === selectedKey.value) || null);

const mainKeys = ['task-description-images', 'bug-report'];
const mainForms = computed(() =>
    mainKeys.map((key) => props.forms.find((candidate) => candidate.key === key)).filter(Boolean));
const secondaryForms = computed(() => props.forms.filter((candidate) => !mainKeys.includes(candidate.key)));

const mainTitles = {
    'task-description-images': 'Описание задачи + изображения',
    'bug-report': 'Баг-репорт',
    free: 'Свободная заявка',
};

const mainDescriptions = {
    'task-description-images': 'Задача своими словами и скриншоты — для нового запроса или правки.',
    'bug-report': 'Что сломалось, как воспроизвести и что ожидалось.',
    free: 'Пустая заявка без полей: опишите словами, я уточню.',
};

const mainIcons = {
    'task-description-images': ClipboardDocumentListIcon,
    'bug-report': BugAntIcon,
    free: SparklesIcon,
};

function isSelectedForm(key) {
    return ! freeform.value && selectedKey.value === key;
}
const selectedProject = computed(() => (props.projects || []).find((project) => project.id === form.project_id));
const priorityLabel = computed(() => (props.priorities || []).find((priority) => priority.value === form.priority)?.label || form.priority);

function emptyFormData(template) {
    if (!template) return { fields: {}, groups: {} };
    const schema = template.schema || {};
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

const form = useForm({
    title: '',
    description: '',
    project_id: new URLSearchParams(window.location.search).get('project_id') || '',
    consent: false,
    priority: 'medium',
    starts_at: '',
    deadline: '',
    form_data: emptyFormData(preferredForm),
});

function choose(template) {
    freeform.value = false;
    pickerOpen.value = false;
    selectedKey.value = template.key;
    form.form_data = emptyFormData(template);
    form.clearErrors();
}

function startFree() {
    freeform.value = true;
    pickerOpen.value = false;
    selectedKey.value = null;
    form.title = '';
    form.description = '';
    form.form_data = { fields: {}, groups: {} };
    form.clearErrors();
}

onMounted(() => {
    const key = new URLSearchParams(window.location.search).get('form');
    const template = props.forms.find((form) => form.key === key);
    if (template) choose(template);
});

const draft = useTaskDraft({
    key: `client:create:${page.props.auth?.user?.id || 'anonymous'}`,
    snapshot: () => ({
        selectedKey: selectedKey.value,
        freeform: freeform.value,
        title: form.title,
        description: form.description,
        project_id: form.project_id,
        priority: form.priority,
        starts_at: form.starts_at,
        deadline: form.deadline,
        form_data: form.form_data,
    }),
    restore: (data) => {
        selectedKey.value = data.selectedKey || null;
        freeform.value = Boolean(data.freeform || !data.selectedKey);
        form.title = data.title || '';
        form.description = data.description || '';
        form.project_id = data.project_id || '';
        form.priority = data.priority || 'medium';
        form.deadline = data.deadline || '';
        form.form_data = data.form_data || { fields: {}, groups: {} };
        form.consent = false;
    },
    isEmpty: (data) => !data.description && !data.project_id && !data.title && !Object.values(data.form_data?.fields || {}).some((value) => value !== '' && value !== false && value !== null) && !Object.values(data.form_data?.groups || {}).some((rows) => Array.isArray(rows) && rows.some((row) => Object.values(row).some((value) => value !== '' && value !== false && value !== null))),
});

function submit() {
    if (freeform.value) {
        form.post(route('client.tasks.store'), { onSuccess: () => draft.clear() });
        return;
    }

    if (selected.value) {
        form.post(route('client.tasks.forms.store', selected.value.key), { onSuccess: () => draft.clear() });
    }
}
</script>

<template>
    <ClientLayout title="Новая заявка">
        <template #actions>
            <Link :href="route('client.tasks.index')" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                <ArrowLeftIcon class="h-3.5 w-3.5" />
                Мои задачи
            </Link>
        </template>

        <section class="relative mb-5 overflow-hidden rounded-[28px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-5 sm:px-7 sm:py-6">
            <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700">
                    <ClipboardDocumentListIcon class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Личный кабинет</p>
                    <h1 class="mt-1 font-display text-[22px] font-medium tracking-[-0.035em] text-ink">Соберите заявку без лишних полей</h1>
                    <p class="mt-1 max-w-[620px] text-[11px] leading-relaxed text-muted">Сначала выберите шаблон, затем заполните только то, что относится к вашей ситуации. Справа всегда видно, что получится.</p>
                </div>
                <div class="flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-3 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                    <span class="font-mono text-[9px] uppercase tracking-[0.1em] text-muted">3 шага</span>
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
                        Три основных сценария. Название задачи вы придумываете сами — шаблон подставит только поля и подсказки.
                    </p>
                </div>
                <span class="rounded-full border border-line bg-surface-2/60 px-3 py-1 font-mono text-[9px] uppercase tracking-[0.12em] text-muted">шаг 1 из 3</span>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <button
                    v-for="template in mainForms"
                    :key="template.key"
                    type="button"
                    :class="[
                        'group flex min-h-[136px] flex-col items-start rounded-[18px] border p-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40',
                        isSelectedForm(template.key)
                            ? 'border-primary-400 bg-primary-soft/60 shadow-[0_10px_30px_rgba(109,90,230,.12)]'
                            : 'border-line bg-surface/70 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-[0_10px_24px_rgba(28,24,35,.06)]',
                    ]"
                    :aria-pressed="isSelectedForm(template.key)"
                    @click="choose(template)"
                >
                    <span :class="['flex h-10 w-10 items-center justify-center rounded-[13px]', isSelectedForm(template.key) ? 'bg-primary-600 text-white' : 'bg-surface-2 text-primary-700']">
                        <component :is="mainIcons[template.key]" class="h-5 w-5" />
                    </span>
                    <span class="mt-3 block text-[13px] font-bold text-ink">{{ mainTitles[template.key] || template.name }}</span>
                    <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ mainDescriptions[template.key] || template.description }}</span>
                    <span v-if="isSelectedForm(template.key)" class="mt-2 inline-flex items-center gap-1 text-[10px] font-semibold text-primary-700">
                        <CheckCircleIcon class="h-3.5 w-3.5" />Выбрано
                    </span>
                </button>

                <button
                    type="button"
                    :class="[
                        'group flex min-h-[136px] flex-col items-start rounded-[18px] border p-4 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40',
                        freeform
                            ? 'border-primary-400 bg-primary-soft/60 shadow-[0_10px_30px_rgba(109,90,230,.12)]'
                            : 'border-dashed border-line-strong bg-surface/50 hover:-translate-y-0.5 hover:border-primary-300',
                    ]"
                    :aria-pressed="freeform"
                    @click="startFree"
                >
                    <span :class="['flex h-10 w-10 items-center justify-center rounded-[13px]', freeform ? 'bg-primary-600 text-white' : 'bg-surface-2 text-primary-700']">
                        <SparklesIcon class="h-5 w-5" />
                    </span>
                    <span class="mt-3 block text-[13px] font-bold text-ink">{{ mainTitles.free }}</span>
                    <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ mainDescriptions.free }}</span>
                    <span v-if="freeform" class="mt-2 inline-flex items-center gap-1 text-[10px] font-semibold text-primary-700">
                        <CheckCircleIcon class="h-3.5 w-3.5" />Выбрано
                    </span>
                </button>
            </div>

            <div v-if="secondaryForms.length" class="mt-4 border-t border-line/70 pt-3">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-primary-700 transition hover:underline"
                    :aria-expanded="pickerOpen"
                    @click="pickerOpen = !pickerOpen"
                >
                    {{ pickerOpen ? 'Скрыть другие шаблоны' : 'Другие шаблоны' }}<span class="ml-1 font-mono text-[10px] text-faint">{{ secondaryForms.length }}</span>
                    <ChevronDownIcon :class="['h-3.5 w-3.5 transition', pickerOpen ? 'rotate-180' : '']" />
                </button>

                <div v-if="pickerOpen" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <FormTemplateCard
                        v-for="template in secondaryForms"
                        :key="template.key"
                        :template="template"
                        :selected="isSelectedForm(template.key)"
                        @select="choose"
                    />
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">02</span> Детали</span>
                    <span class="text-faint">→</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">03</span> Проверка</span>
                </div>

                <section class="surface-card overflow-visible">
                    <div class="border-b border-line/80 bg-surface-2/40 px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="eyebrow">{{ freeform ? 'Свободная заявка' : 'Выбранный шаблон' }}</p>
                                <h2 class="mt-1 text-[17px] font-bold tracking-[-0.02em] text-ink">{{ freeform ? 'Опишите словами' : selected?.name }}</h2>
                                <p class="mt-1 text-[10px] leading-relaxed text-muted">{{ freeform ? 'Мы уточним детали, если чего-то не хватит.' : selected?.description }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <Button variant="secondary" size="sm" @click="pickerOpen = !pickerOpen">
                                    <RectangleStackIcon class="h-3.5 w-3.5" />
                                    {{ pickerOpen ? $t('Скрыть выбор') : $t('Сменить шаблон') }}
                                </Button>
                                <span class="text-[9px] text-faint">{{ $t('не подходит? выберите другой') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="space-y-4">
                            <TextInput v-model="form.title" label="Название заявки" :error="form.errors.title" placeholder="Коротко: что нужно сделать" />

                            <SelectInput
                                v-if="projects.length"
                                v-model="form.project_id"
                                label="Проект"
                                :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))"
                                placeholder="Выберите проект" required
                                :error="form.errors.project_id"
                            />
                            <p v-if="projects.length" class="mt-1 text-[10px] text-muted">Проект обязателен — задача появится в нём у клиента.</p>
                            <p v-else class="mt-1 text-[10px] text-danger-ink">У вас нет доступных проектов. Попросите владельца добавить вас в проект.</p>

                            <template v-if="freeform">
                                <div>
                                    <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">Описание</p>
                                    <TextArea v-model="form.description" :rows="6" placeholder="Что случилось, что уже пробовали, какой результат нужен…" :error="form.errors.description" />
                                </div>
                            </template>

                            <StructuredForm
                                v-else-if="selected"
                                :template="selected"
                                :model-value="form.form_data"
                                :errors="form.errors"
                            />

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <SelectInput v-model="form.priority" label="Приоритет" :options="priorities" placeholder="Выберите приоритет" :error="form.errors.priority" />
                                <TaskDateCalendar v-model="form.starts_at" label="Старт работ"  :error="form.errors.starts_at" />
                                <TaskDateCalendar v-model="form.deadline" label="Желаемый срок"  :error="form.errors.deadline" />
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card overflow-hidden">
                    <div class="relative overflow-hidden border-b border-line/70 bg-ink px-5 py-4 text-canvas">
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-primary-400/30 blur-2xl"></div>
                        <div class="relative flex items-center gap-3">
                            <KikiAvatar variant="face" fit="contain" :size="34" />
                            <div class="min-w-0">
                                <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-canvas/60">Заявка</p>
                                <p class="mt-0.5 truncate text-[12px] font-semibold text-canvas">Проверьте перед отправкой</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="space-y-3">
                            <div class="flex items-start gap-2.5">
                                <ClipboardDocumentListIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Шаблон</p>
                                    <p class="mt-0.5 truncate text-[11px] font-semibold text-ink">{{ freeform ? 'Свободная заявка' : selected?.name }}</p>
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
                                <CalendarDaysIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-[9px] uppercase tracking-[0.1em] text-faint">Приоритет и срок</p>
                                    <p class="mt-0.5 text-[11px] font-semibold text-ink">{{ priorityLabel }} · {{ form.deadline || 'без срока' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 border-t border-line/70 pt-4">
                            <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="task-consent" />
                        </div>

                        <Button class="mt-4 w-full" :disabled="form.processing || !form.consent || !form.project_id" @click="submit">
                            <CheckCircleIcon class="h-4 w-4" />
                            {{ form.processing ? 'Отправляю…' : 'Отправить заявку' }}
                        </Button>
                        <div class="mt-3 flex justify-center">
                            <button type="button" class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-primary-700 hover:underline" @click="open">
                                <SparklesIcon class="h-3.5 w-3.5" />
                                Помочь сформулировать
                            </button>
                        </div>
                        <p class="mt-2 text-center text-[9px] leading-relaxed text-faint">Заявка попадёт владельцу со всеми выбранными полями.</p>
                    </div>
                </section>

                <section class="rounded-[20px] border border-line/70 bg-white/35 p-4">
                    <p class="eyebrow">Что дальше</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Владелец увидит заявку, уточнит детали и возьмёт в работу. Все изменения появятся в «Моих задачах».</p>
                </section>
            </aside>
        </div>
    </ClientLayout>
</template>
