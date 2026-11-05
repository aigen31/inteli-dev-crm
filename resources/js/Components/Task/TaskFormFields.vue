<script setup>
import { computed } from 'vue';
import RichTextEditor from '@/Components/Form/RichTextEditor.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import CustomFieldsForm from '@/Components/CustomFields/CustomFieldsForm.vue';
import Button from '@/Components/UI/Button.vue';
import { ArrowDownIcon, ArrowUpIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: Object, required: true },
    clients: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    assignees: { type: Array, default: () => [] },
    templates: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    fields: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    showStatus: { type: Boolean, default: true },
    showTemplate: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const checklist = computed(() => props.modelValue.checklist || []);

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}

function selectProject(projectId) {
    const project = props.projects.find((item) => item.id === projectId);
    const next = { ...props.modelValue, project_id: projectId };

    if (project?.client_id && !props.modelValue.client_id) {
        next.client_id = project.client_id;
    }

    emit('update:modelValue', next);
}

function applyTemplate(templateId) {
    const template = props.templates.find((item) => item.id === templateId);
    const next = { ...props.modelValue, task_template_id: templateId };

    if (template) {
        next.title = props.modelValue.title || template.name;
        next.description = template.description || '';
        next.checklist = (template.checklist || []).map((item) => ({ ...item }));
        next.type = template.default_type || props.modelValue.type;
        next.priority = template.default_priority || props.modelValue.priority;

        if (template.default_due_days && !props.modelValue.deadline) {
            const date = new Date();
            date.setDate(date.getDate() + Number(template.default_due_days));
            next.deadline = date.toISOString().slice(0, 10);
        }

        if (template.default_assignee_id) next.assignee_id = template.default_assignee_id;
        if (template.estimated_minutes) next.estimated_minutes = template.estimated_minutes;
    }

    emit('update:modelValue', next);
}

function addChecklistItem() {
    update('checklist', [...checklist.value, { text: '', done: false }]);
}

function removeChecklistItem(index) {
    const next = [...checklist.value];
    next.splice(index, 1);
    update('checklist', next);
}

function moveChecklistItem(index, direction) {
    const next = [...checklist.value];
    const target = index + direction;
    if (target < 0 || target >= next.length) return;
    const [item] = next.splice(index, 1);
    next.splice(target, 0, item);
    update('checklist', next);
}

function error(key) {
    return props.errors?.[key] || null;
}

const statusOptions = computed(() =>
    (props.statuses || []).filter((status) => ['pending_review', 'queued'].includes(status.value)),
);
</script>

