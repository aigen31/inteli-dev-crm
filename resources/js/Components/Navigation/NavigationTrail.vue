<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { localeTag } from '@/i18n';
import { formatRelativeTime } from '@/Utils/formatters.js';
import { ArrowRightIcon, ArrowUpRightIcon, CheckCircleIcon, FolderIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const page = usePage();

const primary = computed(() => props.items[0] ?? null);
const trail = computed(() => props.items.slice(1, 6));
const isAdmin = computed(() => !!page.props.auth?.user?.is_admin);

const colorBar = {
    violet: 'bg-primary-500',
    sage: 'bg-sage',
    sky: 'bg-sky',
    peach: 'bg-peach',
    ink: 'bg-ink',
};

const projectIndexUrl = computed(() => route(isAdmin.value ? 'admin.projects.index' : 'client.projects.index'));

const projectUrl = computed(() => {
    if (primary.value?.type !== 'task' || !primary.value?.project) return null;

    return isAdmin.value
        ? route('admin.projects.show', primary.value.project.id)
        : route('client.projects.show', primary.value.project.id);
});

function colorOf(color) {
    return colorBar[color] || colorBar.violet;
}

function relative(value, style = 'long') {
    return formatRelativeTime(value, style);
}

function clock(value) {
    if (!value) return '';
    return new Date(value).toLocaleTimeString(localeTag(), {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function clear() {
    router.delete(route('navigation.clear'), { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div v-if="items.length" class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
        <!-- Resume card: a bookmark on paper -->
        <section class="relative overflow-hidden rounded-[26px] border border-line bg-surface shadow-[0_18px_45px_rgba(28,24,35,0.06)]">
            <span :class="['absolute inset-y-0 left-0 w-[3px]', colorOf(primary.project?.color)]" aria-hidden="true"></span>
            <span
                :class="['absolute right-8 top-0 h-8 w-5 rounded-b-[6px] opacity-90 transition group-hover:h-9', colorOf(primary.project?.color)]"
                aria-hidden="true"
            ></span>
            <div class="pointer-events-none absolute inset-2.5 rounded-[20px] border border-dashed border-line/80" aria-hidden="true"></div>

            <div class="relative p-5 sm:p-7">
                <div class="flex items-start justify-between gap-4 pr-10">
                    <p class="eyebrow">
                        {{ primary.type === 'task' ? 'Вы остановились здесь' : 'Вы остановились в проекте' }}
                    </p>
                    <p class="shrink-0 text-right text-[10px] text-muted">
                        {{ relative(primary.visited_at) }}
                        <span class="ml-1 font-mono text-faint">{{ clock(primary.visited_at) }}</span>
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span
                        v-if="primary.project"
                        class="rounded-full border border-line bg-white/70 px-2.5 py-1 font-mono text-[10px] tracking-[0.08em] text-muted"
                    >
                        {{ primary.project.key }}
                    </span>
                    <Badge v-if="primary.status" :status="primary.status.value" type="task">{{ primary.status.label }}</Badge>
                    <span v-else-if="primary.subtitle" class="text-[11px] text-muted">{{ primary.subtitle }}</span>
                </div>

                <h2 class="mt-3 max-w-[640px] font-display text-[22px] font-medium leading-[1.15] tracking-[-0.03em] text-ink sm:text-[26px]">
                    {{ primary.title }}
                </h2>

                <p class="mt-2 font-mono text-[10px] uppercase tracking-[0.1em] text-faint">
                    <template v-if="primary.type === 'task'">
                        {{ primary.project?.name || 'Без проекта' }}
                        <span v-if="primary.reference"> · {{ primary.reference }}</span>
                    </template>
                    <template v-else-if="primary.progress && primary.progress.total > 0">
                        {{ primary.progress.completed }} / {{ primary.progress.total }} задач · {{ primary.progress.percent }}%
                    </template>
                    <template v-else>Задач пока нет</template>
                </p>

                <div v-if="primary.type === 'project' && primary.progress && primary.progress.total > 0" class="mt-3 h-1.5 w-full max-w-[320px] overflow-hidden rounded-full bg-ink/[0.06]">
                    <div class="h-full rounded-full bg-primary-500" :style="{ width: primary.progress.percent + '%' }"></div>
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <Link :href="primary.url">
                        <Button size="md">
                            {{ primary.type === 'task' ? 'Вернуться к задаче' : 'Открыть проект' }}
                            <ArrowRightIcon class="h-4 w-4" />
                        </Button>
                    </Link>
                    <Link
                        v-if="projectUrl"
                        :href="projectUrl"
                        class="text-[12px] font-semibold text-ink-soft transition hover:text-ink"
                    >
                        Открыть проект
                    </Link>
                </div>
            </div>
        </section>

        <!-- Trail: dashed path between stops -->
        <section class="surface-card flex flex-col overflow-hidden">
            <header class="flex items-center justify-between gap-3 border-b border-line/80 px-5 py-4">
                <div>
                    <p class="eyebrow">Маршрут</p>
                    <h2 class="mt-1 text-[14px] font-bold text-ink">Последние места</h2>
                </div>
                <button type="button" class="text-[10px] text-muted transition hover:text-ink" @click="clear">очистить</button>
            </header>

            <ol v-if="trail.length" class="relative flex-1 px-5 py-3">
                <span class="pointer-events-none absolute bottom-7 left-[27px] top-6 border-l border-dashed border-line" aria-hidden="true"></span>
                <li v-for="item in trail" :key="item.id" class="relative">
                    <Link
                        :href="item.url"
                        class="group flex items-start gap-3 rounded-2xl py-2.5 pl-7 pr-2 transition hover:bg-ink/[0.025]"
                    >
                        <span
                            :class="[
                                'absolute left-[7px] top-[15px] h-2 w-2 rounded-full ring-4 ring-surface',
                                colorOf(item.project?.color),
                            ]"
                            aria-hidden="true"
                        ></span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <component
                                    :is="item.type === 'task' ? CheckCircleIcon : FolderIcon"
                                    class="h-3.5 w-3.5 shrink-0 text-faint"
                                />
                                <span class="truncate text-[12px] font-semibold text-ink transition group-hover:text-primary-800">
                                    {{ item.title }}
                                </span>
                            </span>
                            <span class="mt-0.5 flex items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.08em] text-faint">
                                <span v-if="item.reference">{{ item.reference }} ·</span>
                                <span>{{ relative(item.visited_at, 'short') }}</span>
                            </span>
                        </span>
                        <ArrowUpRightIcon class="mt-1 h-3.5 w-3.5 shrink-0 text-faint opacity-0 transition group-hover:opacity-100" />
                    </Link>
                </li>
            </ol>

            <p
                v-if="trail.length && trail.length < 3"
                class="mt-auto border-t border-line/60 px-5 py-3 text-[10px] leading-relaxed text-faint"
            >
                Маршрут дополнится, когда вы откроете ещё задачи и проекты.
            </p>

            <p v-if="!trail.length" class="px-5 py-6 text-[11px] leading-relaxed text-muted">
                Это первое место за сегодня. Маршрут соберётся, когда вы откроете ещё задачу или проект.
            </p>
        </section>
    </div>

    <!-- Empty history: quiet invitation, not a second dashboard -->
    <section v-else class="surface-card mb-6 flex flex-col items-start gap-5 p-6 sm:flex-row sm:items-center sm:p-7">
        <img
            src="/images/kiki/kiki-empty-project-tasks.png"
            alt="Кики с карточкой и маркером"
            class="h-[88px] w-[88px] shrink-0 object-contain"
        />
        <div class="min-w-0">
            <p class="eyebrow">Маршрут</p>
            <h2 class="mt-1.5 font-display text-[19px] font-medium tracking-[-0.03em] text-ink">Пока пусто</h2>
            <p class="mt-1.5 max-w-[520px] text-[12px] leading-relaxed text-muted">
                Здесь появятся проекты и задачи, которые вы открывали. Начните с проекта — и вернуться будет проще.
            </p>
        </div>
        <Link :href="projectIndexUrl" class="sm:ml-auto">
            <Button variant="secondary" size="sm">К проектам</Button>
        </Link>
    </section>
</template>
