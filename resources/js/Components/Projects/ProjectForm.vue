<script setup>
import { computed } from 'vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';

const props = defineProps({
    modelValue: { type: Object, required: true },
    clients: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    priorities: { type: Array, default: () => [] },
    colors: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const clientOptions = computed(() =>
    props.clients.map((client) => ({ value: client.id, label: client.name + (client.email ? ` · ${client.email}` : '') })),
);

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>

<template>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.25fr_.75fr]">
        <section class="surface-card p-5 sm:p-6">
            <p class="eyebrow">Проект</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-[1fr_140px]">
                <TextInput
                    :model-value="modelValue.name"
                    label="Название"
                    placeholder="Редизайн koreatur.ru"
                    :error="errors.name"
                    @update:model-value="update('name', $event)"
                />
                <TextInput
                    :model-value="modelValue.key"
                    label="Ключ"
                    placeholder="KOR"
                    hint="Префикс задач: KOR-1"
                    :error="errors.key"
                    @update:model-value="update('key', $event.toUpperCase())"
                />
            </div>

            <div class="mt-4">
                <TextArea
                    :model-value="modelValue.description"
                    label="Описание"
                    :rows="4"
                    placeholder="Что за проект, цель, рамки…"
                    :error="errors.description"
                    @update:model-value="update('description', $event)"
                />
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <SelectInput
                    :model-value="modelValue.client_id || ''"
                    label="Клиент"
                    :options="clientOptions"
                    placeholder="Без клиента"
                    :error="errors.client_id"
                    @update:model-value="update('client_id', $event || null)"
                />
                <SelectInput
                    :model-value="modelValue.status"
                    label="Статус"
                    :options="statuses"
                    :error="errors.status"
                    @update:model-value="update('status', $event)"
                />
                <SelectInput
                    :model-value="modelValue.priority"
                    label="Приоритет"
                    :options="priorities"
                    :error="errors.priority"
                    @update:model-value="update('priority', $event)"
                />
                <div>
                    <p class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Цвет</p>
                    <div class="flex items-center gap-2">
                        <button
                            v-for="color in colors"
                            :key="color.value"
                            type="button"
                            class="h-8 w-8 rounded-full border-2 transition"
                            :class="[
                                modelValue.color === color.value ? 'border-ink scale-110' : 'border-transparent hover:scale-105',
                                color.value === 'violet' ? 'bg-primary-500' : '',
                                color.value === 'sage' ? 'bg-sage' : '',
                                color.value === 'sky' ? 'bg-sky' : '',
                                color.value === 'peach' ? 'bg-peach' : '',
                                color.value === 'ink' ? 'bg-ink' : '',
                            ]"
                            :title="color.label"
                            @click="update('color', color.value)"
                        ></button>
                    </div>
                    <p v-if="errors.color" class="mt-1 text-[10px] text-danger-ink">{{ errors.color }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <TextInput
                    :model-value="modelValue.starts_at || ''"
                    label="Старт"
                    type="date"
                    :error="errors.starts_at"
                    @update:model-value="update('starts_at', $event || null)"
                />
                <TextInput
                    :model-value="modelValue.due_at || ''"
                    label="Дедлайн"
                    type="date"
                    :error="errors.due_at"
                    @update:model-value="update('due_at', $event || null)"
                />
            </div>

            <div class="mt-4">
                <TextInput
                    :model-value="modelValue.site_url || ''"
                    label="Сайт проекта"
                    placeholder="https://example.com"
                    hint="Кики изучит его и соберёт AGENTS.md"
                    :error="errors.site_url"
                    @update:model-value="update('site_url', $event || null)"
                />
            </div>

            <div class="mt-4">
                <TextArea
                    :model-value="modelValue.instructions || ''"
                    label="Постоянные указания"
                    :rows="3"
                    placeholder="Например: не менять цены без согласования, общаться с клиентом на «вы»…"
                    :error="errors.instructions"
                    @update:model-value="update('instructions', $event || null)"
                />
            </div>
        </section>

        <aside class="space-y-4">
            <section class="surface-card p-5">
                <p class="text-[12px] font-bold text-ink">Зачем проект</p>
                <ul class="mt-2 space-y-2 text-[11px] leading-relaxed text-muted">
                    <li>· задачи получают общий префикс <span class="font-mono text-ink">{{ modelValue.key || 'KOR' }}-1</span>;</li>
                    <li>· прогресс и дедлайн видны на одном экране;</li>
                    <li>· AGENTS.md и сайт наследуются всеми задачами;</li>
                    <li>· Кики отвечает в контексте проекта, а не «вообще».</li>
                </ul>
            </section>

            <section class="surface-card p-5">
                <p class="eyebrow">Ключ</p>
                <p class="mt-2 text-[11px] leading-relaxed text-muted">
                    Короткий латинский префикс. Он попадёт в каждую задачу проекта и в поиск. Менять можно, но ссылки
                    в задачах останутся читаемыми.
                </p>
                <p class="mt-3 font-mono text-[20px] tracking-[0.08em] text-ink">{{ modelValue.key || 'PRJ' }}-12</p>
            </section>
        </aside>
    </div>
</template>