<template>
    <div class="space-y-5">
        <section class="surface-card p-5 sm:p-6">
            <p class="eyebrow">Суть</p>
            <div class="mt-4 space-y-4">
                <SelectInput
                    v-if="showTemplate"
                    :model-value="modelValue.task_template_id || ''"
                    label="Шаблон (необязательно)"
                    :options="templates.map((t) => ({ value: t.id, label: t.name }))"
                    placeholder="Без шаблона"
                    @update:model-value="applyTemplate"
                />
                <TextInput :model-value="modelValue.title" label="Название" :error="error('title')" placeholder="Что нужно сделать" @update:model-value="update('title', $event)" />
                <div>
                    <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">Описание</p>
                    <RichTextEditor :model-value="modelValue.description" placeholder="Что входит, что нужно от клиента, критерии готовности…" @update:model-value="update('description', $event)" />
                    <p v-if="error('description')" class="mt-1 text-[10px] text-danger-ink">{{ error('description') }}</p>
                </div>
            </div>
        </section>

        <section class="surface-card p-5 sm:p-6">
            <p class="eyebrow">Детали</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <SelectInput
                    :model-value="modelValue.client_id"
                    label="Клиент"
                    :options="clients.map((c) => ({ value: c.id, label: c.name }))"
                    placeholder="Выберите клиента"
                    :error="error('client_id')"
                    @update:model-value="update('client_id', $event)"
                />
                <SelectInput
                    :model-value="modelValue.project_id || ''"
                    label="Проект"
                    :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))"
                    placeholder="Без проекта"
                    :error="error('project_id')"
                    @update:model-value="selectProject"
                />
                <SelectInput
                    :model-value="modelValue.assignee_id || ''"
                    label="Исполнитель (необязательно)"
                    :options="assignees.map((a) => ({ value: a.id, label: a.name }))"
                    placeholder="Не назначен"
                    :error="error('assignee_id')"
                    @update:model-value="update('assignee_id', $event)"
                />
                <SelectInput
                    :model-value="modelValue.type"
                    label="Тип"
                    :options="types"
                    placeholder="Выберите тип"
                    :error="error('type')"
                    @update:model-value="update('type', $event)"
                />
                <SelectInput
                    :model-value="modelValue.priority"
                    label="Приоритет"
                    :options="priorities"
                    placeholder="Выберите приоритет"
                    :error="error('priority')"
                    @update:model-value="update('priority', $event)"
                />
                <SelectInput
                    v-if="showStatus"
                    :model-value="modelValue.status"
                    label="Статус"
                    :options="statusOptions"
                    placeholder="Выберите статус"
                    :error="error('status')"
                    @update:model-value="update('status', $event)"
                />
                <TextInput
                    :model-value="modelValue.deadline || ''"
                    label="Дедлайн (необязательно)"
                    type="date"
                    :error="error('deadline')"
                    @update:model-value="update('deadline', $event)"
                />
                <TextInput
                    :model-value="modelValue.estimated_minutes || ''"
                    label="Оценка, минут"
                    type="number"
                    :error="error('estimated_minutes')"
                    @update:model-value="update('estimated_minutes', $event)"
                />
            </div>
        </section>

        <section class="surface-card p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="eyebrow">Чек-лист</p>
                    <h2 class="mt-1 text-[14px] font-bold text-ink">Шаги задачи</h2>
                </div>
                <Button variant="secondary" size="sm" @click="addChecklistItem">
                    <PlusIcon class="h-3.5 w-3.5" />
                    Добавить
                </Button>
            </div>

            <div v-if="!checklist.length" class="mt-4 rounded-2xl border border-dashed border-line px-4 py-5 text-center text-[11px] text-muted">
                Чек-лист пуст. Можно добавить шаги вручную или выбрать шаблон.
            </div>
            <div v-else class="mt-4 space-y-2">
                <div v-for="(item, index) in checklist" :key="index" class="flex items-center gap-2">
                    <span class="w-5 shrink-0 text-center font-mono text-[10px] text-faint">{{ index + 1 }}</span>
                    <input
                        :value="item.text"
                        class="h-9 flex-1 rounded-[11px] border border-line bg-white/70 px-3 text-[12px] text-ink focus:border-primary-400 focus:outline-none"
                        placeholder="Что сделать"
                        @input="update('checklist', checklist.map((row, i) => (i === index ? { ...row, text: $event.target.value } : row)))"
                    />
                    <button type="button" class="rounded-lg p-1.5 text-muted hover:text-ink" @click="moveChecklistItem(index, -1)"><ArrowUpIcon class="h-3.5 w-3.5" /></button>
                    <button type="button" class="rounded-lg p-1.5 text-muted hover:text-ink" @click="moveChecklistItem(index, 1)"><ArrowDownIcon class="h-3.5 w-3.5" /></button>
                    <button type="button" class="rounded-lg p-1.5 text-muted hover:text-danger" @click="removeChecklistItem(index)"><TrashIcon class="h-3.5 w-3.5" /></button>
                </div>
            </div>
        </section>

        <section v-if="fields.length" class="surface-card p-5 sm:p-6">
            <p class="eyebrow">Поля</p>
            <div class="mt-4">
                <CustomFieldsForm
                    :model-value="modelValue.custom_fields || {}"
                    :fields="fields"
                    :errors="errors"
                    @update:model-value="update('custom_fields', $event)"
                />
            </div>
        </section>
    </div>
</template>
