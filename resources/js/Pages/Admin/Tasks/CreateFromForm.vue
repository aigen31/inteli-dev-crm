<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { ArrowLeftIcon, ClipboardDocumentListIcon, PaperClipIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    forms: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    assignees: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
});

const preferred = props.forms.find((form) => form.key === 'task-description-images') || props.forms[0];
const selectedKey = ref(preferred?.key || '');
const selected = computed(() => props.forms.find((form) => form.key === selectedKey.value) || null);
const form = useForm({
    client_id: '',
    project_id: '',
    assignee_id: '',
    title: preferred?.name || '',
    priority: 'medium',
    deadline: '',
    budget: '',
    form_data: emptyFormData(preferred),
    consent: false,
});

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

function choose(key) {
    selectedKey.value = key;
    const template = props.forms.find((item) => item.key === key);
    if (!template) return;
    form.title = template.name;
    form.form_data = emptyFormData(template);
    form.clearErrors();
}

function submit() {
    if (!selected.value) return;
    form.post(route('admin.tasks.forms.store', selected.value.key), { onSuccess: () => form.reset() });
}
</script>

<template>
    <AdminLayout title="Задача из шаблона">
        <template #actions>
            <Link :href="route('admin.tasks.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Задачи</Button></Link>
            <Link :href="route('admin.tasks.create')"><Button variant="secondary" size="sm">Без шаблона</Button></Link>
        </template>

        <section class="mb-5 flex flex-wrap items-center gap-4 rounded-[26px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-5 sm:px-7">
            <span class="flex h-11 w-11 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700"><ClipboardDocumentListIcon class="h-5 w-5" /></span>
            <div class="min-w-0 flex-1">
                <p class="eyebrow">Быстрый сценарий</p>
                <h1 class="mt-1 font-display text-[22px] font-medium tracking-[-0.03em] text-ink">Задача из готового шаблона</h1>
                <p class="mt-1 max-w-[600px] text-[11px] leading-relaxed text-muted">Выберите шаблон, укажите клиента и исполнителя — структура, поля и изображения уже внутри.</p>
            </div>
            <KikiAvatar :size="48" />
        </section>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-5">
                <section class="surface-card p-5 sm:p-6">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div><p class="eyebrow">01 / Шаблон</p><h2 class="mt-1 text-[14px] font-bold text-ink">С чего начнём?</h2></div>
                        <span class="font-mono text-[9px] text-faint">по умолчанию «Описание задачи + изображения»</span>
                    </div>
                    <div class="mt-4">
                        <SelectInput :model-value="selectedKey" :options="forms.map((item) => ({ value: item.key, label: item.name }))" placeholder="Выберите шаблон" @update:model-value="choose" />
                    </div>
                    <div v-if="selected" class="mt-3 rounded-[14px] border border-line/70 bg-white/40 px-3.5 py-3">
                        <p class="text-[12px] font-semibold text-ink">{{ selected.name }}</p>
                        <p class="mt-1 text-[10px] leading-relaxed text-muted">{{ selected.description }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-[9px] text-faint">
                            <span>{{ selected.field_count }} полей</span>
                            <span>·</span>
                            <span>~{{ selected.estimated_minutes }} мин</span>
                        </div>
                    </div>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">02 / Куда и кому</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SelectInput v-model="form.client_id" label="Клиент" :options="clients.map((c) => ({ value: c.id, label: c.name }))" placeholder="Выберите клиента" :error="form.errors.client_id" />
                        <SelectInput v-model="form.project_id" label="Проект" :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))" placeholder="Без проекта" :error="form.errors.project_id" />
                        <SelectInput v-model="form.assignee_id" label="Исполнитель" :options="assignees.map((a) => ({ value: a.id, label: a.name }))" placeholder="Не назначен" :error="form.errors.assignee_id" />
                        <SelectInput v-model="form.priority" label="Приоритет" :options="priorities" :error="form.errors.priority" />
                        <TextInput v-model="form.deadline" label="Срок" type="date" :error="form.errors.deadline" />
                        <TextInput v-model="form.budget" label="Бюджет, ₽" type="number" step="0.01" min="0" placeholder="Необязательно" :error="form.errors.budget" />
                        <div class="sm:col-span-2"><TextInput v-model="form.title" label="Название задачи" :error="form.errors.title" placeholder="Название из формы" /></div>
                    </div>
                </section>

                <section v-if="selected" class="surface-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div><p class="eyebrow">03 / Содержание</p><h2 class="mt-1 text-[14px] font-bold text-ink">{{ selected.name }}</h2></div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary-soft px-2.5 py-1 text-[9px] font-semibold text-primary-800"><PaperClipIcon class="h-3 w-3" />изображения внутри</span>
                    </div>
                    <StructuredForm class="mt-5" :template="selected" :model-value="form.form_data" :errors="form.errors" />
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card overflow-hidden">
                    <div class="border-b border-line/70 bg-surface-2/50 px-5 py-4">
                        <p class="eyebrow">Итог</p>
                        <p class="mt-1 text-[13px] font-semibold text-ink">{{ form.title || selected?.name || 'Задача из шаблона' }}</p>
                        <p class="mt-1 text-[10px] text-muted">{{ selected?.name }} · {{ form.priority }}</p>
                    </div>
                    <div class="p-5">
                        <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="admin-form-task-consent" variant="contract" />
                        <Button class="mt-4 w-full" :disabled="form.processing || !form.consent || !selected || !form.client_id" @click="submit">
                            {{ form.processing ? 'Создаю…' : 'Создать задачу из шаблона' }}
                        </Button>
                        <p class="mt-2 text-center text-[9px] leading-relaxed text-faint">Все поля шаблона сохранятся в карточке задачи.</p>
                    </div>
                </section>
                <section class="rounded-[20px] border border-line/70 bg-white/35 p-4">
                    <p class="eyebrow">Почему шаблон</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Шаблон задаёт правильные поля, обязательные шаги и места для изображений. Так заявка не теряет детали.</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
