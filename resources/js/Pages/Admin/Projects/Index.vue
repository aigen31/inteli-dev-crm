<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { FolderIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    projects: Array,
    filters: Object,
    statuses: Array,
    clients: Array,
    counts: Object,
});

const local = reactive({
    q: props.filters?.q || '',
    status: props.filters?.status || '',
    client_id: props.filters?.client_id || '',
    archived: props.filters?.archived ? '1' : '',
});

const colorBar = {
    violet: 'bg-primary-500',
    sage: 'bg-sage',
    sky: 'bg-sky',
    peach: 'bg-peach',
    ink: 'bg-ink',
};

const statusTone = {
    active: 'bg-primary-soft text-primary-800',
    on_hold: 'bg-warning/10 text-warning',
    completed: 'bg-success/10 text-success',
    draft: 'bg-ink/[0.06] text-muted',
    archived: 'bg-ink/[0.06] text-muted',
};

const clientOptions = computed(() => props.clients.map((client) => ({ value: client.id, label: client.name })));

function apply(extra = {}) {
    router.get(
        route('admin.projects.index'),
        {
            ...local,
            ...extra,
            status: local.status || undefined,
            client_id: local.client_id || undefined,
            q: local.q || undefined,
            archived: local.archived || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function search() {
    apply({ q: local.q || undefined });
}
</script>

<template>
    <AdminLayout title="Проекты">
        <template #actions>
            <Link :href="route('admin.projects.create')">
                <Button size="sm"><PlusIcon class="h-4 w-4" />Новый проект</Button>
            </Link>
        </template>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <p class="max-w-[560px] text-[12px] leading-relaxed text-muted">
                Задачи, сроки и знания о сайте живут вместе. У каждого проекта свой ключ, AGENTS.md и прогресс.
            </p>
            <div class="flex flex-wrap gap-2 text-[11px] text-muted">
                <span class="rounded-full border border-line bg-surface px-3 py-1.5">всего {{ counts?.all ?? 0 }}</span>
                <span class="rounded-full border border-line bg-surface px-3 py-1.5">в работе {{ counts?.active ?? 0 }}</span>
                <span class="rounded-full border border-line bg-surface px-3 py-1.5">на паузе {{ counts?.on_hold ?? 0 }}</span>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            <div class="relative min-w-[220px] flex-1">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                <input
                    v-model="local.q"
                    class="h-10 w-full rounded-[13px] border border-line bg-white/70 pl-9 pr-3 text-[13px] text-ink placeholder-faint focus:border-primary-300 focus:outline-none"
                    placeholder="Найти проект"
                    @keyup.enter="search"
                />
            </div>
            <div class="w-[170px]">
                <SelectInput v-model="local.status" :options="statuses" placeholder="Любой статус" @update:model-value="apply({ status: $event || undefined })" />
            </div>
            <div class="w-[180px]">
                <SelectInput v-model="local.client_id" :options="clientOptions" placeholder="Все клиенты" @update:model-value="apply({ client_id: $event || undefined })" />
            </div>
            <label class="flex items-center gap-2 text-[11px] text-muted">
                <input v-model="local.archived" type="checkbox" true-value="1" false-value="" class="h-3.5 w-3.5 rounded border-line-strong" @change="apply({ archived: local.archived || undefined })" />
                архив
            </label>
        </div>

        <div v-if="projects.length" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="project in projects"
                :key="project.id"
                :href="route('admin.projects.show', project.id)"
                class="group relative overflow-hidden rounded-[22px] border border-line bg-surface p-5 shadow-[0_14px_36px_rgba(28,24,35,0.05)] transition hover:-translate-y-0.5 hover:shadow-[0_20px_45px_rgba(28,24,35,0.09)]"
            >
                <div :class="['absolute inset-x-0 top-0 h-[3px]', colorBar[project.color] || colorBar.violet]"></div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full border border-line bg-white/70 px-2 py-0.5 font-mono text-[9px] tracking-[0.08em] text-muted">{{ project.key }}</span>
                    <span :class="['rounded-full px-2 py-0.5 text-[9px] font-semibold', statusTone[project.status] || statusTone.draft]">{{ project.status_label }}</span>
                    <span v-if="project.due_at" class="ml-auto font-mono text-[9px] text-faint">до {{ project.due_at }}</span>
                </div>

                <h2 class="mt-3 line-clamp-2 text-[17px] font-semibold leading-snug text-ink group-hover:text-primary-800">{{ project.name }}</h2>
                <p class="mt-1 text-[11px] text-muted">{{ project.client?.name || 'Внутренний проект' }}</p>

                <div class="mt-4">
                    <div class="flex items-center justify-between font-mono text-[9px] text-faint">
                        <span>{{ project.completed_count }} / {{ project.tasks_count }} задач</span>
                        <span>{{ project.percent }}%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink/[0.06]">
                        <div class="h-full rounded-full bg-primary-500 transition-all" :style="{ width: project.percent + '%' }"></div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2 text-[10px] text-muted">
                    <FolderIcon class="h-3.5 w-3.5 text-faint" />
                    <span class="truncate">{{ project.site_url || 'сайт не задан' }}</span>
                    <span v-if="project.studied_at" class="ml-auto shrink-0 rounded-full bg-sage/15 px-2 py-0.5 text-[9px] text-ink-soft">AGENTS.md</span>
                </div>
            </Link>
        </div>

        <EmptyState
            v-else
            class="mt-6"
            title="Проектов пока нет"
            text="Создайте первый проект: к нему можно привязать задачи, сайт и агентскую инструкцию."
        >
            <Link :href="route('admin.projects.create')"><Button size="sm">Создать проект</Button></Link>
        </EmptyState>
    </AdminLayout>
</template>
