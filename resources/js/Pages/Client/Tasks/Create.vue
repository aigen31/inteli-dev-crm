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
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import TaskDraftNotice from '@/Components/Task/TaskDraftNotice.vue';
import { useTaskDraft } from '@/Composables/useTaskDraft';
import { ArrowLeftIcon, ArrowRightIcon, CalendarDaysIcon, CheckCircleIcon, ClipboardDocumentListIcon, FolderIcon, RectangleStackIcon, SparklesIcon } from '@heroicons/vue/24/outline';
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
    title: preferredForm?.name || '',
    description: '',
    project_id: new URLSearchParams(window.location.search).get('project_id') || '',
    consent: false,
    priority: 'medium',
    deadline: '',
    form_data: emptyFormData(preferredForm),
});

function choose(template) {
    freeform.value = false;
    pickerOpen.value = false;
    selectedKey.value = template.key;
    form.title = template.name;
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

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <div class="flex rounded-full border border-line bg-white/50 p-1">
                <span class="rounded-full bg-ink px-3 py-1.5 text-[11px] font-semibold text-canvas">Шаблоны заявок</span>
            </div>
            <button type="button" class="ml-auto inline-flex items-center gap-1.5 text-[10px] font-semibold text-primary-700 hover:underline" @click="startFree">
                Свободная заявка
                <ArrowRightIcon class="h-3 w-3" />
            </button>
        </div>

        <section class="surface-card mb-5 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="eyebrow">01 / Шаблон</p>
                    <h2 class="mt-1 text-[14px] font-bold text-ink">С чего начнём?</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="font-mono text-[9px] text-faint">{{ forms.length }} <span>{{ $t('шаблонов') }}</span>&nbsp;·&nbsp;{{ $t('можно выбрать одну') }}</span>
                    <button type="button" class="rounded-full border border-line bg-white/45 px-2.5 py-1 text-[9px] font-semibold text-primary-700 transition hover:border-primary-300" @click="pickerOpen = !pickerOpen">
                        {{ pickerOpen ? $t('Скрыть выбор') : $t('Выбрать другую') }}
                    </button>
                </div>
            </div>

            <div v-if="pickerOpen" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <FormTemplateCard
                    v-for="template in forms"
                    :key="template.key"
                    :template="template"
                    :selected="!freeform && selectedKey === template.key"
                    @select="choose"
                />

                <button
                    type="button"
                    :class="[
                        'flex min-h-[92px] items-center gap-3 rounded-[20px] border px-4 py-3 text-left transition',
                        freeform ? 'border-primary-300 bg-primary-50/50' : 'border-line bg-white/35 hover:border-line-strong',
                    ]"
                    @click="startFree"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[12px] bg-surface-2 text-muted"><SparklesIcon class="h-4 w-4" /></span>
                    <span class="min-w-0">
                        <span class="block text-[12px] font-semibold text-ink">Свободная заявка</span>
                        <span class="mt-0.5 block text-[10px] leading-relaxed text-muted">Опишите словами, если готового шаблона нет.</span>
                    </span>
                </button>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">02</span> Детали</span>
                    <span class="text-faint">→</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/50 px-2.5 py-1 text-[10px] font-semibold text-ink-soft"><span class="font-mono text-[9px] text-primary-600">03</span> Проверка</span>
                </div>

                <section class="surface-card overflow-hidden">
                    <div class="border-b border-line/80 bg-surface-2/40 px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="eyebrow">{{ freeform ? 'Свободная заявка' : 'Выбранная форма' }}</p>
                                <h2 class="mt-1 text-[17px] font-bold tracking-[-0.02em] text-ink">{{ freeform ? 'Опишите словами' : selected?.name }}</h2>
                                <p class="mt-1 text-[10px] leading-relaxed text-muted">{{ freeform ? 'Мы уточним детали, если чего-то не хватит.' : selected?.description }}</p>
                            </div>
                            <KikiAvatar :size="34" />
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="space-y-4">
                            <TextInput v-model="form.title" label="Название заявки" :error="form.errors.title" placeholder="Коротко: что нужно сделать" />

                            <SelectInput
                                v-if="projects.length"
                                v-model="form.project_id"
                                label="Проект (необязательно)"
                                :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))"
                                placeholder="Без проекта"
                                :error="form.errors.project_id"
                            />

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
                                <TextInput v-model="form.deadline" label="Желаемый срок" type="date" :error="form.errors.deadline" />
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

                        <Button class="mt-4 w-full" :disabled="form.processing || !form.consent" @click="submit">
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
