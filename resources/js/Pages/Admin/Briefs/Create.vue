<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import { ArrowLeftIcon, CheckCircleIcon, ClipboardDocumentListIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    projects: { type: Array, default: () => [] },
});

const selectedId = ref(props.templates[0]?.id || null);
const selected = computed(() => props.templates.find((template) => template.id === selectedId.value) || null);

const form = useForm({
    brief_template_id: selectedId.value,
    client_id: '',
    project_id: '',
    title: '',
});

const projectOptions = computed(() => {
    if (!form.client_id) return props.projects;
    return props.projects.filter((project) => !project.client_id || project.client_id === form.client_id);
});

function choose(template) {
    selectedId.value = template.id;
    form.brief_template_id = template.id;
}

function submit() {
    form.post(route('admin.briefs.store'));
}
</script>

<template>
    <AdminLayout title="Новый бриф">
        <template #actions>
            <Link :href="route('admin.briefs.index')">
                <Button variant="secondary" size="sm"><ArrowLeftIcon class="h-4 w-4" />К списку</Button>
            </Link>
        </template>

        <div class="mb-5">
            <p class="eyebrow">Шаблон → клиент → отправка</p>
            <p class="mt-2 max-w-[720px] text-[12px] leading-relaxed text-muted">
                Выберите шаблон, назначьте клиента и проект. Бриф появится в кабинете клиента, ответы придут задачей на разбор.
            </p>
        </div>

        <form class="grid grid-cols-1 gap-5 lg:grid-cols-[1.15fr_.85fr]" @submit.prevent="submit">
            <div class="min-w-0 space-y-3">
                <button
                    v-for="template in templates"
                    :key="template.id"
                    type="button"
                    :class="[
                        'surface-card w-full p-4 text-left transition',
                        selectedId === template.id ? 'border-primary-500/60 ring-2 ring-primary-500/20' : 'hover:border-line-strong',
                    ]"
                    @click="choose(template)"
                >
                    <div class="flex items-start gap-3">
                        <span
                            :class="[
                                'grid h-10 w-10 shrink-0 place-items-center rounded-[12px]',
                                selectedId === template.id ? 'bg-primary-500 text-white' : 'bg-surface-2 text-primary-700',
                            ]"
                        >
                            <CheckCircleIcon v-if="selectedId === template.id" class="h-5 w-5" />
                            <ClipboardDocumentListIcon v-else class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="text-[13px] font-bold text-ink">{{ template.title }}</span>
                                <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-[9px] text-muted">
                                    {{ template.section_count }} секций · {{ template.field_count }} полей
                                </span>
                                <span v-if="template.estimated_minutes" class="font-mono text-[9px] text-faint">~{{ template.estimated_minutes }} мин</span>
                            </span>
                            <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ template.summary }}</span>
                        </span>
                    </div>
                </button>

                <p v-if="!templates.length" class="surface-card p-5 text-[12px] text-muted">
                    Шаблонов нет. Загрузите их командой <code class="font-mono text-[11px]">php artisan briefs:seed</code>.
                </p>
            </div>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <section class="surface-card p-5">
                    <p class="eyebrow">Параметры брифа</p>

                    <label class="mt-4 block">
                        <span class="text-[11px] font-semibold text-ink-soft">Название</span>
                        <input
                            v-model="form.title"
                            type="text"
                            :placeholder="selected ? `${selected.title} — имя клиента` : 'Например: SEO-аудит — ООО Ромашка'"
                            class="mt-1.5 h-10 w-full rounded-[13px] border border-line bg-white/70 px-3 text-[12px] text-ink placeholder:text-faint focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        >
                        <span class="mt-1 block text-[10px] text-faint">Пустое поле — подставим название шаблона.</span>
                    </label>

                    <label class="mt-3 block">
                        <span class="text-[11px] font-semibold text-ink-soft">Клиент</span>
                        <select
                            v-model="form.client_id"
                            class="mt-1.5 h-10 w-full rounded-[13px] border border-line bg-white/70 px-3 text-[12px] text-ink focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        >
                            <option value="">Не назначать</option>
                            <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
                        </select>
                        <span v-if="form.errors.client_id" class="mt-1 block text-[10px] text-danger">{{ form.errors.client_id }}</span>
                    </label>

                    <label class="mt-3 block">
                        <span class="text-[11px] font-semibold text-ink-soft">Проект</span>
                        <select
                            v-model="form.project_id"
                            class="mt-1.5 h-10 w-full rounded-[13px] border border-line bg-white/70 px-3 text-[12px] text-ink focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        >
                            <option value="">Без проекта</option>
                            <option v-for="project in projectOptions" :key="project.id" :value="project.id">
                                {{ project.key }} · {{ project.name }}
                            </option>
                        </select>
                        <span v-if="form.errors.project_id" class="mt-1 block text-[10px] text-danger">{{ form.errors.project_id }}</span>
                    </label>

                    <p v-if="form.errors.brief_template_id" class="mt-3 text-[10px] text-danger">{{ form.errors.brief_template_id }}</p>

                    <Button type="submit" class="mt-5 w-full" :disabled="!form.brief_template_id || form.processing">
                        {{ form.processing ? 'Создаю…' : 'Создать бриф' }}
                    </Button>

                    <p class="mt-3 text-[10px] leading-relaxed text-faint">
                        Клиент заполняет бриф в кабинете и отправляет. При отправке создаётся задача на разбор.
                    </p>
                </section>
            </aside>
        </form>
    </AdminLayout>
</template>
