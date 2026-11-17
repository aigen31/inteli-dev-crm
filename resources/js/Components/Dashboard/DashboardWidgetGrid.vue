<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ActivityCalendarWidget from '@/Components/Dashboard/ActivityCalendarWidget.vue';
import UserWorkloadWidget from '@/Components/Dashboard/UserWorkloadWidget.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import {
    ArrowUpRightIcon, BanknotesIcon, Bars3Icon,
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
    users: { type: Array, default: () => [] },
    currentUserId: { type: String, default: null },
    heading: { type: String, default: 'Рабочий стол' },
    hint: { type: String, default: 'Перетаскивайте виджеты, меняйте размер и собирайте свой обзор.' },
});

const emit = defineEmits(['update:layout', 'reset']);
const catalogOpen = ref(false);
const draggingId = ref(null);
const grid = ref(null);
const gridWidth = ref(0);
const columns = ref(4);
const resizing = ref(null);

const catalog = [
    { id: 'clients', title: 'Клиенты', description: 'Активные аккаунты', icon: UserGroupIcon, w: 1, h: 1, roles: ['admin'] },
    { id: 'active_tasks', title: 'Задачи в работе', description: 'Активные задачи и просрочки', icon: ClockIcon, w: 1, h: 1 },
    { id: 'revenue', title: 'Доход за месяц', description: 'Оплаченные счета за месяц', icon: BanknotesIcon, w: 1, h: 1, roles: ['admin'] },
    { id: 'focus', title: 'Фокус', description: 'Сегодняшние и ближайшие задачи', icon: CheckCircleIcon, w: 2, h: 2 },
    { id: 'activity', title: 'Движение', description: 'Последние изменения задач', icon: ClockIcon, w: 2, h: 2 },
    { id: 'deadlines', title: 'Дедлайны', description: 'Ближайшие сроки', icon: ClockIcon, w: 2, h: 1 },
    { id: 'quick_links', title: 'Быстрые ссылки', description: 'Клиенты, проекты и шаблоны', icon: UserGroupIcon, w: 2, h: 1, roles: ['admin'] },
    { id: 'activity_calendar', title: 'Календарь активности', description: 'Мои задачи по датам', icon: ClockIcon, w: 2, h: 3 },
    { id: 'user_workload', title: 'Нагрузка сотрудника', description: 'Календарь конкретного сотрудника', icon: UserGroupIcon, w: 2, h: 3, roles: ['admin'] },
];
const page = usePage();
const role = computed(() => page.props.auth?.user?.role ?? 'admin');
const availableCatalog = computed(() => catalog.filter((item) => !item.roles || item.roles.includes(role.value)));
const meta = (id) => catalog.find((item) => item.id === id) || catalog[0];
const activeIds = computed(() => props.layout.map((item) => item.id));
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
const GAP = 12;
const desktopRow = 104;
const mobileRow = 136;
const rowHeight = computed(() => (columns.value === 1 ? mobileRow : desktopRow));
const placements = computed(() => {
    const cols = columns.value;
    const rows = [];
    const free = (row, col, w, h) => {
        for (let r = row; r < row + h; r += 1) {
            for (let c = col; c < col + w; c += 1) {
                if (rows[r]?.[c]) return false;
            }
        }
        return true;
    };
    const occupy = (row, col, w, h) => {
        for (let r = row; r < row + h; r += 1) {
            rows[r] ||= [];
            for (let c = col; c < col + w; c += 1) rows[r][c] = true;
        }
    };
    const placed = [];
    props.layout.forEach((item) => {
        const w = Math.min(cols, Math.max(1, Number(item.w) || 1));
        const h = Math.min(4, Math.max(1, Number(item.h) || 1));
        let row = 0;
        let col = 0;
        while (true) {
            if (col + w > cols) { row += 1; col = 0; continue; }
            if (free(row, col, w, h)) { occupy(row, col, w, h); placed.push({ id: item.id, row, col, w, h }); break; }
            col += 1;
        }
    });
    return { placed, rows: rows.length };
});
const colWidth = computed(() => {
    const width = gridWidth.value || 1;
    return (width - GAP * (columns.value - 1)) / columns.value;
});
const gridHeight = computed(() => placements.value.rows * rowHeight.value + Math.max(0, placements.value.rows - 1) * GAP);
const placement = (id) => placements.value.placed.find((item) => item.id === id) || { row: 0, col: 0, w: 1, h: 1 };
function styleFor(item) {
    const p = placement(item.id);
    const active = resizing.value?.id === item.id;
    const previewW = active ? resizing.value.previewW : p.w;
    const previewH = active ? resizing.value.previewH : p.h;
    return {
        position: 'absolute',
        top: '0px',
        left: '0px',
        width: `${previewW * colWidth.value + (previewW - 1) * GAP}px`,
        height: `${previewH * rowHeight.value + (previewH - 1) * GAP}px`,
        transform: `translate(${p.col * (colWidth.value + GAP)}px, ${p.row * (rowHeight.value + GAP)}px)`,
        transition: active ? 'none' : 'transform 260ms cubic-bezier(.2,.8,.2,1), width 260ms cubic-bezier(.2,.8,.2,1), height 260ms cubic-bezier(.2,.8,.2,1)',
    };
}
function startResize(event, item) {
    event.preventDefault();
    event.stopPropagation();
    resizing.value = { id: item.id, startX: event.clientX, startY: event.clientY, startW: item.w, startH: item.h, previewW: item.w, previewH: item.h, item };
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'nwse-resize';
    window.addEventListener('pointermove', onResize);
    window.addEventListener('pointerup', endResize);
}
function onResize(event) {
    if (!resizing.value) return;
    const { id, startX, startY, startW, startH } = resizing.value;
    const rawW = Math.min(4, Math.max(1, startW + (event.clientX - startX) / (colWidth.value + GAP)));
    const rawH = Math.min(4, Math.max(1, startH + (event.clientY - startY) / (rowHeight.value + GAP)));
    resizing.value = { ...resizing.value, previewW: rawW, previewH: rawH };
    // Snap earlier than the midpoint: 25% into the next column/row is enough
    // for the neighbours to start moving while the active edge is still dragged.
    const w = Math.min(4, Math.max(1, Math.floor(rawW + 0.75)));
    const h = Math.min(4, Math.max(1, Math.floor(rawH + 0.75)));
    const current = props.layout.find((item) => item.id === id);
    if (!current || (current.w === w && current.h === h)) return;
    update(props.layout.map((item) => item.id === id ? { ...item, w, h } : item));
}
function endResize() {
    resizing.value = null;
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
    window.removeEventListener('pointermove', onResize);
    window.removeEventListener('pointerup', endResize);
}
let observer;
onMounted(() => {
    if (!grid.value) return;
    observer = new ResizeObserver((entries) => {
        gridWidth.value = entries[0]?.contentRect.width || 0;
        columns.value = gridWidth.value >= 900 ? 4 : gridWidth.value >= 600 ? 2 : 1;
    });
    observer.observe(grid.value);
});
onBeforeUnmount(() => {
    observer?.disconnect();
    window.removeEventListener('pointermove', onResize);
    window.removeEventListener('pointerup', endResize);
});

