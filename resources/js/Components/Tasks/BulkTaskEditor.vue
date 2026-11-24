<script setup>
import { computed, reactive, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Button from '@/Components/UI/Button.vue';
import TaskDateCalendar from '@/Components/Form/TaskDateCalendar.vue';
import { XMarkIcon, AdjustmentsHorizontalIcon, ArrowPathIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import { validationMessage } from '@/Utils/validation';

const props = defineProps({
    endpoint: { type: String, required: true },
    selectedTasks: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    assignees: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['done', 'clear']);
const form = useForm({ task_ids: [] });

const enabled = reactive({
    project_id: false, client_id: false, assignee_id: false,
    type: false, priority: false, status: false,
    starts_at: false, deadline: false, estimated_minutes: false, budget: false,
});
const values = reactive({
    project_id: '', client_id: '', assignee_id: '',
    type: '', priority: '', status: '',
    starts_at: '', deadline: '', estimated_minutes: '', budget: '',
});

const groups = computed(() => [
    {
        title: 'Организация',
        hint: 'Куда и кому относится работа',
        fields: [
            { key: 'project_id', label: 'Проект', type: 'select', options: props.projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name })), empty: 'Без проекта' },
            { key: 'client_id', label: 'Клиент', type: 'select', options: props.clients.map((c) => ({ value: c.id, label: c.name })), empty: 'Без клиента' },
            { key: 'assignee_id', label: 'Исполнитель', type: 'select', options: props.assignees.map((u) => ({ value: u.id, label: u.name })), empty: 'Не назначен' },
            { key: 'type', label: 'Тип', type: 'select', options: props.types, empty: 'Не менять' },
        ],
    },
    {
        title: 'План',
        hint: 'Сроки, приоритет и статус',
        fields: [
            { key: 'status', label: 'Статус', type: 'select', options: props.statuses, empty: 'Не менять' },
            { key: 'priority', label: 'Приоритет', type: 'select', options: props.priorities, empty: 'Не менять' },
            { key: 'starts_at', label: 'Старт работ', type: 'date' },
            { key: 'deadline', label: 'Дедлайн', type: 'date' },
        ],
    },
    {
        title: 'Ресурсы',
        hint: 'Оценка и бюджет',
        fields: [
            { key: 'estimated_minutes', label: 'Оценка, минут', type: 'number', placeholder: 'Например, 120' },
            { key: 'budget', label: 'Бюджет, ₽', type: 'number', placeholder: 'Например, 5000' },
        ],
    },
]);

const chosen = computed(() => Object.values(enabled).filter(Boolean).length);
const hasFields = computed(() => chosen.value > 0);
const bulkAssigneeId = computed(() => {
    if (enabled.assignee_id && values.assignee_id) return values.assignee_id;
    const ids = [...new Set(props.selectedTasks.map((task) => task.assignee_id).filter(Boolean))];
    return ids.length === 1 ? ids[0] : null;
});
const fieldErrors = computed(() => Object.entries(form.errors).filter(([key]) => enabled[key] || key === 'fields'));

watch(() => props.selectedTasks, (tasks) => {
    form.task_ids = tasks.map((task) => task.id);
}, { immediate: true, deep: true });

function onToggle(field) {
    if (!enabled[field.key]) values[field.key] = '';
}

function selectField(field) {
    enabled[field.key] = !enabled[field.key];
    if (!enabled[field.key]) values[field.key] = '';
}

function submit() {
    if (!hasFields.value) return;
    const payload = { task_ids: props.selectedTasks.map((task) => task.id) };
    for (const [key, active] of Object.entries(enabled)) {
        if (active) payload[key] = values[key] === '' ? null : values[key];
    }
    form.transform(() => payload);
    form.patch(props.endpoint, {
        preserveScroll: true,
        onSuccess: () => {
            emit('done');
            reset();
        },
    });
}

function reset() {
    for (const key of Object.keys(enabled)) {
        enabled[key] = false;
        values[key] = '';
    }
    form.clearErrors();
    form.transform((data) => data);
}
</script>

