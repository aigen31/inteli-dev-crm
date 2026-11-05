<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ArrowTopRightOnSquareIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
} from '@heroicons/vue/24/outline';
import { formatDateTime } from '@/Utils/formatters.js';

const props = defineProps({
    report: { type: Object, required: true },
});

const groups = computed(() => props.report.items || []);
const isRead = computed(() => Boolean(props.report.read_at));
</script>

<template>
    <div class="mx-auto max-w-[1120px]">
        <section class="relative overflow-hidden rounded-[26px] border border-line bg-surface p-5 shadow-[0_18px_45px_rgba(28,24,35,0.06)] sm:p-6">
            <div class="absolute inset-0 bg-[radial-gradient(120%_130%_at_100%_-10%,rgba(143,154,255,0.16),transparent_60%)]"></div>
            <div class="relative">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-line bg-white/70 px-2.5 py-1 font-mono text-[9px] uppercase tracking-[0.12em] text-muted">Отчёт о выполнении</span>
                    <span v-if="report.task?.reference" class="rounded-full border border-line bg-white/70 px-2.5 py-1 font-mono text-[9px] text-ink-soft">{{ report.task.reference }}</span>
                    <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[9px] font-semibold', isRead ? 'bg-success/10 text-success' : 'bg-primary-soft text-primary-800']">
                        <CheckCircleIcon v-if="isRead" class="h-3 w-3" />
                        <ClockIcon v-else class="h-3 w-3" />
                        {{ isRead ? 'прочитан' : 'новый' }}
                    </span>
                </div>

                <h1 class="mt-3 max-w-[760px] font-display text-[30px] font-medium leading-[1.05] tracking-[-0.045em] text-ink sm:text-[40px]">
                    {{ report.display_title || report.title || 'Отчёт' }}
                </h1>

                <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted">
                    <span>{{ groups.length }} {{ groups.length === 1 ? 'группа' : 'групп' }}</span>
                    <span v-if="report.author">· от {{ report.author.name }}</span>
                    <span v-if="report.recipient">· для {{ report.recipient.name }}</span>
                    <span class="font-mono text-[10px] text-faint">· {{ formatDateTime(report.sent_at) }}</span>
                </div>

                <Link
                    v-if="report.task?.url"
                    :href="report.task.url"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-[12px] border border-line bg-white/70 px-3 py-1.5 text-[10px] font-semibold text-ink-soft hover:border-primary-300 hover:text-primary-800"
                >
                    <DocumentTextIcon class="h-3.5 w-3.5" />
                    Открыть задачу
                    <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                </Link>
            </div>
        </section>

        <div class="mt-5 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_300px]">
            <div class="min-w-0 space-y-4">
                <article
                    v-for="(item, index) in groups"
                    :key="item.id"
                    class="overflow-hidden rounded-[24px] border border-line bg-surface shadow-[0_14px_36px_rgba(28,24,35,0.05)]"
                >
                    <header class="flex items-center gap-3 border-b border-line/70 px-5 py-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-ink text-[10px] font-bold text-canvas">{{ index + 1 }}</span>
                        <div>
                            <p class="text-[11px] font-semibold text-ink">Что проверено</p>
                            <p class="text-[9px] text-muted">группа {{ index + 1 }} из {{ groups.length }}</p>
                        </div>
                    </header>

                    <div class="px-5 py-4">
                        <p class="whitespace-pre-wrap text-[13px] leading-relaxed text-ink">{{ item.summary }}</p>

                        <a
                            v-if="item.test_url"
                            :href="item.test_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-4 inline-flex items-center gap-2 rounded-[13px] border border-primary-200 bg-primary-soft/50 px-3.5 py-2 text-[11px] font-semibold text-primary-800 transition hover:border-primary-300"
                        >
                            Открыть страницу для тестирования
                            <ArrowTopRightOnSquareIcon class="h-3.5 w-3.5" />
                        </a>

                        <a v-if="item.screenshot_url" :href="item.screenshot_url" target="_blank" rel="noopener" class="mt-4 block">
                            <img
                                :src="item.screenshot_url"
                                alt="Скриншот отчёта"
                                class="max-h-[540px] w-full rounded-[16px] border border-line bg-white/60 object-contain"
                                loading="lazy"
                            />
                        </a>
                    </div>
                </article>
            </div>

            <aside class="min-w-0 space-y-4">
                <section class="surface-card p-5">
                    <p class="eyebrow">Получатель</p>
                    <div v-if="report.recipient" class="mt-3 flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-soft text-[11px] font-bold text-primary-800">
                            {{ report.recipient.name.slice(0, 1).toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-[12px] font-semibold text-ink">{{ report.recipient.name }}</p>
                            <p class="truncate text-[10px] text-muted">{{ report.recipient.email }}</p>
                        </div>
                    </div>
                    <p v-else class="mt-2 text-[11px] text-muted">Получатель не указан.</p>

                    <div class="mt-4 border-t border-line/70 pt-3">
                        <p class="eyebrow">Автор</p>
                        <p class="mt-1 text-[11px] font-semibold text-ink">{{ report.author?.name || '—' }}</p>
                    </div>
                </section>

                <section class="surface-card p-5">
                    <p class="eyebrow">Доставка</p>
                    <dl class="mt-2 space-y-2 text-[11px]">
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted">Отправлен</dt>
                            <dd class="font-mono text-[10px] text-ink">{{ formatDateTime(report.sent_at) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-muted">Прочитан</dt>
                            <dd class="font-mono text-[10px]" :class="isRead ? 'text-success' : 'text-faint'">{{ isRead ? formatDateTime(report.read_at) : 'ещё нет' }}</dd>
                        </div>
                    </dl>
                </section>

                <section v-if="report.task" class="surface-card p-5">
                    <p class="eyebrow">Задача</p>
                    <p v-if="report.task.project" class="mt-2 text-[10px] text-muted">{{ report.task.project }}</p>
                    <Link v-if="report.task.url" :href="report.task.url" class="mt-1 block text-[12px] font-semibold text-ink hover:text-primary-800">
                        {{ report.task.title }}
                    </Link>
                    <p v-else class="mt-1 text-[12px] font-semibold text-ink">{{ report.task.title }}</p>
                </section>

                <section class="rounded-[18px] border border-line bg-white/40 p-4">
                    <p class="text-[10px] font-semibold text-ink">Формат отчёта</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Объяснение → ссылка для тестирования → скриншот. Групп может быть сколько угодно.</p>
                </section>
            </aside>
        </div>
    </div>
</template>