function startDrag(widget, event) {
    draggingId.value = widget.id;
    if (event?.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', widget.id);
    }
}

function onDragEnter(index) {
    if (!draggingId.value) return;
    const from = props.layout.findIndex((item) => item.id === draggingId.value);
    if (from === -1 || from === index) return;
    const next = [...props.layout];
    const [moved] = next.splice(from, 1);
    const insertAt = from < index ? index - 1 : index;
    if (insertAt === from) return;
    next.splice(insertAt, 0, moved);
    update(next);
}

function endDrag() {
    draggingId.value = null;
}
</script>

<template>
    <section class="mt-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <p class="eyebrow">{{ heading }}</p>
                <p class="mt-1 text-[11px] text-muted">{{ hint }}</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white/55 px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700" @click="catalogOpen = true">
                    <PlusIcon class="h-3.5 w-3.5" />Виджеты
                </button>
            </div>
        </div>

        <div ref="grid" class="relative w-full transition-[height] duration-[260ms] ease-[cubic-bezier(.2,.8,.2,1)]" :style="{ height: `${gridHeight}px` }">
            <article
                v-for="(widget, index) in layout"
                :key="widget.id"
                :draggable="true"
                :class="['group absolute flex flex-col overflow-visible rounded-[22px] border border-line/80 bg-surface shadow-[0_8px_24px_rgba(28,24,35,.035)] transition-[opacity,box-shadow,border-color] duration-200 hover:border-line-strong', draggingId === widget.id && 'z-20 opacity-45 ring-2 ring-primary-300 shadow-[0_18px_45px_rgba(83,61,130,.18)]']"
                :style="styleFor(widget)"
                @dragstart="startDrag(widget, $event)"
                @dragenter.prevent="onDragEnter(index)"
                @dragover.prevent
                @drop.prevent="endDrag"
                @dragend="endDrag"
            >
                <div class="flex shrink-0 items-center gap-2 border-b border-line/65 px-3.5 py-2.5">
                    <Bars3Icon class="h-3.5 w-3.5 shrink-0 cursor-grab text-faint active:cursor-grabbing" />
                    <p class="min-w-0 flex-1 truncate text-[10px] font-bold text-ink-soft">{{ meta(widget.id).title }}</p>
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
                    <UserWorkloadWidget
                        v-else-if="widget.id === 'user_workload'"
                        :users="users"
                        :current-user-id="currentUserId"
                        :compact="short(widget)"
                    />
                    <div v-else-if="widget.id === 'quick_links'" class="flex h-full flex-col justify-center gap-1.5">
                        <Link :href="route('admin.clients.index')" class="flex items-center gap-2 rounded-[9px] px-2 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:bg-primary-soft/40"><UserGroupIcon class="h-3.5 w-3.5 text-primary-500" />Клиенты и заявки <ArrowUpRightIcon class="ml-auto h-3 w-3 text-faint" /></Link>
                        <Link :href="route('admin.projects.index')" class="flex items-center gap-2 rounded-[9px] px-2 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:bg-primary-soft/40"><Squares2X2Icon class="h-3.5 w-3.5 text-sky" />Проекты <ArrowUpRightIcon class="ml-auto h-3 w-3 text-faint" /></Link>
                        <Link :href="route('admin.forms.index')" class="flex items-center gap-2 rounded-[9px] px-2 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:bg-primary-soft/40"><Squares2X2Icon class="h-3.5 w-3.5 text-accent" />Шаблоны <ArrowUpRightIcon class="ml-auto h-3 w-3 text-faint" /></Link>
                    </div>
                </div>
                <button type="button" class="absolute bottom-1 right-1 flex h-4 w-4 cursor-nwse-resize items-end justify-end p-0.5 text-faint opacity-30 transition hover:text-primary-600 group-hover:opacity-100" :aria-label="`Изменить размер ${meta(widget.id).title}`" @pointerdown="startResize($event, widget)">
                    <svg viewBox="0 0 12 12" class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M11 5 5 11M11 9l-2 2" stroke-linecap="round"/></svg>
                </button>
            </article>
        </div>

        <div v-if="!layout.length" class="mt-4 rounded-[20px] border border-dashed border-line px-5 py-8 text-center">
            <p class="text-[12px] font-semibold text-ink">Рабочий стол пуст</p>
            <p class="mt-1 text-[10px] text-muted">Добавьте виджеты из каталога.</p>
            <Button class="mt-3" size="sm" @click="catalogOpen = true"><PlusIcon class="h-3.5 w-3.5" />Открыть каталог</Button>
        </div>
    </section>

    <Teleport to="body">
        <div v-if="catalogOpen" class="fixed inset-0 z-[90] flex justify-end bg-black/30 backdrop-blur-[2px]" @click.self="catalogOpen = false">
            <aside class="flex h-full w-full max-w-[430px] flex-col border-l border-line bg-surface shadow-[-24px_0_70px_rgba(16,13,24,.16)]">
                <header class="relative overflow-hidden border-b border-line bg-surface-2/70 px-5 pb-5 pt-5 text-ink">
                    <div class="pointer-events-none absolute -right-10 -top-12 h-36 w-36 rounded-full bg-primary-400/25 blur-2xl"></div>
                    <div class="relative flex items-start gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-[13px] bg-primary-soft text-primary-700"><Squares2X2Icon class="h-5 w-5" /></span><div class="flex-1"><p class="font-mono text-[8px] uppercase tracking-[0.16em] text-muted">Каталог</p><h2 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em]">Виджеты обзора</h2><p class="mt-1 text-[10px] text-muted">Добавьте нужное — размер можно изменить после.</p></div><button type="button" class="rounded-full p-1.5 text-muted hover:bg-ink/[0.06] hover:text-ink" aria-label="Закрыть каталог" @click="catalogOpen = false"><XMarkIcon class="h-4 w-4" /></button></div>
                </header>
                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
                    <div v-for="item in availableCatalog" :key="item.id" :class="['flex items-center gap-3 rounded-[15px] border p-3', activeIds.includes(item.id) ? 'border-line/60 bg-surface-2/45 opacity-65' : 'border-line bg-white/45']">
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
