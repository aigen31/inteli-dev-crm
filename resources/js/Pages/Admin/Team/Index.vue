<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { MagnifyingGlassIcon, PlusIcon, UserGroupIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    executors: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const local = reactive({ q: props.filters?.q || '' });

function search() {
    router.get(route('admin.team.index'), { q: local.q || undefined }, { preserveState: true, replace: true });
}

function toggle(executor) {
    router.patch(route('admin.team.toggle', executor.id), {}, { preserveScroll: true });
}

function initials(name) {
    return (name || '?').split(' ').slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
}
</script>

<template>
    <AdminLayout title="Команда">
        <template #actions>
            <Link :href="route('admin.team.create')"><Button><PlusIcon class="h-4 w-4" />Добавить исполнителя</Button></Link>
        </template>

        <section class="relative mb-5 overflow-hidden rounded-[26px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-5 sm:px-7">
            <div class="pointer-events-none absolute -right-10 -top-16 h-44 w-44 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <span class="flex h-11 w-11 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700"><WrenchScrewdriverIcon class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Люди, которые делают работу</p>
                    <h1 class="mt-1 font-display text-[22px] font-medium tracking-[-0.03em] text-ink">Исполнители</h1>
                    <p class="mt-1 max-w-[560px] text-[11px] leading-relaxed text-muted">Исполнитель видит только свои назначенные задачи, может вести чек-лист, менять рабочий статус и отправлять отчёты клиенту.</p>
                </div>
                <span class="rounded-full border border-line bg-white/55 px-3 py-1.5 font-mono text-[9px] uppercase tracking-[0.1em] text-muted">{{ executors.length }} в команде</span>
            </div>
        </section>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <form class="relative w-full max-w-[360px]" @submit.prevent="search">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <input v-model="local.q" type="search" placeholder="Имя или email…" class="h-10 w-full rounded-full border border-line bg-white/60 pl-10 pr-4 text-[12px] text-ink placeholder-faint focus:border-primary-400 focus:outline-none" />
            </form>
        </div>

        <div v-if="!executors.length" class="surface-card">
            <EmptyState title="В команде пока никого" text="Добавьте исполнителя — после этого его можно будет назначить на задачу." :image="null">
                <Link :href="route('admin.team.create')"><Button size="sm">Добавить исполнителя</Button></Link>
            </EmptyState>
        </div>

        <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="executor in executors" :key="executor.id" class="surface-card flex flex-col p-5">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[12px] font-bold text-primary-800">{{ initials(executor.name) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-bold text-ink">{{ executor.name }}</p>
                        <p class="mt-0.5 truncate text-[11px] text-muted">{{ executor.email }}</p>
                        <p class="mt-0.5 text-[10px] text-faint">{{ executor.phone || 'телефон не указан' }}</p>
                    </div>
                    <span :class="['rounded-full px-2 py-0.5 text-[9px] font-semibold', executor.is_active ? 'bg-success/10 text-success' : 'bg-ink/[0.06] text-muted']">{{ executor.is_active ? 'активен' : 'выкл' }}</span>
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-line/70 pt-3">
                    <span class="inline-flex items-center gap-1.5 text-[10px] text-muted"><UserGroupIcon class="h-3.5 w-3.5" />{{ executor.assigned_tasks_count }} задач</span>
                    <button type="button" class="text-[9px] font-semibold text-primary-700 hover:underline" @click="toggle(executor)">{{ executor.is_active ? 'Деактивировать' : 'Активировать' }}</button>
                </div>
            </article>
        </div>
    </AdminLayout>
</template>
