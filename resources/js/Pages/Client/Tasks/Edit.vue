<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TaskDateCalendar from '@/Components/Form/TaskDateCalendar.vue';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import { ArrowLeftIcon, ArrowPathIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    task: { type: Object, required: true },
    projects: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },

});

const form = useForm({
    title: props.task.title || '',
    description: props.task.description || '',
    project_id: props.task.project_id || '',
    priority: props.task.priority || 'medium',
    starts_at: props.task.starts_at || '',
    deadline: props.task.deadline || '',
    form_data: props.task.form_data || { fields: {}, groups: {} },
});

const hasTemplate = computed(() => Boolean(props.task.form_template));

function submit() {
    form.put(route('client.tasks.update', props.task.id));
}
</script>

<template>
    <ClientLayout :title="task.title" eyebrow="Редактирование">
        <div class="mb-5 flex flex-wrap items-center gap-3">
            <Link :href="route('client.tasks.show', task.id)" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                <ArrowLeftIcon class="h-3.5 w-3.5" />
                К задаче
            </Link>
            <span class="ml-auto inline-flex items-center gap-1.5 rounded-full border border-warning/30 bg-warning/[0.07] px-3 py-1.5 text-[10px] font-semibold text-warning">
                <ArrowPathIcon class="h-3.5 w-3.5" />
                После сохранения — «На рассмотрении»
            </span>
        </div>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_330px]">
            <div class="space-y-5">
                <section class="surface-card p-5 sm:p-6">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-[11px] bg-primary-soft text-primary-700"><PencilSquareIcon class="h-4 w-4" /></span>
                        <div><p class="eyebrow">Содержание</p><h2 class="mt-0.5 text-[14px] font-bold text-ink">Что уточнить?</h2></div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2"><TextInput v-model="form.title" label="Название" :error="form.errors.title" /></div>
                        <SelectInput v-model="form.project_id" label="Проект" :options="projects.map((p) => ({ value: p.id, label: p.key + ' · ' + p.name }))" placeholder="Без проекта" :error="form.errors.project_id" />
                        <template v-if="! hasTemplate">
                            <SelectInput v-model="form.priority" label="Приоритет" :options="priorities" :error="form.errors.priority" />
                            <TaskDateCalendar v-model="form.starts_at" label="Старт работ" :task-id="task.id" :error="form.errors.starts_at" />
                            <TaskDateCalendar v-model="form.deadline" label="Дедлайн" :task-id="task.id" :error="form.errors.deadline" />
                        </template>
                    </div>
                </section>

                <section v-if="hasTemplate" class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Заявка из шаблона</p>
                    <h2 class="mt-1 text-[14px] font-bold text-ink">{{ task.form_template.name }}</h2>
                    <StructuredForm class="mt-5" :template="task.form_template" :model-value="form.form_data" :errors="form.errors" />
                </section>

                <section v-else class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Описание</p>
                    <TextArea v-model="form.description" class="mt-3" :rows="7" placeholder="Что нужно сделать, что уже пробовали, какой результат нужен…" :error="form.errors.description" />
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card p-5">
                    <p class="eyebrow">Сохранение</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">Изменения увидит владелец. Задача снова попадёт в очередь на рассмотрение, даже если уже была в работе.</p>
                    <Button class="mt-4 w-full" :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Сохраняю…' : 'Сохранить изменения' }}
                    </Button>
                    <Link :href="route('client.tasks.show', task.id)" class="mt-2 block text-center text-[10px] font-semibold text-muted hover:text-ink">Отменить</Link>
                </section>
                <p class="rounded-[16px] border border-line/70 bg-white/35 px-4 py-3 text-[9px] leading-relaxed text-faint">Статус сбросится на «На рассмотрении» автоматически. История задачи сохранится.</p>
            </aside>
        </div>
    </ClientLayout>
</template>
