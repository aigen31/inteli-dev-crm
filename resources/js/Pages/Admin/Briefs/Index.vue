<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { formatDate } from '@/Utils/formatters.js';
import { ClipboardDocumentListIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    briefs: Object,
    filters: Object,
    stats: Object,
    statuses: Array,
});

const search = ref(props.filters?.q || '');

const tabs = [
    { value: 'open', label: 'Активные', count: props.stats?.in_progress },
    { value: 'submitted', label: 'На проверке', count: props.stats?.submitted },
    { value: 'archived', label: 'Архив', count: props.stats?.archived },
    { value: 'all', label: 'Все', count: props.stats?.total },
];

function setStatus(value) {
    router.get(route('admin.briefs.index'), { status: value, q: search.value || undefined }, { preserveState: true, replace: true });
}

function applySearch() {
    router.get(route('admin.briefs.index'), { status: props.filters?.status || 'open', q: search.value || undefined }, { preserveState: true, replace: true });
}
</script>

<template>
    <AdminLayout title="Брифы">
        <template #actions>
            <Link :href="route('admin.briefs.create')">
                <Button><PlusIcon class="h-4 w-4" />Создать бриф</Button>
            </Link>
        </template>

        <div class="mb-5">
            <p class="eyebrow">Клиентские брифы</p>
            <p class="mt-2 max-w-[720px] text-[12px] leading-relaxed text-muted">
                Структурированные вводные от клиента: шаблон, ответы по секциям и отправка. Заполненный бриф приходит задачей на разбор.
            </p>
        </div>

        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Всего</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-ink">{{ stats.total }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Заполняются</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-ink">{{ stats.in_progress }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">На проверке</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-primary-700">{{ stats.submitted }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">В архиве</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-muted">{{ stats.archived }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="inline-flex flex-wrap gap-1 rounded-full border border-line bg-white/50 p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold transition',
                        (filters?.status || 'open') === tab.value ? 'bg-ink text-canvas' : 'text-muted hover:text-ink',
                    ]"
                    @click="setStatus(tab.value)"
                >
                    {{ tab.label }}
                    <span v-if="tab.count != null" class="font-mono text-[9px] opacity-70">{{ tab.count }}</span>
                </button>
            </div>

            <form class="ml-auto flex items-center gap-2" @submit.prevent="applySearch">
                <label class="relative block">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Название или клиент"
                        class="h-9 w-[220px] rounded-[12px] border border-line bg-white/70 pl-9 pr-3 text-[12px] text-ink placeholder:text-faint focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                        @change="applySearch"
                    >
                </label>
            </form>
        </div>

        <div v-if="!briefs.data.length" class="surface-card">
            <EmptyState
                image="/images/kiki/kiki-forms-avatar.webp"
                title="Брифов пока нет"
                text="Выберите шаблон и выдайте клиенту бриф — он заполнит его в своём кабинете."
            >
                <Link :href="route('admin.briefs.create')"><Button size="sm">Создать бриф</Button></Link>
            </EmptyState>
        </div>

        <div v-else class="surface-card overflow-hidden">
            <div class="hidden grid-cols-[minmax(0,1.6fr)_minmax(0,.9fr)_minmax(0,.8fr)_110px_96px] gap-4 border-b border-line/80 bg-surface-2/40 px-5 py-3 font-mono text-[9px] uppercase tracking-[0.14em] text-faint lg:grid">
                <span>Бриф</span>
                <span>Клиент</span>
                <span>Шаблон</span>
                <span>Прогресс</span>
                <span class="text-right">Статус</span>
            </div>

            <Link
                v-for="brief in briefs.data"
                :key="brief.id"
                :href="route('admin.briefs.show', brief.id)"
                class="grid grid-cols-1 items-center gap-3 border-b border-line/60 px-5 py-4 transition hover:bg-ink/[0.02] lg:grid-cols-[minmax(0,1.6fr)_minmax(0,.9fr)_minmax(0,.8fr)_110px_96px] lg:gap-4"
            >
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-semibold text-ink">{{ brief.title }}</p>
                    <p class="mt-1 font-mono text-[9px] text-faint">
                        {{ brief.submitted_at ? `отправлен ${formatDate(brief.submitted_at)}` : `изменён ${formatDate(brief.updated_at)}` }}
                        <span v-if="brief.project"> · {{ brief.project.key }}</span>
                    </p>
                </div>
                <div class="min-w-0 text-[12px] text-ink-soft">
                    <span class="truncate">{{ brief.client?.name || 'Без клиента' }}</span>
                </div>
                <div class="min-w-0">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-2 px-2.5 py-1 text-[10px] font-semibold text-ink-soft">
                        <ClipboardDocumentListIcon class="h-3.5 w-3.5 text-primary-600" />
                        {{ brief.template?.title || 'Шаблон удалён' }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-surface-2">
                        <div class="h-full rounded-full bg-primary-500" :style="{ width: `${brief.progress}%` }" />
                    </div>
                    <span class="font-mono text-[9px] text-muted">{{ brief.progress }}%</span>
                </div>
                <div class="lg:text-right">
                    <Badge :status="brief.status" type="brief">{{ brief.status_label }}</Badge>
                </div>
            </Link>

            <div class="px-5 py-4">
                <Pagination :links="briefs" />
            </div>
        </div>
    </AdminLayout>
</template>
