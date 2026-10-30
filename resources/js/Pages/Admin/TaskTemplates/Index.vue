<script setup>
import { reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { PlusIcon, SparklesIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: Object,
    filters: Object,
    stats: Object,
    clients: Array,
});

const local = reactive({ q: props.filters?.q || '', scope: props.filters?.scope || '' });
const selected = ref(null);
const applyForm = useForm({ client_id: '', title: '' });

function search() {
    router.get(route('admin.task-templates.index'), { ...local, q: local.q || undefined, scope: local.scope || undefined }, { preserveState: true, replace: true });
}

function openApply(template) {
    selected.value = template;
    applyForm.reset();
    applyForm.title = template.name;
    applyForm.clearErrors();
}

function applyTemplate() {
    applyForm.post(route('admin.task-templates.apply', selected.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = null;
            applyForm.reset();
        },
    });
}

function toggle(template) {
    router.patch(route('admin.task-templates.toggle', template.id), {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Шаблоны задач">
        <template #actions>
            <Link :href="route('admin.task-templates.create')">
                <Button>
                    <PlusIcon class="h-4 w-4" />
                    Создать шаблон
                </Button>
            </Link>
        </template>

        <section class="surface-card mb-5 flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:p-6">
            <KikiAvatar :size="64" class="kiki-ring" />
            <div class="min-w-0 flex-1">
                <p class="eyebrow">Меньше хаоса</p>
                <h2 class="mt-1 font-display text-[19px] font-medium tracking-[-0.03em] text-ink">Одинаковые задачи — одинаково хорошо</h2>
                <p class="mt-1 text-[12px] leading-relaxed text-muted">
                    Шаблон хранит структуру, критерии и чек-лист. Клиент выбирает понятный сценарий, а не пишет «сделайте красиво».
                </p>
            </div>
            <div class="flex shrink-0 gap-4 sm:gap-6">
                <div><p class="font-display text-[22px] font-medium text-ink">{{ stats?.total ?? 0 }}</p><p class="text-[10px] text-muted">всего</p></div>
                <div><p class="font-display text-[22px] font-medium text-ink">{{ stats?.active ?? 0 }}</p><p class="text-[10px] text-muted">активных</p></div>
                <div><p class="font-display text-[22px] font-medium text-ink">{{ stats?.usage ?? 0 }}</p><p class="text-[10px] text-muted">применений</p></div>
            </div>
        </section>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <div class="flex rounded-full border border-line bg-white/50 p-1">
                <button
                    v-for="option in [{ value: '', label: 'Все' }, { value: 'global', label: 'Общие' }, { value: 'personal', label: 'Личные' }]"
                    :key="option.value"
                    type="button"
                    :class="['rounded-full px-3 py-1.5 text-[11px] font-semibold transition', local.scope === option.value ? 'bg-ink text-canvas' : 'text-muted hover:text-ink']"
                    @click="local.scope = option.value; search()"
                >
                    {{ option.label }}
                </button>
            </div>
            <form class="ml-auto" @submit.prevent="search">
                <input
                    v-model="local.q"
                    type="search"
                    placeholder="Найти шаблон…"
                    class="h-9 w-[220px] rounded-full border border-line bg-white/60 px-4 text-[11px] text-ink placeholder-faint focus:border-primary-400 focus:outline-none"
                />
            </form>
        </div>

        <div v-if="!templates.data.length" class="surface-card">
            <EmptyState variant="flight" title="Шаблонов пока нет" text="Начните с одного сценария, который повторяется чаще всего. Кики поможет собрать структуру.">
                <Link :href="route('admin.task-templates.create', { ai: 1 })">
                    <Button><SparklesIcon class="h-4 w-4" />Собрать с Кики</Button>
                </Link>
            </EmptyState>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="(template, index) in templates.data" :key="template.id" class="surface-card surface-card-hover flex flex-col p-5">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-mono text-[10px] text-faint">{{ String(index + 1).padStart(2, '0') }}</span>
                    <div class="flex items-center gap-2">
                        <span v-if="!template.is_active" class="rounded-full bg-ink/[0.06] px-2 py-0.5 font-mono text-[8px] uppercase tracking-[0.1em] text-muted">выкл</span>
                        <span v-if="template.client" class="rounded-full bg-sky/15 px-2 py-0.5 text-[9px] font-medium text-[#3d718f]">{{ template.client.name }}</span>
                        <span v-else class="rounded-full bg-primary-soft px-2 py-0.5 text-[9px] font-medium text-primary-700">общий</span>
                    </div>
                </div>

                <h3 class="mt-3 text-[15px] font-bold leading-snug tracking-[-0.01em] text-ink">{{ template.name }}</h3>
                <p class="mt-1.5 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ template.summary || 'Без описания' }}</p>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">{{ template.checklist?.length || 0 }} шагов</span>
                    <span v-if="template.default_due_days" class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">{{ template.default_due_days }} дн.</span>
                    <span v-if="template.estimated_minutes" class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">{{ Math.round(template.estimated_minutes / 60) }} ч</span>
                    <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">{{ template.usage_count }} исп.</span>
                </div>

                <div class="mt-auto flex items-center gap-2 pt-5">
                    <Button size="sm" class="flex-1" @click="openApply(template)">Применить</Button>
                    <Link :href="route('admin.task-templates.edit', template.id)">
                        <Button size="sm" variant="secondary">Изменить</Button>
                    </Link>
                    <button
                        type="button"
                        class="rounded-full border border-line px-2.5 py-1.5 text-[10px] text-muted transition hover:text-ink"
                        :title="template.is_active ? 'Выключить' : 'Включить'"
                        @click="toggle(template)"
                    >
                        {{ template.is_active ? 'Выкл' : 'Вкл' }}
                    </button>
                </div>
            </article>
        </div>

        <Pagination :links="templates" />

        <!-- Apply modal -->
        <div v-if="selected" class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]" @click="selected = null"></div>
            <div class="relative w-full max-w-[440px] rounded-[26px] border border-line bg-surface p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="eyebrow">Применить шаблон</p>
                        <h3 class="mt-1 font-display text-[19px] font-medium tracking-[-0.03em] text-ink">{{ selected.name }}</h3>
                    </div>
                    <button type="button" class="rounded-xl p-1.5 text-muted hover:bg-ink/[0.05]" @click="selected = null"><XMarkIcon class="h-5 w-5" /></button>
                </div>
                <div class="mt-5 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Клиент</label>
                        <select v-model="applyForm.client_id" class="h-10 w-full rounded-[13px] border border-line bg-white/70 px-3.5 text-[13px]">
                            <option value="">Выберите клиента</option>
                            <option v-for="client in clients" :key="client.id" :value="client.id">{{ client.name }}</option>
                        </select>
                        <p v-if="applyForm.errors.client_id" class="mt-1 text-[10px] text-danger-ink">{{ applyForm.errors.client_id }}</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Название задачи</label>
                        <input v-model="applyForm.title" class="h-10 w-full rounded-[13px] border border-line bg-white/70 px-3.5 text-[13px]" />
                        <p v-if="applyForm.errors.title" class="mt-1 text-[10px] text-danger-ink">{{ applyForm.errors.title }}</p>
                    </div>
                    <div class="rounded-2xl bg-surface-2/60 p-3.5">
                        <p class="text-[10px] font-semibold uppercase tracking-[0.08em] text-muted">В задачу попадут</p>
                        <p class="mt-1.5 text-[11px] leading-relaxed text-ink-soft">{{ selected.checklist?.length || 0 }} шагов чек-листа, описание и настройки по умолчанию.</p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <Button variant="ghost" size="sm" @click="selected = null">Отмена</Button>
                    <Button size="sm" :disabled="applyForm.processing || !applyForm.client_id" @click="applyTemplate">Создать задачу</Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
