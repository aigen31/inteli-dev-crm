<script setup>
import { computed, reactive, ref } from 'vue';
import { CheckIcon, PlusIcon, SparklesIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import SelectInput from '@/Components/Form/SelectInput.vue';

const props = defineProps({
    action: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['confirm', 'reject']);

const plan = props.action.plan || {};
const clients = plan.clients || [];
const projectOptions = plan.projects || [];
const questions = plan.questions || [];

const summary = ref(plan.summary || '');
const clientId = ref(plan.client_id || clients[0]?.id || '');
const projectId = ref(plan.project_id || '');
const tasks = reactive(
    (plan.tasks || []).map((task) => ({
        title: task.title || '',
        description: task.description || '',
        checklist: Array.isArray(task.checklist) ? [...task.checklist] : [],
        priority: task.priority || 'medium',
        due_days: task.due_days ?? null,
        template_id: task.template_id || null,
        expanded: false,
    })),
);

const priorities = [
    { value: 'low', label: 'Низкий' },
    { value: 'medium', label: 'Средний' },
    { value: 'high', label: 'Высокий' },
];

const projects = computed(() =>
    projectOptions.map((project) => ({
        value: project.id,
        label: project.key + ' · ' + project.name,
    })),
);

const clientOptions = computed(() =>
    clients.map((client) => ({
        value: client.id,
        label: client.email ? `${client.name} · ${client.email}` : client.name,
    })),
);

const priorityLabels = { low: 'Низкий', medium: 'Средний', high: 'Высокий' };

const priorityClasses = {
    low: 'border-line bg-white/70 text-muted',
    medium: 'border-sky/40 bg-sky/10 text-ink-soft',
    high: 'border-warning/30 bg-warning/10 text-warning',
};

function cyclePriority(task) {
    const order = ['low', 'medium', 'high'];
    const index = order.indexOf(task.priority);
    task.priority = order[(index + 1) % order.length];
}

const approachLabel = computed(
    () =>
        ({
            form: 'по готовой форме',
            template: 'по шаблону',
            free: 'свободный план',
        })[plan.approach] || 'план',
);

const total = computed(() => tasks.length);

function addTask() {
    tasks.push({
        title: '',
        description: '',
        checklist: [],
        priority: 'medium',
        due_days: null,
        template_id: null,
        expanded: true,
    });
}

function removeTask(index) {
    tasks.splice(index, 1);
}

function submit() {
    const payload = {
        summary: summary.value,
        approach: plan.approach || 'free',
        source_document_id: plan.source_document_id || null,
        questions,
        tasks: tasks.map((task) => ({
            title: task.title.trim(),
            description: task.description.trim(),
            checklist: task.checklist,
            priority: task.priority,
            due_days: task.due_days,
            template_id: task.template_id,
        })),
    };

    if (clientOptions.value.length > 0) {
        payload.client_id = clientId.value;
    }

    if (projectId.value) {
        payload.project_id = projectId.value;
    }

    emit('confirm', payload);
}
</script>

<template>
    <div class="overflow-hidden rounded-[24px] border border-ink/10 bg-gradient-to-b from-white to-surface shadow-[0_18px_40px_rgba(28,24,35,0.08)]">
        <header class="relative overflow-hidden border-b border-line/80 px-4 py-4">
            <div class="absolute inset-0 bg-[radial-gradient(120%_120%_at_100%_0%,rgba(143,184,154,0.22),transparent_60%)]"></div>
            <div class="relative flex items-start gap-3">
                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[12px] bg-ink text-canvas">
                    <SparklesIcon class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-muted">План задач</p>
                        <span class="rounded-full border border-line bg-white/70 px-2 py-[1px] font-mono text-[8px] uppercase tracking-[0.1em] text-ink-soft">
                            {{ approachLabel }}
                        </span>
                    </div>
                    <p class="mt-1 text-[13px] font-semibold leading-snug text-ink">
                        {{ summary || 'Задачи из документа' }}
                    </p>
                    <p class="mt-1 text-[11px] leading-relaxed text-muted">
                        Проверьте формулировки и приоритеты. Каждая карточка станет отдельной задачей.
                    </p>
                </div>
                <span class="shrink-0 rounded-full bg-ink px-2.5 py-1 font-mono text-[10px] text-canvas">{{ total }}</span>
            </div>

            <div v-if="questions.length" class="relative mt-3 rounded-[14px] border border-warning/25 bg-warning/[0.07] px-3 py-2.5">
                <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-warning">Требует уточнения</p>
                <ul class="mt-1 space-y-0.5 text-[11px] leading-relaxed text-ink-soft">
                    <li v-for="(question, index) in questions" :key="index">— {{ question }}</li>
                </ul>
            </div>
        </header>

        <div class="max-h-[54vh] overflow-y-auto px-3 py-3">
            <SelectInput
                v-if="projects.length"
                v-model="projectId"
                label="Проект"
                :options="projects"
                placeholder="Без проекта"
                :error="errors.project_id"
                class="mb-3"
            />

            <SelectInput
                v-if="clientOptions.length > 0"
                v-model="clientId"
                label="Клиент"
                :options="clientOptions"
                :error="errors.client_id"
                placeholder="Выберите клиента"
                class="mb-3"
            />

            <div class="relative space-y-2 pl-[26px]">
                <span class="absolute bottom-4 left-[9px] top-2 w-px bg-line"></span>

                <div
                    v-for="(task, index) in tasks"
                    :key="index"
                    class="relative rounded-[16px] border border-line bg-white/70 p-3 transition focus-within:border-primary-300"
                >
                    <span class="absolute -left-[26px] top-3 flex h-[19px] w-[19px] items-center justify-center rounded-full border border-line bg-surface font-mono text-[9px] text-muted">
                        {{ String(index + 1).padStart(2, '0') }}
                    </span>

                    <div class="flex items-start gap-2">
                        <input
                            v-model="task.title"
                            type="text"
                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-[12.5px] font-semibold text-ink placeholder-faint focus:outline-none focus:ring-0"
                            placeholder="Что нужно сделать"
                        />
                        <button
                            type="button"
                            class="shrink-0 rounded-md p-1 text-faint transition hover:text-danger"
                            title="Убрать задачу"
                            @click="removeTask(index)"
                        >
                            <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <p v-if="errors[`tasks.${index}.title`]" class="mt-1 text-[10px] text-danger-ink">{{ errors[`tasks.${index}.title`] }}</p>

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-full border px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.06em] transition hover:opacity-80"
                            :class="priorityClasses[task.priority] || priorityClasses.medium"
                            :title="'Приоритет: ' + (priorityLabels[task.priority] || 'средний') + ' — нажмите, чтобы изменить'"
                            @click="cyclePriority(task)"
                        >
                            {{ priorityLabels[task.priority] || 'Средний' }}
                        </button>

                        <button
                            v-if="task.checklist.length"
                            type="button"
                            class="rounded-full border border-line bg-surface-2/60 px-2 py-1 font-mono text-[9px] text-muted transition hover:text-ink"
                            @click="task.expanded = !task.expanded"
                        >
                            чек-лист {{ task.checklist.length }}
                        </button>

                        <button
                            type="button"
                            class="ml-auto rounded-full px-1.5 py-1 text-[10px] font-medium text-faint transition hover:text-primary-700"
                            @click="task.expanded = !task.expanded"
                        >
                            {{ task.expanded ? 'свернуть' : 'описание' }}
                        </button>
                    </div>

                    <div v-if="task.expanded" class="mt-2 space-y-2">
                        <textarea
                            v-model="task.description"
                            rows="3"
                            class="w-full rounded-[12px] border border-line bg-white/80 px-3 py-2 text-[11px] leading-relaxed text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                            placeholder="Детали задачи: что входит, что не входит, критерий готовности"
                        ></textarea>

                        <ul v-if="task.checklist.length" class="space-y-1 rounded-[12px] bg-surface-2/50 px-3 py-2">
                            <li v-for="(item, itemIndex) in task.checklist" :key="itemIndex" class="flex items-start gap-1.5 text-[10.5px] text-ink-soft">
                                <span class="mt-[3px] h-1 w-1 shrink-0 rounded-full bg-muted"></span>
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>

                <button
                    type="button"
                    class="relative flex w-full items-center justify-center gap-1.5 rounded-[14px] border border-dashed border-line py-2 text-[10px] font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-50/40"
                    @click="addTask"
                >
                    <PlusIcon class="h-3.5 w-3.5" />
                    Добавить задачу
                </button>
            </div>
        </div>

        <footer class="flex flex-wrap items-center gap-2 border-t border-line/80 bg-white/50 px-4 py-3">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full bg-ink px-4 py-2 text-[11px] font-semibold text-canvas transition hover:bg-primary-700 disabled:opacity-50"
                :disabled="busy || tasks.length === 0"
                @click="submit"
            >
                <CheckIcon class="h-3.5 w-3.5" />
                Создать {{ tasks.length }} {{ tasks.length === 1 ? 'задачу' : 'задач' }}
            </button>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border border-line px-3 py-2 text-[11px] font-medium text-muted transition hover:text-ink disabled:opacity-50"
                :disabled="busy"
                @click="emit('reject')"
            >
                <XMarkIcon class="h-3.5 w-3.5" />
                Отмена
            </button>
            <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.12em] text-faint">Кики · план</span>
        </footer>
    </div>
</template>
