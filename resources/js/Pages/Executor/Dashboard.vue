<script setup>
import { Link } from '@inertiajs/vue3';
import ExecutorLayout from '@/Components/Layout/ExecutorLayout.vue';
import ActivityCalendarWidget from '@/Components/Dashboard/ActivityCalendarWidget.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime } from '@/Utils/formatters.js';
import { ArrowUpRightIcon, CheckCircleIcon, ClockIcon, InboxIcon } from '@heroicons/vue/24/outline';

defineProps({
    tasks: { type: Array, default: () => [] },
    reports: { type: Array, default: () => [] },
    counts: { type: Object, default: () => ({}) },
});
</script>

<template>
    <ExecutorLayout title="Обзор">
        <section class="relative mb-5 overflow-hidden rounded-[28px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-6 sm:px-7">
            <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center gap-5">
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Рабочее место</p>
                    <h1 class="mt-1 font-display text-[26px] font-medium tracking-[-0.04em] text-ink">Задачи, в которых вы участвуете</h1>
                    <p class="mt-2 max-w-[560px] text-[12px] leading-relaxed text-muted">Здесь только ваша работа: назначенные задачи, чек-листы и отчёты клиенту. Клиенты, счета и чужие задачи остаются вне этого экрана.</p>
                </div>
                <KikiAvatar :size="66" />
            </div>
        </section>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="surface-card p-4"><p class="eyebrow">Активные</p><p class="mt-2 font-display text-[28px] text-ink">{{ counts.active || 0 }}</p><p class="mt-1 text-[10px] text-muted">в работе и на проверке</p></div>
            <div class="surface-card p-4"><p class="eyebrow">В работе</p><p class="mt-2 font-display text-[28px] text-ink">{{ counts.in_progress || 0 }}</p><p class="mt-1 text-[10px] text-muted">сейчас у вас</p></div>
            <div class="surface-card p-4"><p class="eyebrow">Ждут оценки</p><p class="mt-2 font-display text-[28px] text-ink">{{ counts.awaiting_review || 0 }}</p><p class="mt-1 text-[10px] text-muted">отправлены клиенту</p></div>
            <div class="surface-card p-4"><p class="eyebrow">Просрочено</p><p :class="['mt-2 font-display text-[28px]', counts.overdue ? 'text-danger-ink' : 'text-ink']">{{ counts.overdue || 0 }}</p><p class="mt-1 text-[10px] text-muted">срок уже прошёл</p></div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.25fr_.75fr]">
            <section class="surface-card overflow-hidden">
                <div class="flex items-center justify-between border-b border-line/70 px-5 py-4">
                    <div><p class="eyebrow">Фокус</p><h2 class="mt-1 text-[15px] font-bold text-ink">Что делать дальше</h2></div>
                    <Link :href="route('executor.tasks.index')" class="inline-flex items-center gap-1 text-[10px] font-semibold text-primary-700 hover:underline">Все задачи <ArrowUpRightIcon class="h-3.5 w-3.5" /></Link>
                </div>
                <div v-if="!tasks.length" class="p-2">
                    <EmptyState variant="flight" :show-avatar="false" title="Пока нет назначенных задач" text="Как только администратор назначит вас на задачу, она появится здесь." />
                </div>
                <div v-else class="divide-y divide-line/60">
                    <Link v-for="task in tasks" :key="task.id" :href="route('executor.tasks.show', task.id)" class="flex items-center gap-3 px-5 py-4 transition hover:bg-ink/[0.025]">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-semibold text-ink">{{ task.title }}</span>
                            <span class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] text-muted">
                                <span v-if="task.project" class="rounded-full border border-line bg-white/60 px-2 py-0.5 font-mono text-[8px]">{{ task.project.key }}{{ task.number ? '-' + task.number : '' }}</span>
                                {{ task.client?.name || 'Без клиента' }}
                                <span v-if="task.deadline" :class="new Date(task.deadline) < new Date() ? 'text-danger-ink' : ''">· {{ formatDate(task.deadline) }}</span>
                            </span>
                        </span>
                        <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                    </Link>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="surface-card p-5">
                    <p class="eyebrow">Мои отчёты</p>
                    <div v-if="!reports.length" class="mt-3 text-[11px] text-muted">Отчётов пока нет.</div>
                    <div v-else class="mt-3 space-y-3">
                        <Link v-for="report in reports" :key="report.id" :href="route('reports.show', report.id)" class="block rounded-[12px] border border-line/60 px-3 py-2.5 transition hover:border-primary-200 hover:bg-primary-50/30">
                            <p class="truncate text-[11px] font-semibold text-ink">{{ report.title || 'Отчёт' }}</p>
                            <p class="mt-0.5 truncate text-[9px] text-muted">{{ report.task?.title || 'Без задачи' }} · {{ formatDateTime(report.created_at) }}</p>
                        </Link>
                    </div>
                </section>
                <section class="surface-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="eyebrow">Моя нагрузка</p>
                        <span class="font-mono text-[9px] uppercase tracking-[0.12em] text-faint">задачи</span>
                    </div>
                    <div class="mt-2">
                        <ActivityCalendarWidget compact />
                    </div>
                </section>
                <section class="rounded-[20px] border border-line/70 bg-white/35 p-4">
                    <p class="eyebrow">Ритм</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Двигайте статус, когда задача действительно меняет состояние. Отчёт отправляется клиенту сразу после сохранения.</p>
                </section>
            </aside>
        </div>
    </ExecutorLayout>
</template>
