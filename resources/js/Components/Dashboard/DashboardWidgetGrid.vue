<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ActivityCalendarWidget from '@/Components/Dashboard/ActivityCalendarWidget.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import {
    AdjustmentsHorizontalIcon, ArrowUpRightIcon, BanknotesIcon, Bars3Icon,
    CheckCircleIcon, ClockIcon, PlusIcon, Squares2X2Icon, UserGroupIcon, XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    layout: { type: Array, default: () => [] },
    counters: Object,
    monthlyRevenue: Number,
    focusTasks: Array,
    recentHistory: Array,
    pendingInvoices: Array,
    upcomingDeadlines: Array,
});

const emit = defineEmits(['update:layout', 'reset']);
const catalogOpen = ref(false);
const sizeMenu = ref(null);
const dragIndex = ref(null);

const catalog = [
    { id: 'clients', title: 'Клиенты', description: 'Активные аккаунты', icon: UserGroupIcon, w: 1, h: 1 },
    { id: 'active_tasks', title: 'Задачи в работе', description: 'Активные задачи и просрочки', icon: ClockIcon, w: 1, h: 1 },
    { id: 'revenue', title: 'Доход за месяц', description: 'Оплаченные счета за месяц', icon: BanknotesIcon, w: 1, h: 1 },
    { id: 'templates', title: 'Шаблоны', description: 'Активные шаблоны заявок', icon: Squares2X2Icon, w: 1, h: 1 },
    { id: 'focus', title: 'Фокус', description: 'Сегодняшние и ближайшие задачи', icon: CheckCircleIcon, w: 2, h: 2 },
    { id: 'activity', title: 'Движение', description: 'Последние изменения задач', icon: ClockIcon, w: 2, h: 2 },
    { id: 'deadlines', title: 'Дедлайны', description: 'Ближайшие сроки', icon: ClockIcon, w: 2, h: 1 },
    { id: 'quick_links', title: 'Быстрые ссылки', description: 'Клиенты, проекты и шаблоны', icon: UserGroupIcon, w: 2, h: 1 },
    { id: 'activity_calendar', title: 'Календарь активности', description: 'Нагрузка по задачам', icon: ClockIcon, w: 2, h: 2 },
];
const meta = (id) => catalog.find((item) => item.id === id) || catalog[0];
const activeIds = computed(() => props.layout.map((item) => item.id));
const colClass = { 1: 'lg:col-span-1', 2: 'lg:col-span-2', 3: 'lg:col-span-3', 4: 'lg:col-span-4' };
const rowClass = { 1: 'lg:row-span-1', 2: 'lg:row-span-2', 3: 'lg:row-span-3', 4: 'lg:row-span-4' };
const compact = (widget) => Number(widget.w) === 1;
const short = (widget) => Number(widget.h) === 1;
const visibleFocus = computed(() => (props.focusTasks || []).slice(0, 6));

function update(widgets) { emit('update:layout', widgets); }
function remove(id) { update(props.layout.filter((item) => item.id !== id)); }
function add(id) {
    if (activeIds.value.includes(id)) return;
    const item = meta(id);
    update([...props.layout, { id, w: item.w, h: item.h }]);
    catalogOpen.value = false;
}
function setSize(id, w, h) { update(props.layout.map((item) => item.id === id ? { ...item, w, h } : item)); sizeMenu.value = null; }
function onDrop(index) {
    if (dragIndex.value === null || dragIndex.value === index) return;
    const next = [...props.layout];
    const [moved] = next.splice(dragIndex.value, 1);
    next.splice(index, 0, moved);
    update(next);
    dragIndex.value = null;
}
function toggleSize(id) { sizeMenu.value = sizeMenu.value === id ? null : id; }
</script>

