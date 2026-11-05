<script setup>
import { computed, reactive, ref } from 'vue';
import { CheckIcon, DocumentTextIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import StructuredForm from '@/Components/FormTemplates/StructuredForm.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';

const props = defineProps({
    action: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['confirm', 'reject']);

const form = props.action.form || {};
const template = form.template || {};
const defaults = form.defaults || {};
const clients = form.clients || [];

const values = reactive({
    fields: { ...(form.values?.fields || {}) },
    groups: JSON.parse(JSON.stringify(form.values?.groups || {})),
});

const title = ref(defaults.title || template.name || '');
const priority = ref(defaults.priority || 'medium');
const deadline = ref(defaults.deadline || '');
const clientId = ref(form.client_id || clients[0]?.id || '');

const priorities = [
    { value: 'low', label: 'Низкий' },
    { value: 'medium', label: 'Средний' },
    { value: 'high', label: 'Высокий' },
];

const clientOptions = computed(() =>
    clients.map((client) => ({
        value: client.id,
        label: client.email ? `${client.name} · ${client.email}` : client.name,
    })),
);

const schema = computed(() => form.schema || { fields: [], groups: [] });

function submit() {
    const payload = {
        form_template_id: template.id,
        title: title.value,
        priority: priority.value,
        deadline: deadline.value || null,
        form_data: {
            fields: { ...values.fields },
            groups: JSON.parse(JSON.stringify(values.groups)),
        },
    };

    if (clientOptions.value.length > 0) {
        payload.client_id = clientId.value;
    }

    emit('confirm', payload);
}
</script>

<template>
    <div class="overflow-hidden rounded-[22px] border border-primary-200 bg-gradient-to-b from-primary-50/60 to-surface">
        <header class="flex items-start gap-3 border-b border-primary-100 px-4 py-3.5">
            <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-[11px] bg-primary-100 text-primary-700">
                <DocumentTextIcon class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-primary-700">Форма заявки</p>
                <p class="mt-0.5 text-[13px] font-semibold text-ink">{{ template.name }}</p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-muted">
                    Кики заполнила, что смогла. Проверьте поля и подтвердите — заявка уйдёт в работу.
                </p>
            </div>
        </header>

        <div class="max-h-[52vh] space-y-4 overflow-y-auto px-4 py-4">
            <SelectInput
                v-if="clientOptions.length > 0"
                v-model="clientId"
                label="Клиент"
                :options="clientOptions"
                :error="errors.client_id"
                placeholder="Выберите клиента"
            />

            <TextInput v-model="title" label="Название заявки" :error="errors.title" placeholder="Коротко: что нужно сделать" />

            <StructuredForm :template="{ schema }" :model-value="values" :errors="errors" />

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <SelectInput v-model="priority" label="Приоритет" :options="priorities" :error="errors.priority" />
                <TextInput v-model="deadline" label="Желаемый срок" type="date" :error="errors.deadline" />
            </div>
        </div>

        <footer class="flex flex-wrap items-center gap-2 border-t border-primary-100 bg-white/50 px-4 py-3">
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full bg-ink px-4 py-2 text-[11px] font-semibold text-canvas transition hover:bg-primary-700 disabled:opacity-50"
                :disabled="busy"
                @click="submit"
            >
                <CheckIcon class="h-3.5 w-3.5" />
                Создать заявку
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
            <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.12em] text-faint">
                {{ template.category || 'заявка' }}
            </span>
        </footer>
    </div>
</template>
