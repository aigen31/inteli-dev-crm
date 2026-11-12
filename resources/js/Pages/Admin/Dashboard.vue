<script setup>
import { computed, ref, watch } from 'vue';
import axios from 'axios';
import { localeTag } from '@/i18n';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import NavigationTrail from '@/Components/Navigation/NavigationTrail.vue';
import DashboardWidgetGrid from '@/Components/Dashboard/DashboardWidgetGrid.vue';
import { PlusIcon, Squares2X2Icon, SparklesIcon } from '@heroicons/vue/24/outline';
import { useAssistant } from '@/Composables/useAssistant';

const props = defineProps({
    taskStats: Object,
    pendingTasks: Array,
    pendingInvoices: Array,
    monthlyRevenue: Number,
    upcomingDeadlines: Array,
    todayTasks: Array,
    recentHistory: Array,
    counters: Object,
    navigation: { type: Object, default: () => ({ recent: [] }) },
});

const page = usePage();
const { open } = useAssistant();
const hour = new Date().getHours();
const greeting = hour < 6 ? 'Доброй ночи' : hour < 12 ? 'Доброе утро' : hour < 18 ? 'Добрый день' : 'Добрый вечер';
const firstName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0]);
const todayLabel = new Date().toLocaleDateString(localeTag(), { weekday: 'long', day: 'numeric', month: 'long' });
const focusTasks = computed(() => (props.todayTasks?.length ? props.todayTasks : props.pendingTasks ?? []));

const defaultLayout = [
    { id: 'clients', w: 1, h: 1 },
    { id: 'active_tasks', w: 1, h: 1 },
    { id: 'revenue', w: 1, h: 1 },
    { id: 'templates', w: 1, h: 1 },
    { id: 'focus', w: 2, h: 2 },
    { id: 'activity', w: 2, h: 2 },
    { id: 'deadlines', w: 2, h: 1 },
    { id: 'quick_links', w: 2, h: 1 },
    { id: 'activity_calendar', w: 2, h: 2 },
];
const allowed = new Set(defaultLayout.map((item) => item.id));
function normalize(value) {
    if (!Array.isArray(value)) return defaultLayout;
    const seen = new Set();
    const result = [];
    value.forEach((row) => {
        if (!row || !allowed.has(row.id) || seen.has(row.id)) return;
        seen.add(row.id);
        result.push({ id: row.id, w: Math.min(4, Math.max(1, Number(row.w) || 1)), h: Math.min(4, Math.max(1, Number(row.h) || 1)) });
    });
    return result;
}
const layout = ref(normalize(page.props.auth?.user?.dashboard_layout));
let saveTimer = null;
function save(next) {
    layout.value = next;
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
        axios.patch(route('dashboard.layout'), { widgets: layout.value }).catch(() => {});
    }, 500);
}
watch(layout, (next) => {
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
        axios.patch(route('dashboard.layout'), { widgets: next }).catch(() => {});
    }, 500);
}, { deep: true });
function reset() {
    save(defaultLayout.map((item) => ({ ...item })));
}
</script>

<template>
    <AdminLayout title="Обзор">
        <section class="relative mb-6 min-h-[260px] overflow-hidden rounded-[30px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] p-6 sm:min-h-[290px] sm:p-8 xl:min-h-[320px] 2xl:min-h-[350px]">
            <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative z-10 max-w-[560px]">
                <p class="eyebrow">{{ todayLabel }}</p>
                <h2 class="mt-2 font-display text-[27px] font-medium leading-[1.08] tracking-[-0.04em] text-ink sm:text-[34px]">{{ greeting }}, {{ firstName }}.</h2>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-soft">В работе {{ counters?.active_tasks ?? 0 }} задач, {{ counters?.overdue ?? 0 }} просрочено. Соберите обзор из виджетов ниже.</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('admin.tasks.create')"><Button size="md"><PlusIcon class="h-4 w-4" />Новая задача</Button></Link>
                    <Link :href="route('admin.forms.index')"><Button variant="secondary" size="md"><Squares2X2Icon class="h-4 w-4" />Шаблоны</Button></Link>
                    <Button variant="ghost" size="md" @click="open"><SparklesIcon class="h-4 w-4 text-primary-600" />Спросить Кики</Button>
                </div>
            </div>
            <div class="pointer-events-none absolute bottom-0 right-2 hidden h-full w-[360px] sm:block sm:w-[400px] lg:w-[430px] xl:w-[480px] 2xl:w-[520px]">
                <img src="/images/kiki/kiki-dashboard-mug.webp" alt="Кики с большой кружкой" class="h-full w-full object-contain object-right-bottom" />
            </div>
        </section>

        <NavigationTrail :items="navigation?.recent || []" />

        <DashboardWidgetGrid
            :layout="layout"
            :counters="counters"
            :monthly-revenue="monthlyRevenue"
            :focus-tasks="focusTasks"
            :recent-history="recentHistory"
            :pending-invoices="pendingInvoices"
            :upcoming-deadlines="upcomingDeadlines"
            @update:layout="save"
            @reset="reset"
        />
    </AdminLayout>
</template>