<template>
    <Teleport to="body">
        <template v-if="open">
        <transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div class="fixed inset-0 z-[60] flex justify-end bg-black/30 backdrop-blur-[2px]" @click.self="emit('clear')">
                <transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-x-6 opacity-0" enter-to-class="translate-x-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-x-0 opacity-100" leave-to-class="translate-x-6 opacity-0">
                    <section class="flex h-full w-full max-w-[440px] flex-col border-l border-line bg-surface shadow-[-24px_0_70px_rgba(16,13,24,.16)]" role="dialog" aria-modal="true" aria-label="Пакетное редактирование задач">
                        <header class="relative overflow-hidden border-b border-line bg-surface-2/70 px-5 pb-5 pt-5 text-ink">
                            <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-primary-400/25 blur-2xl"></div>
                            <div class="relative flex items-start gap-3">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[13px] bg-primary-soft text-primary-700"><AdjustmentsHorizontalIcon class="h-5 w-5" /></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-[8px] uppercase tracking-[0.16em] text-muted">Пакетное изменение</p>
                                    <h2 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em]">Настроить задачи</h2>
                                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Выбрано {{ selectedTasks.length }} · название и описание не меняются</p>
                                </div>
                                <button type="button" class="rounded-full p-1.5 text-muted transition hover:bg-ink/[0.06] hover:text-ink" aria-label="Закрыть" @click="emit('clear')"><XMarkIcon class="h-4 w-4" /></button>
                            </div>
                        </header>

                        <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
                            <div class="space-y-4">
                                <section v-for="group in groups" :key="group.title" class="rounded-[18px] border border-line/75 bg-white/40 p-3">
                                    <div class="flex items-baseline justify-between gap-2 px-1">
                                        <h3 class="text-[11px] font-bold text-ink">{{ group.title }}</h3>
                                        <span class="text-[9px] text-faint">{{ group.hint }}</span>
                                    </div>
                                    <div class="mt-2 space-y-1.5">
                                        <div v-for="field in group.fields" :key="field.key" :class="['rounded-[12px] border px-2.5 py-2 transition', enabled[field.key] ? 'border-primary-200 bg-primary-50/40' : 'border-transparent']">
                                            <div class="flex items-center gap-2">
                                                <input :id="`bulk-${field.key}`" v-model="enabled[field.key]" type="checkbox" class="h-3.5 w-3.5 rounded border-line-strong text-primary-600 focus:ring-primary-500/30" @change="onToggle(field)" />
                                                <label :for="`bulk-${field.key}`" class="min-w-0 flex-1 cursor-pointer text-[10px] font-semibold text-ink-soft">{{ field.label }}</label>
                                                <span v-if="enabled[field.key]" class="font-mono text-[8px] uppercase tracking-[0.08em] text-primary-600">изменить</span>
                                            </div>
                                            <div v-if="enabled[field.key]" class="mt-1.5">
                                                <select v-if="field.type === 'select'" v-model="values[field.key]" class="h-8 w-full rounded-[9px] border border-line bg-white/70 px-2 text-[10px] text-ink focus:border-primary-400 focus:outline-none">
                                                    <option value="">{{ field.empty || '—' }}</option>
                                                    <option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                                                </select>
                                                <TaskDateCalendar v-else-if="field.type === 'date'" v-model="values[field.key]" label="" :aria-label="field.label" :assignee-id="bulkAssigneeId" :error="form.errors[field.key]" />
                                                <input v-else v-model="values[field.key]" :type="field.type" :placeholder="field.placeholder" :min="field.key === 'estimated_minutes' || field.key === 'budget' ? 0 : undefined" :step="field.key === 'budget' ? '0.01' : undefined" class="h-8 w-full rounded-[9px] border border-line bg-white/70 px-2 text-[10px] text-ink focus:border-primary-400 focus:outline-none" />
                                            </div>
                                        </div>
                                    </div>
                                </section>
                                <p v-if="!hasFields" class="rounded-[13px] border border-dashed border-line px-3 py-2.5 text-[10px] leading-relaxed text-muted">Отметьте поля, которые нужно изменить. Остальные данные останутся как есть.</p>
                                <p v-if="form.errors.fields" class="text-[10px] text-danger-ink">{{ validationMessage(form.errors.fields) }}</p>
                                <div v-for="[key, message] in fieldErrors" :key="key" class="text-[10px] text-danger-ink"><template v-if="key !== 'fields'">{{ key }}: </template>{{ validationMessage(message) }}</div>
                                <p v-if="enabled.status" class="rounded-[12px] border border-warning/20 bg-warning/10 px-3 py-2 text-[9px] leading-relaxed text-warning">Если задача не может перейти в выбранный статус, она останется без изменения статуса. Остальные поля применятся.</p>
                            </div>
                        </div>

                        <footer class="border-t border-line/80 bg-surface px-4 py-3">
                            <div class="flex items-center justify-between gap-2">
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-[10px] px-2 py-2 text-[10px] font-semibold text-muted transition hover:bg-ink/[0.05] hover:text-ink" @click="reset"><ArrowPathIcon class="h-3.5 w-3.5" />Сбросить поля</button>
                                <div class="flex items-center gap-2">
                                    <button type="button" class="rounded-[10px] px-3 py-2 text-[10px] font-semibold text-muted hover:text-ink" @click="emit('clear')">Отмена</button>
                                    <Button :disabled="form.processing || !hasFields" @click="submit"><CheckCircleIcon class="h-3.5 w-3.5" />{{ form.processing ? 'Применяю…' : `Применить · ${chosen}` }}</Button>
                                </div>
                            </div>
                        </footer>
                    </section>
                </transition>
            </div>
        </transition>
        </template>
    </Teleport>
</template>
