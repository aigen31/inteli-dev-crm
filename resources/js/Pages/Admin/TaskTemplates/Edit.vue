<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import RichTextEditor from '@/Components/Form/RichTextEditor.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import {
    ArrowDownIcon,
    ArrowLeftIcon,
    ArrowUpIcon,
    PlusIcon,
    SparklesIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    template: Object,
    clients: Array,
    assignees: Array,
    types: Array,
    priorities: Array,
    statuses: Array,
});

const editing = computed(() => Boolean(props.template?.id));

const form = useForm({
    name: props.template?.name || '',
    summary: props.template?.summary || '',
    description: props.template?.description || '',
    checklist: props.template?.checklist?.length ? props.template.checklist.map((item) => ({ ...item })) : [],
    default_type: props.template?.default_type || 'one_time',
    default_priority: props.template?.default_priority || 'medium',
    default_status: props.template?.default_status || 'pending_review',
    default_due_days: props.template?.default_due_days ?? 3,
    estimated_minutes: props.template?.estimated_minutes ?? 120,
    default_assignee_id: props.template?.default_assignee_id || '',
    client_id: props.template?.client_id || '',
    is_active: props.template?.is_active ?? true,
    is_shared: props.template?.is_shared ?? true,
});

const ai = ref({ brief: '', loading: false, warning: null });
const showDelete = ref(false);

const statusOptions = computed(() =>
    (props.statuses || [])
        .filter((status) => ['pending_review', 'queued'].includes(status.value))
        .map((status) => ({ value: status.value, label: taskStatusLabels[status.value] || status.label })),
);

function addChecklistItem() {
    form.checklist.push({ text: '', done: false });
}

function removeChecklistItem(index) {
    form.checklist.splice(index, 1);
}

function moveChecklistItem(index, direction) {
    const target = index + direction;
    if (target < 0 || target >= form.checklist.length) return;
    const [item] = form.checklist.splice(index, 1);
    form.checklist.splice(target, 0, item);
}

async function generate() {
    if (ai.value.brief.trim().length < 10 || ai.value.loading) return;

    ai.value.loading = true;
    ai.value.warning = null;

    try {
        const { data } = await axios.post(route('admin.task-templates.generate'), { brief: ai.value.brief });
        form.name = data.name || form.name;
        form.summary = data.summary || form.summary;
        form.description = data.description || form.description;
        form.checklist = (data.checklist || []).map((text) => ({ text, done: false }));
        form.default_priority = data.default_priority || form.default_priority;
        form.default_due_days = data.default_due_days ?? form.default_due_days;
        ai.value.warning = data.warning || null;
    } catch (error) {
        ai.value.warning = error?.response?.data?.message || 'Не удалось собрать шаблон. Попробуйте ещё раз.';
    } finally {
        ai.value.loading = false;
    }
}

function submit() {
    if (editing.value) {
        form.put(route('admin.task-templates.update', props.template.id), { preserveScroll: true });
    } else {
        form.post(route('admin.task-templates.store'), { preserveScroll: true });
    }
}

function destroy() {
    router.delete(route('admin.task-templates.destroy', props.template.id), { onSuccess: () => (showDelete.value = false) });
}
</script>

