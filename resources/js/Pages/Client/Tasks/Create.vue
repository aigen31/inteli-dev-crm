<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import RichTextEditor from '@/Components/Form/RichTextEditor.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import CustomFieldsForm from '@/Components/CustomFields/CustomFieldsForm.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { ArrowLeftIcon, CheckIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { useAssistant } from '@/Composables/useAssistant';

const { open } = useAssistant();

const props = defineProps({
    priorities: Array,
    templates: Array,
    fields: Array,
});

const selected = ref(null);
const mode = ref('template');

const form = useForm({
    template_id: '',
    title: '',
    description: '',
    priority: 'medium',
    deadline: '',
    custom_fields: {},
});

const checklist = computed(() => selected.value?.checklist || []);

function choose(template) {
    selected.value = template;
    mode.value = 'form';
    form.template_id = template.id;
    form.title = template.name;
    form.description = template.description || '';
    form.priority = template.default_priority || 'medium';
    form.clearErrors();
}

function startBlank() {
    selected.value = null;
    mode.value = 'form';
    form.template_id = '';
    form.title = '';
    form.description = '';
    form.priority = 'medium';
    form.clearErrors();
}

function back() {
    mode.value = 'template';
    selected.value = null;
    form.reset();
}

function submit() {
    form.post(route('client.tasks.store'));
}

onMounted(() => {
    const id = new URLSearchParams(window.location.search).get('template');
    const template = props.templates.find((item) => item.id === id);
    if (template) choose(template);
});
</script>

<template>
    <ClientLayout :title="mode === 'form' ? 'Новая заявка' : 'Создать заявку'">
        <template #actions>
            <Button v-if="mode === 'form'" variant="ghost" size="sm" @click="back">
                <ArrowLeftIcon class="h-4 w-4" />
                К шаблонам
            </Button>
        </template>

        <template v-if="mode === 'template'">
            <section class="surface-card mb-5 flex flex-col items-start gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
                <KikiAvatar :size="60" class="kiki-ring" />
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Быстрее, чем письмо</p>
                    <h2 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em] text-ink">Выберите сценарий</h2>
                    <p class="mt-1 text-[12px] leading-relaxed text-muted">Шаблон уже содержит структуру и чек-лист. Вы только уточняете детали.</p>
                </div>
                <Button variant="secondary" size="sm" @click="startBlank">Своими словами</Button>
            </section>

            <div v-if="templates.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="template in templates"
                    :key="template.id"
                    type="button"
                    class="surface-card surface-card-hover group p-5 text-left"
                    @click="choose(template)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="rounded-full bg-primary-soft px-2.5 py-1 text-[9px] font-semibold text-primary-800">{{ template.default_due_days ? `${template.default_due_days} дн.` : 'срок обсуждается' }}</span>
                        <CheckIcon class="h-4 w-4 text-primary-500 opacity-0 transition group-hover:opacity-100" />
                    </div>
                    <h3 class="mt-3 text-[14px] font-bold text-ink">{{ template.name }}</h3>
                    <p class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ template.summary || 'Готовый сценарий заявки' }}</p>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span v-for="item in (template.checklist || []).slice(0, 3)" :key="item.text" class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-ink-soft">{{ item.text }}</span>
                        <span v-if="(template.checklist || []).length > 3" class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-muted">+{{ template.checklist.length - 3 }}</span>
                    </div>
                </button>
            </div>

            <div v-else class="surface-card">
                <div class="flex flex-col items-center px-6 py-12 text-center">
                    <KikiAvatar :size="100" variant="flight" class="kiki-ring" />
                    <h3 class="mt-4 font-display text-[19px] font-medium text-ink">Шаблонов пока нет</h3>
                    <p class="mt-2 max-w-[360px] text-[12px] text-muted">Опишите заявку своими словами — владелец увидит её и поможет.</p>
                    <Button class="mt-4" size="sm" @click="startBlank">Создать заявку</Button>
                </div>
            </div>
        </template>

        <template v-else>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.4fr_.7fr]">
                <div class="space-y-5">
                    <section v-if="selected" class="surface-card p-5">
                        <div class="flex items-start gap-3">
                            <KikiAvatar :size="40" />
                            <div>
                                <p class="text-[11px] font-semibold text-ink">Шаблон: {{ selected.name }}</p>
                                <p class="mt-1 text-[10px] leading-relaxed text-muted">Описание и чек-лист уже внутри. Можно дополнить своими словами.</p>
                            </div>
                        </div>
                        <div v-if="checklist.length" class="mt-3 flex flex-wrap gap-1.5">
                            <span v-for="item in checklist" :key="item.text" class="rounded-full bg-surface-2 px-2 py-1 text-[9px] text-ink-soft">{{ item.text }}</span>
                        </div>
                    </section>

                    <section class="surface-card p-5 sm:p-6">
                        <p class="eyebrow">Заявка</p>
                        <div class="mt-4 space-y-4">
                            <TextInput v-model="form.title" label="Название" :error="form.errors.title" placeholder="Коротко: что нужно сделать" />
                            <div>
                                <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">Описание</p>
                                <RichTextEditor v-model="form.description" placeholder="Что важно учесть, какие материалы есть, какой результат нужен…" />
                                <p v-if="form.errors.description" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.description }}</p>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <SelectInput v-model="form.priority" label="Приоритет" :options="priorities" placeholder="Выберите приоритет" />
                                <TextInput v-model="form.deadline" label="Желаемый срок" type="date" :error="form.errors.deadline" />
                            </div>
                            <CustomFieldsForm v-if="fields.length" v-model="form.custom_fields" :fields="fields" :errors="form.errors" />
                            <div class="flex justify-end gap-2 pt-2">
                                <Button variant="ghost" size="sm" @click="back">Отмена</Button>
                                <Button size="sm" :disabled="form.processing" @click="submit">Отправить заявку</Button>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <section class="surface-card p-5">
                        <p class="eyebrow">Что дальше</p>
                        <ol class="mt-3 space-y-3 text-[11px] leading-relaxed text-muted">
                            <li><span class="font-semibold text-ink">1.</span> Заявка попадает владельцу.</li>
                            <li><span class="font-semibold text-ink">2.</span> Он уточняет детали и берёт в работу.</li>
                            <li><span class="font-semibold text-ink">3.</span> Вы видите статус и можете оставить комментарий.</li>
                        </ol>
                    </section>
                    <button type="button" class="surface-card flex w-full items-center gap-3 p-4 text-left" @click="open">
                        <SparklesIcon class="h-4 w-4 text-primary-600" />
                        <span class="text-[11px] font-semibold text-ink">Помочь сформулировать?</span>
                        <span class="ml-auto text-[10px] text-muted">Кики</span>
                    </button>
                </aside>
            </div>
        </template>
    </ClientLayout>
</template>
