<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { formatDate } from '@/Utils/formatters.js';
import { ArrowLeftIcon, CheckCircleIcon, PlusIcon } from '@heroicons/vue/24/outline';

defineProps({
    project: Object,
    tasks: Array,
    progress: Object,
    team: { type: Array, default: () => [] },
});
</script>

<template>
    <ClientLayout title="Проект">
        <template #actions>
            <Link
                v-if="project.can?.create_tasks"
                :href="route('client.tasks.create', { project_id: project.id })"
                class="mr-2 inline-flex items-center gap-1.5 rounded-[14px] bg-primary-600 px-3 py-2 text-[11px] font-semibold text-white"
            >
                <PlusIcon class="h-3.5 w-3.5" />Новая задача
            </Link>
            <Link :href="route('client.projects.index')">
                <span class="inline-flex items-center gap-1.5 rounded-[14px] border border-line-strong bg-white/60 px-3 py-2 text-[11px] font-semibold text-ink">
                    <ArrowLeftIcon class="h-3.5 w-3.5" />Проекты
                </span>
            </Link>
        </template>

        <section class="relative overflow-hidden rounded-[26px] border border-line bg-surface p-5 shadow-[0_18px_45px_rgba(28,24,35,0.06)] sm:p-6">
            <div class="absolute inset-0 bg-[radial-gradient(120%_130%_at_100%_-10%,rgba(143,154,255,0.16),transparent_60%)]"></div>
            <div class="relative flex flex-wrap items-start gap-4">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full border border-line bg-white/70 px-2.5 py-1 font-mono text-[10px] tracking-[0.08em] text-muted">{{ project.key }}</span>
                        <span class="rounded-full bg-primary-soft px-2.5 py-1 text-[10px] font-semibold text-primary-800">{{ project.status_label }}</span>
                        <span v-if="project.access" class="text-[10px] text-faint">{{ project.access }}</span>
                    </div>
                    <h1 class="mt-2 font-display text-[30px] font-medium leading-tight tracking-[-0.04em] text-ink">{{ project.name }}</h1>
                    <p v-if="project.description" class="mt-2 max-w-[620px] text-[12px] leading-relaxed text-ink-soft">{{ project.description }}</p>
                </div>

                <div class="w-full max-w-[260px] rounded-[18px] border border-line bg-white/70 p-4">
                    <div class="flex items-end justify-between">
                        <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-faint">Прогресс</p>
                        <p class="font-display text-[26px] leading-none text-ink">{{ progress.percent }}<span class="text-[13px] text-muted">%</span></p>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-ink/[0.06]">
                        <div class="h-full rounded-full bg-primary-500" :style="{ width: progress.percent + '%' }"></div>
                    </div>
                    <p class="mt-2 text-[10px] text-muted">{{ progress.completed }} из {{ progress.total }} задач готово</p>
                </div>
            </div>
        </section>

        <section class="surface-card mt-5">
            <header class="flex items-center gap-2 border-b border-line/80 px-5 py-3.5">
                <CheckCircleIcon class="h-4 w-4 text-primary-600" />
                <p class="text-[12px] font-bold text-ink">Мои задачи в проекте</p>
                <span class="font-mono text-[9px] text-faint">{{ tasks.length }}</span>
            </header>

            <div v-if="tasks.length" class="divide-y divide-line/70">
                <Link
                    v-for="task in tasks"
                    :key="task.id"
                    :href="route('client.tasks.show', task.id)"
                    class="flex flex-wrap items-center gap-3 px-5 py-3 transition hover:bg-primary-soft/30"
                >
                    <span class="w-[54px] shrink-0 font-mono text-[9px] text-faint">{{ task.number ? project.key + '-' + task.number : '—' }}</span>
                    <span class="min-w-0 flex-1 truncate text-[12px] font-medium text-ink">{{ task.title }}</span>
                    <Badge :status="task.status" type="task" />
                    <span v-if="task.deadline" class="font-mono text-[9px] text-faint">{{ formatDate(task.deadline) }}</span>
                </Link>
            </div>

            <div v-else class="p-2">
                <EmptyState compact title="Задач пока нет" text="В этом проекте у вас ещё нет задач. Как только они появятся, вы увидите их здесь." />
            </div>
        </section>

        <section v-if="project.can?.see_team && team.length" class="surface-card mt-4 p-5">
            <p class="eyebrow">Команда проекта</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <span v-for="member in team" :key="member.name" class="rounded-full border border-line bg-white/70 px-3 py-1 text-[10px] text-ink-soft">
                    {{ member.name }} · {{ member.role }}
                </span>
            </div>
        </section>
    </ClientLayout>
</template>