<template>
    <AdminLayout :title="editing ? 'Редактировать шаблон' : 'Новый шаблон'">
        <template #actions>
            <Link :href="route('admin.task-templates.index')">
                <Button variant="ghost" size="sm">
                    <ArrowLeftIcon class="h-4 w-4" />
                    Все шаблоны
                </Button>
            </Link>
            <Button v-if="editing" variant="secondary" size="sm" @click="showDelete = true">Удалить</Button>
            <Button size="sm" :disabled="form.processing" @click="submit">{{ editing ? 'Сохранить' : 'Создать шаблон' }}</Button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.4fr_.8fr]">
            <div class="space-y-5">
                <section class="surface-card p-5 sm:p-6">
                    <div class="flex items-start gap-3">
                        <KikiAvatar :size="44" class="kiki-ring" />
                        <div class="min-w-0">
                            <p class="eyebrow">Кики поможет</p>
                            <h2 class="mt-1 text-[15px] font-bold text-ink">Собрать структуру из идеи</h2>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted">Опишите, что обычно делает клиент. Кики предложит описание и чек-лист — вы отредактируете.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <input
                            v-model="ai.brief"
                            class="h-10 flex-1 rounded-[13px] border border-line bg-white/70 px-3.5 text-[12px] text-ink placeholder-faint focus:border-primary-400 focus:outline-none"
                            placeholder="Например: клиент просит настроить интеграцию с CRM…"
                            @keydown.enter.prevent="generate"
                        />
                        <Button variant="dark" :disabled="ai.loading || ai.brief.trim().length < 10" @click="generate">
                            <SparklesIcon class="h-4 w-4" />
                            {{ ai.loading ? 'Собираю…' : 'Собрать' }}
                        </Button>
                    </div>
                    <p v-if="ai.warning" class="mt-2 rounded-xl bg-warning/10 px-3 py-2 text-[10px] leading-relaxed text-warning">{{ ai.warning }}</p>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Основа</p>
                    <div class="mt-4 space-y-4">
                        <TextInput v-model="form.name" label="Название шаблона" :error="form.errors.name" placeholder="Например: Лендинг под ключ" />
                        <TextInput v-model="form.summary" label="Короткое описание" :error="form.errors.summary" placeholder="Одна строка, что делает шаблон" />
                        <div>
                            <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">Подробное описание</p>
                            <RichTextEditor v-model="form.description" placeholder="Что входит, что нужно от клиента, критерии готовности…" />
                            <p v-if="form.errors.description" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="eyebrow">Чек-лист</p>
                            <h2 class="mt-1 text-[15px] font-bold text-ink">Шаги, которые нельзя забыть</h2>
                        </div>
                        <Button variant="secondary" size="sm" @click="addChecklistItem">
                            <PlusIcon class="h-3.5 w-3.5" />
                            Добавить
                        </Button>
                    </div>

                    <div v-if="!form.checklist.length" class="mt-4 rounded-2xl border border-dashed border-line px-4 py-6 text-center text-[11px] text-muted">
                        Пока нет шагов. Добавьте 3–7 конкретных пунктов.
                    </div>

                    <div v-else class="mt-4 space-y-2">
                        <div v-for="(item, index) in form.checklist" :key="index" class="flex items-center gap-2">
                            <span class="w-5 shrink-0 text-center font-mono text-[10px] text-faint">{{ index + 1 }}</span>
                            <input
                                v-model="item.text"
                                class="h-9 flex-1 rounded-[11px] border border-line bg-white/70 px-3 text-[12px] text-ink focus:border-primary-400 focus:outline-none"
                                placeholder="Что сделать"
                            />
                            <button type="button" class="rounded-lg p-1.5 text-muted hover:text-ink" title="Выше" @click="moveChecklistItem(index, -1)">
                                <ArrowUpIcon class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="rounded-lg p-1.5 text-muted hover:text-ink" title="Ниже" @click="moveChecklistItem(index, 1)">
                                <ArrowDownIcon class="h-3.5 w-3.5" />
                            </button>
                            <button type="button" class="rounded-lg p-1.5 text-muted hover:text-danger" title="Удалить" @click="removeChecklistItem(index)">
                                <TrashIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                    <p v-if="form.errors.checklist" class="mt-2 text-[10px] text-danger-ink">{{ form.errors.checklist }}</p>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Настройки по умолчанию</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SelectInput v-model="form.default_type" label="Тип задачи" :options="types" :error="form.errors.default_type" placeholder="Выберите тип" />
                        <SelectInput v-model="form.default_priority" label="Приоритет" :options="priorities" :error="form.errors.default_priority" placeholder="Выберите приоритет" />
                        <SelectInput v-model="form.default_status" label="Статус при создании" :options="statusOptions" :error="form.errors.default_status" placeholder="Выберите статус" />
                        <SelectInput v-model="form.default_assignee_id" label="Исполнитель (необязательно)" :options="assignees.map((a) => ({ value: a.id, label: a.name }))" placeholder="Не назначен" :error="form.errors.default_assignee_id" />
                        <TextInput v-model="form.default_due_days" label="Срок по умолчанию, дней" type="number" :error="form.errors.default_due_days" hint="Можно оставить пустым — срок не обязателен." />
                        <TextInput v-model="form.estimated_minutes" label="Оценка, минут" type="number" :error="form.errors.estimated_minutes" />
                        <SelectInput v-model="form.client_id" label="Доступность" :options="clients.map((c) => ({ value: c.id, label: `Только ${c.name}` }))" placeholder="Общий шаблон для всех" :error="form.errors.client_id" />
                    </div>
                    <div class="mt-4 flex flex-wrap gap-5">
                        <label class="flex items-center gap-2 text-[12px] text-ink-soft">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30" />
                            Шаблон активен
                        </label>
                        <label class="flex items-center gap-2 text-[12px] text-ink-soft">
                            <input v-model="form.is_shared" type="checkbox" class="h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30" />
                            Показывать клиентам
                        </label>
                    </div>
                </section>
            </div>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <section class="surface-card overflow-hidden">
                    <div class="border-b border-line/80 bg-surface-2/50 px-5 py-3">
                        <p class="eyebrow">Предпросмотр</p>
                        <p class="mt-1 text-[12px] font-semibold text-ink">Как это увидит клиент</p>
                    </div>
                    <div class="p-5">
                        <p class="font-mono text-[9px] uppercase tracking-[0.12em] text-faint">{{ form.default_due_days ? `${form.default_due_days} дней` : 'без срока' }} · {{ form.estimated_minutes ? `${Math.round(form.estimated_minutes / 60)} ч` : 'без оценки' }}</p>
                        <h3 class="mt-2 text-[19px] font-bold leading-snug tracking-[-0.02em] text-ink">{{ form.name || 'Название шаблона' }}</h3>
                        <p class="mt-1 text-[11px] text-muted">{{ form.summary || 'Короткое описание появится здесь' }}</p>

                        <div class="prose-kiki mt-4 border-t border-line/70 pt-4" v-html="form.description || '<p class=\'text-faint\'>Описание задачи появится здесь.</p>'"></div>

                        <div class="mt-5 border-t border-line/70 pt-4">
                            <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted">Чек-лист</p>
                            <ol class="mt-2 space-y-1.5">
                                <li v-for="(item, index) in form.checklist.filter((i) => i.text)" :key="index" class="flex items-start gap-2 text-[11px] text-ink-soft">
                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary-400"></span>
                                    {{ item.text }}
                                </li>
                            </ol>
                        </div>
                    </div>
                </section>

                <section class="surface-card mt-4 p-5">
                    <p class="eyebrow">Подсказка</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">
                        Хороший шаблон отвечает на три вопроса: что входит, что нужно от клиента и когда работа считается готовой. Исполнитель и срок — необязательны.
                    </p>
                </section>
            </aside>
        </div>

        <div v-if="showDelete" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]" @click="showDelete = false"></div>
            <div class="relative w-full max-w-[400px] rounded-[24px] border border-line bg-surface p-5 shadow-2xl">
                <h3 class="font-display text-[18px] font-medium tracking-[-0.03em] text-ink">Удалить шаблон?</h3>
                <p class="mt-2 text-[12px] leading-relaxed text-muted">Существующие задачи останутся, но шаблон исчезнет из списка. Действие можно отменить только вручную.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="ghost" size="sm" @click="showDelete = false">Отмена</Button>
                    <Button variant="danger" size="sm" @click="destroy">Удалить</Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
