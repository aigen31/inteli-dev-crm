<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { ArrowLeftIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
});

const form = useForm({
    brief_template_id: props.templates[0]?.id || '',
    project_id: '',
    title: '',
});

const selected = computed(() => props.templates.find((template) => template.id === form.brief_template_id) || null);

function submit() {
    form.post(route('client.briefs.store'));
}
</script>

<template>
    <ClientLayout title="Новый бриф">
        <template #actions>
            <Link :href="route('client.briefs.index')">
                <Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />К списку</Button>
            </Link>
            <Button size="sm" :disabled="form.processing || ! selected" @click="submit">Создать бриф</Button>
        </template>

        <p class="max-w-[640px] text-[12px] leading-relaxed text-muted">
            Выберите шаблон — он задаст секции и вопросы. Название придумайте по теме, чтобы потом было легко найти.
        </p>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1.3fr_.7fr]">
            <section class="surface-card p-5">
                <p class="eyebrow">Шаблон</p>
                <div class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2">
                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        :class="[
                            'flex items-start gap-3 rounded-[14px] border p-3 text-left transition',
                            form.brief_template_id === template.id
                                ? 'border-primary-400 bg-primary-soft/60'
                                : 'border-line bg-surface/70 hover:border-primary-300',
                        ]"
                        :aria-pressed="form.brief_template_id === template.id"
                        @click="form.brief_template_id = template.id"
                    >
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-surface-2 text-primary-700">
                            <ClipboardDocumentListIcon class="h-4 w-4" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-[12px] font-semibold text-ink">{{ template.title }}</span>
                            <span class="mt-0.5 block text-[10px] leading-relaxed text-muted">{{ template.summary }}</span>
                            <span class="mt-1 block font-mono text-[9px] text-faint">{{ template.section_count }} секций · {{ template.field_count }} полей</span>
                        </span>
                    </button>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="surface-card space-y-3 p-5">
                    <TextInput v-model="form.title" label="Название брифа" :error="form.errors.title" placeholder="Например: Сайт для кофейни" />
                    <SelectInput
                        v-model="form.project_id"
                        label="Проект (необязательно)"
                        :options="projects.map((project) => ({ value: project.id, label: `${project.key} · ${project.name}` }))"
                        :error="form.errors.project_id"
                    />
                    <p class="text-[10px] leading-relaxed text-faint">Бриф появится в вашем кабинете. Заполнять можно частями — ответы сохраняются автоматически.</p>
                </section>
            </aside>
        </div>
    </ClientLayout>
</template>