<template>
    <section class="mt-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="eyebrow">Рабочий стол</p>
                <p class="mt-1 text-[11px] text-muted">Перетаскивайте виджеты, меняйте размер и собирайте свой обзор.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/55 px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700" @click="catalogOpen = true">
                    <PlusIcon class="h-3.5 w-3.5" />Виджеты
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:auto-rows-[104px] lg:grid-cols-4">
            <article
                v-for="(widget, index) in layout"
                :key="widget.id"
                :draggable="true"
                :class="[colClass[widget.w], rowClass[widget.h], 'group relative flex min-h-[150px] flex-col overflow-visible rounded-[22px] border border-line/80 bg-surface shadow-[0_8px_24px_rgba(28,24,35,.035)] transition hover:border-line-strong lg:min-h-0']"
                @dragstart="dragIndex = index"
                @dragover.prevent
                @drop.prevent="onDrop(index)"
                @dragend="dragIndex = null"
            >
                <div class="flex shrink-0 items-center gap-2 border-b border-line/65 px-3.5 py-2.5">
                    <Bars3Icon class="h-3.5 w-3.5 shrink-0 cursor-grab text-faint active:cursor-grabbing" />
                    <p class="min-w-0 flex-1 truncate text-[10px] font-bold text-ink-soft">{{ meta(widget.id).title }}</p>
                    <button type="button" class="rounded-md p-1 text-faint opacity-60 transition hover:bg-ink/[0.06] hover:text-ink group-hover:opacity-100" :aria-label="`Размер ${meta(widget.id).title}`" @click.stop="toggleSize(widget.id)">
                        <AdjustmentsHorizontalIcon class="h-3.5 w-3.5" />
                    </button>
                    <button type="button" class="rounded-md p-1 text-faint opacity-60 transition hover:bg-danger/10 hover:text-danger-ink group-hover:opacity-100" :aria-label="`Удалить ${meta(widget.id).title}`" @click.stop="remove(widget.id)">
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </div>

                <div :class="['min-h-0 flex-1 overflow-hidden', short(widget) ? 'p-2.5' : 'p-3.5']">
                    <div v-if="widget.id === 'clients'" class="flex h-full flex-col justify-between">
                        <div class="flex items-start justify-between gap-2"><p class="font-mono text-[8px] uppercase tracking-[0.1em] text-muted">Клиенты</p><UserGroupIcon class="h-4 w-4 text-primary-500" /></div>
                        <p class="font-display text-[24px] font-medium leading-none tracking-[-0.04em] text-ink">{{ counters?.clients ?? 0 }}</p>
                        <p v-if="!short(widget)" class="text-[9px] text-muted">активные аккаунты</p>
                    </div>
                    <div v-else-if="widget.id === 'active_tasks'" class="flex h-full flex-col justify-between">
                        <div class="flex items-start justify-between gap-2"><p class="font-mono text-[8px] uppercase tracking-[0.1em] text-muted">В работе</p><ClockIcon class="h-4 w-4 text-accent" /></div>
                        <p class="font-display text-[24px] font-medium leading-none tracking-[-0.04em] text-ink">{{ counters?.active_tasks ?? 0 }}</p>
                        <p v-if="!short(widget)" class="text-[9px] text-muted">{{ counters?.overdue ?? 0 }} просрочено</p>
                    </div>
                    <div v-else-if="widget.id === 'revenue'" class="flex h-full flex-col justify-between">
                        <div class="flex items-start justify-between gap-2"><p class="font-mono text-[8px] uppercase tracking-[0.1em] text-muted">Доход за месяц</p><BanknotesIcon class="h-4 w-4 text-[#4f7b5c]" /></div>
                        <p class="font-display text-[24px] font-medium leading-none tracking-[-0.04em] text-ink">{{ formatMoney(monthlyRevenue ?? 0) }}</p>
                        <p v-if="!short(widget)" class="text-[9px] text-muted">оплаченные счета</p>
                    </div>
                    <div v-else-if="widget.id === 'templates'" class="flex h-full flex-col justify-between">
                        <div class="flex items-start justify-between gap-2"><p class="font-mono text-[8px] uppercase tracking-[0.1em] text-muted">Шаблоны</p><Squares2X2Icon class="h-4 w-4 text-sky" /></div>
                        <p class="font-display text-[24px] font-medium leading-none tracking-[-0.04em] text-ink">{{ counters?.forms ?? 0 }}</p>
                        <p v-if="!short(widget)" class="text-[9px] text-muted">структурированные заявки</p>
                    </div>
                    <div v-else-if="widget.id === 'focus'" class="flex h-full flex-col">
                        <div class="flex items-center justify-between gap-2"><p class="eyebrow">Фокус</p><Link :href="route('admin.tasks.index')" class="text-[9px] font-semibold text-primary-700 hover:underline">Все задачи ↗</Link></div>
                        <div v-if="visibleFocus.length" class="mt-2 min-h-0 flex-1 divide-y divide-line/50 overflow-hidden">
                            <Link v-for="task in visibleFocus.slice(0, compact(widget) ? 2 : 5)" :key="task.id" :href="route('admin.tasks.show', task.id)" class="flex items-center gap-2 py-1.5 transition hover:bg-primary-soft/25">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-400"></span>
                                <span class="min-w-0 flex-1 truncate text-[10px] font-semibold text-ink">{{ task.title }}</span>
                                <Badge v-if="!compact(widget)" :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                            </Link>
                        </div>
                        <p v-else class="mt-3 text-[10px] text-muted">На сегодня задач нет.</p>
                    </div>
                    <div v-else-if="widget.id === 'activity'" class="flex h-full flex-col">
                        <p class="eyebrow">Движение</p>
                        <div v-if="recentHistory?.length" class="mt-1 min-h-0 flex-1 divide-y divide-line/50 overflow-hidden">
                            <div v-for="item in recentHistory.slice(0, compact(widget) ? 2 : 4)" :key="item.id" class="py-1.5">
                                <p class="truncate text-[10px] font-semibold text-ink">{{ item.task?.title || 'Задача' }}</p>
                                <p class="truncate text-[8px] text-muted">{{ item.changed_by?.name || 'Система' }} · {{ item.new_status ? taskStatusLabels[item.new_status] || item.new_status : 'обновление' }}</p>
                            </div>
                        </div>
                        <p v-else class="mt-3 text-[10px] text-muted">Пока тихо.</p>
                    </div>
                    <div v-else-if="widget.id === 'deadlines'" class="flex h-full flex-col">
                        <p class="eyebrow">Дедлайны</p>
                        <div v-if="upcomingDeadlines?.length" class="mt-1 min-h-0 flex-1 space-y-1 overflow-hidden">
                            <Link v-for="task in upcomingDeadlines.slice(0, compact(widget) ? 2 : 3)" :key="task.id" :href="route('admin.tasks.show', task.id)" class="flex items-center justify-between gap-2 rounded-[8px] py-1 text-[9px] text-ink-soft hover:bg-primary-soft/30">
                                <span class="min-w-0 truncate">{{ task.title }}</span>
                                <span class="shrink-0 font-mono text-[8px] text-muted">{{ formatDate(task.deadline) }}</span>
                            </Link>
                        </div>
                        <p v-else class="mt-3 text-[10px] text-muted">Ближайших дедлайнов нет.</p>
                    </div>
                    <ActivityCalendarWidget v-else-if="widget.id === 'activity_calendar'" :compact="short(widget)" />
                    <div v-else-if="widget.id === 'quick_links'" class="flex h-full flex-col justify-center gap-1.5">
                        <Link :href="route('admin.clients.index')" class="flex items-center gap-2 rounded-[9px] px-2 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:bg-primary-soft/40"><UserGroupIcon class="h-3.5 w-3.5 text-primary-500" />Клиенты и заявки <ArrowUpRightIcon class="ml-auto h-3 w-3 text-faint" /></Link>
                        <Link :href="route('admin.projects.index')" class="flex items-center gap-2 rounded-[9px] px-2 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:bg-primary-soft/40"><Squares2X2Icon class="h-3.5 w-3.5 text-sky" />Проекты <ArrowUpRightIcon class="ml-auto h-3 w-3 text-faint" /></Link>
                        <Link :href="route('admin.forms.index')" class="flex items-center gap-2 rounded-[9px] px-2 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:bg-primary-soft/40"><Squares2X2Icon class="h-3.5 w-3.5 text-accent" />Шаблоны <ArrowUpRightIcon class="ml-auto h-3 w-3 text-faint" /></Link>
                    </div>
                </div>

                <div v-if="sizeMenu === widget.id" class="absolute right-2 top-11 z-30 w-[180px] rounded-[14px] border border-line bg-surface p-2.5 shadow-[0_18px_40px_rgba(16,13,24,.18)]" @click.stop>
                    <p class="px-1 font-mono text-[8px] uppercase tracking-[0.1em] text-faint">Ширина × высота</p>
                    <div class="mt-2 grid grid-cols-4 gap-1">
                        <template v-for="h in 4" :key="h">
                            <button v-for="w in 4" :key="`${w}-${h}`" type="button" :class="['flex h-7 items-center justify-center rounded-[6px] font-mono text-[8px] transition', widget.w === w && widget.h === h ? 'bg-primary-600 text-white' : 'bg-surface-2 text-muted hover:bg-primary-soft hover:text-primary-800']" :aria-label="`${w} на ${h}`" @click="setSize(widget.id, w, h)">{{ w }}×{{ h }}</button>
                        </template>
                    </div>
                    <button type="button" class="mt-2 w-full rounded-[7px] px-1 py-1 text-left text-[8px] font-semibold text-muted hover:text-ink" @click="remove(widget.id)">Удалить виджет</button>
                </div>
            </article>
        </div>

        <div v-if="!layout.length" class="mt-4 rounded-[20px] border border-dashed border-line px-5 py-8 text-center">
            <p class="text-[12px] font-semibold text-ink">Рабочий стол пуст</p>
            <p class="mt-1 text-[10px] text-muted">Добавьте виджеты из каталога.</p>
            <Button class="mt-3" size="sm" @click="catalogOpen = true"><PlusIcon class="h-3.5 w-3.5" />Открыть каталог</Button>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="catalogOpen" class="fixed inset-0 z-[70] flex justify-end bg-ink/30 backdrop-blur-[2px]" @click.self="catalogOpen = false">
            <aside class="flex h-full w-full max-w-[430px] flex-col border-l border-line bg-surface shadow-[-24px_0_70px_rgba(16,13,24,.16)]">
                <header class="relative overflow-hidden bg-ink px-5 pb-5 pt-5 text-canvas">
                    <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-primary-400/25 blur-2xl"></div>
                    <div class="relative flex items-start gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-[13px] bg-white/10"><Squares2X2Icon class="h-5 w-5" /></span><div class="flex-1"><p class="font-mono text-[8px] uppercase tracking-[0.16em] text-canvas/55">Каталог</p><h2 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em]">Виджеты обзора</h2><p class="mt-1 text-[10px] text-canvas/55">Добавьте нужное — размер можно изменить после.</p></div><button type="button" class="rounded-full p-1.5 text-canvas/55 hover:bg-white/10 hover:text-canvas" aria-label="Закрыть каталог" @click="catalogOpen = false"><XMarkIcon class="h-4 w-4" /></button></div>
                </header>
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                    <div v-for="item in catalog" :key="item.id" :class="['flex items-center gap-3 rounded-[15px] border p-3', activeIds.includes(item.id) ? 'border-line/60 bg-surface-2/45 opacity-65' : 'border-line bg-white/45']">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-primary-soft text-primary-700"><component :is="item.icon" class="h-4 w-4" /></span>
                        <div class="min-w-0 flex-1"><p class="text-[11px] font-bold text-ink">{{ item.title }}</p><p class="mt-0.5 text-[9px] leading-relaxed text-muted">{{ item.description }}</p></div>
                        <span v-if="activeIds.includes(item.id)" class="shrink-0 text-[8px] font-semibold uppercase tracking-[0.08em] text-faint">Добавлен</span>
                        <button v-else type="button" class="shrink-0 rounded-full bg-primary-600 px-2.5 py-1.5 text-[9px] font-semibold text-white transition hover:bg-primary-700" @click="add(item.id)">Добавить</button>
                    </div>
                </div>
                <footer class="flex items-center justify-between border-t border-line/80 px-4 py-3">
                    <button type="button" class="text-[10px] font-semibold text-muted hover:text-ink" @click="emit('reset')">Сбросить к стандарту</button>
                    <span class="font-mono text-[8px] text-faint">{{ layout.length }} на столе</span>
                </footer>
            </aside>
        </div>
    </Teleport>
</template>
