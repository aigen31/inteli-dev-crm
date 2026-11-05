<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import FormTemplateCard from '@/Components/FormTemplates/FormTemplateCard.vue';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { ArrowLeftIcon, ArrowRightIcon, RectangleStackIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { useAssistant } from '@/Composables/useAssistant';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';

const props = defineProps({
    forms: { type: Array, default: () => [] },
    scenariosCount: { type: Number, default: 0 },
    priorities: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
});

const { open } = useAssistant();

const selectedKey = ref(props.forms[0]?.key || null);
const freeform = ref(props.forms.length === 0);
const selected = computed(() => props.forms.find((form) => form.key === selectedKey.value) || null);

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
    title: props.forms[0]?.name || '',
    description: '',
    project_id: new URLSearchParams(window.location.search).get('project_id') || '',
    consent: false,
    priority: 'medium',
    deadline: '',
    form_data: emptyFormData(props.forms[0]),
});

function choose(template) {
    freeform.value = false;
    selectedKey.value = template.key;
    form.title = template.name;
    form.form_data = emptyFormData(template);
    form.clearErrors();
}

function startFree() {
    freeform.value = true;
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

function submit() {
    if (freeform.value) {
        form.post(route('client.tasks.store'));
        return;
    }

    if (selected.value) {
        form.post(route('client.tasks.forms.store', selected.value.key));
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

        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Личный кабинет</p>
                <p class="mt-2 max-w-[680px] text-[12px] leading-relaxed text-muted">
                    Выберите форму — заявка соберётся из понятных полей. Никаких пустых листов и догадок.
                </p>
            </div>
            <button type="button" class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-primary-700 hover:underline" @click="startFree">
                Свободная заявка
                <ArrowRightIcon class="h-3 w-3" />
            </button>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <div class="flex rounded-full border border-line bg-white/50 p-1">
                <span class="rounded-full bg-ink px-3 py-1.5 text-[11px] font-semibold text-canvas">Формы заявок</span>
                <Link :href="route('client.tasks.scenarios')" class="rounded-full px-3 py-1.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                    Сценарии
                    <span class="ml-1 font-mono text-[9px] text-faint">{{ scenariosCount }}</span>
                </Link>
            </div>
            <span class="ml-auto hidden text-[10px] text-faint sm:block">Поля с <span class="text-primary-600">*</span> обязательны</span>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.15fr_.85fr]">
            <div class="min-w-0">
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div>
                        <p class="eyebrow">Выберите форму</p>
                        <p class="mt-1 text-[11px] text-muted">Шаблон — это набор полей под конкретную ситуацию.</p>
                    </div>
                    <span class="font-mono text-[9px] text-faint">{{ forms.length }} форм</span>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <FormTemplateCard
                        v-for="template in forms"
                        :key="template.key"
                        :template="template"
                        :selected="!freeform && selectedKey === template.key"
                        @select="choose"
                    />
                </div>

                <button
                    type="button"
                    :class="[
                        'mt-3 flex w-full items-center gap-3 rounded-[20px] border px-4 py-3 text-left transition',
                        freeform ? 'border-primary-300 bg-primary-50/50' : 'border-line bg-white/35 hover:border-line-strong',
                    ]"
                    @click="startFree"
                >
                    <span class="flex h-9 w-9 items-center justify-center rounded-[12px] bg-surface-2 text-muted"><SparklesIcon class="h-4 w-4" /></span>
                    <span class="min-w-0">
                        <span class="block text-[12px] font-semibold text-ink">Свободная заявка</span>
                        <span class="block text-[10px] text-muted">Если формы пока нет — опишите словами.</span>
                    </span>
                </button>

                <Link :href="route('client.tasks.scenarios')" class="mt-3 flex items-center gap-3 rounded-[20px] border border-line bg-white/35 px-4 py-3 text-left transition hover:border-line-strong">
                    <span class="flex h-9 w-9 items-center justify-center rounded-[12px] bg-surface-2 text-muted"><RectangleStackIcon class="h-4 w-4" /></span>
                    <span class="min-w-0">
                        <span class="block text-[12px] font-semibold text-ink">Классические сценарии</span>
                        <span class="block text-[10px] text-muted">Готовое описание и чек-лист — в отдельном разделе.</span>
                    </span>
                    <ArrowRightIcon class="ml-auto h-4 w-4 text-faint" />
                </Link>
            </div>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <section class="surface-card overflow-hidden">
                    <div class="border-b border-line/80 bg-surface-2/40 px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="eyebrow">{{ freeform ? 'Свободная заявка' : 'Форма' }}</p>
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
                                v-if="freeform && projects.length"
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

                        <div class="mt-4">
                            <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="task-consent" />
                        </div>

                        <div class="mt-5 flex items-center justify-between gap-3 border-t border-line/70 pt-4">
                            <button type="button" class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-primary-700 hover:underline" @click="open">
                                <SparklesIcon class="h-3.5 w-3.5" />
                                Помочь сформулировать
                            </button>
                            <Button size="sm" :disabled="form.processing || !form.consent" @click="submit">
                                Отправить заявку
                                <ArrowRightIcon class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </section>

                <p class="mt-3 px-2 text-center text-[9px] leading-relaxed text-faint">
                    Заявка попадёт владельцу. Он увидит все поля, а не сплошной текст.
                </p>
            </aside>
        </div>
    </ClientLayout>
</template>
