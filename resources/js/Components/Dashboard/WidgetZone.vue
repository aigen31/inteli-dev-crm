<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';
import DashboardWidgetGrid from '@/Components/Dashboard/DashboardWidgetGrid.vue';
import { defaultWidgetLayout, normalizeWidgetLayout } from '@/Utils/dashboardWidgets';
import { Squares2X2Icon, XMarkIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const open = ref(false);
const loading = ref(false);
const failed = ref(false);
const data = ref(null);
const layout = ref(normalizeWidgetLayout(page.props.auth?.user?.widget_zone_layout));
const closeButton = ref(null);
let saveTimer = null;

function lockBody(lock) {
    document.body.style.overflow = lock ? 'hidden' : '';
}

async function openZone() {
    open.value = true;
    lockBody(true);
    await nextTick();
    closeButton.value?.focus();
    if (data.value) return;
    loading.value = true;
    failed.value = false;
    try {
        const response = await axios.get(route('admin.widget-zone.data'));
        data.value = response.data;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function closeZone() {
    open.value = false;
    lockBody(false);
}

function save(next) {
    layout.value = next;
    window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
        axios.patch(route('admin.widget-zone.layout'), { widgets: layout.value }).catch(() => {});
    }, 500);
}

function reset() {
    save(defaultWidgetLayout.map((item) => ({ ...item })));
}

function onKeydown(event) {
    if (event.key === 'Escape' && open.value) closeZone();
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    window.clearTimeout(saveTimer);
    lockBody(false);
});
</script>

<template>
    <button type="button" class="fixed bottom-5 right-5 z-40 inline-flex items-center gap-2 rounded-full border border-primary-200/70 bg-surface/90 px-4 py-2.5 text-[10px] font-bold text-primary-800 shadow-[0_12px_30px_rgba(83,61,130,.16)] backdrop-blur-xl transition hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-[0_16px_36px_rgba(83,61,130,.22)] sm:bottom-6 sm:right-6" aria-label="Открыть зону виджетов" @click="openZone">
        <Squares2X2Icon class="h-4 w-4 text-primary-600" />
        <span class="hidden sm:inline">Зона виджетов</span>
    </button>

    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="open" class="fixed inset-0 z-[75] overflow-y-auto bg-[#f4f0e8]" role="dialog" aria-modal="true" aria-label="Зона виджетов">
                <div class="pointer-events-none fixed inset-x-0 top-0 h-[420px] bg-gradient-to-b from-primary-100/50 via-[#f4f0e8]/60 to-transparent"></div>
                <header class="sticky top-0 z-20 border-b border-line/60 bg-[#f4f0e8]/85 backdrop-blur-xl">
                    <div class="mx-auto flex max-w-[1320px] items-start justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[13px] bg-ink text-canvas shadow-sm"><Squares2X2Icon class="h-5 w-5" /></span>
                            <div>
                                <p class="font-mono text-[8px] uppercase tracking-[0.16em] text-primary-700">Личный холст</p>
                                <h2 class="mt-1 font-display text-[22px] font-medium tracking-[-0.04em] text-ink sm:text-[28px]">Зона виджетов</h2>
                                <p class="mt-1 max-w-[520px] text-[10px] leading-relaxed text-muted">Любой виджет из каталога, свободная сетка и доступ из любого раздела. Раскладка сохраняется отдельно от обзора.</p>
                            </div>
                        </div>
                        <button ref="closeButton" type="button" class="rounded-full border border-line bg-white/60 p-2 text-muted transition hover:border-line-strong hover:text-ink" aria-label="Закрыть зону виджетов" @click="closeZone"><XMarkIcon class="h-4 w-4" /></button>
                    </div>
                </header>

                <main class="relative mx-auto max-w-[1320px] px-4 pb-16 pt-4 sm:px-6">
                    <div v-if="loading" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="i in 8" :key="i" class="h-[104px] animate-pulse rounded-[22px] border border-line/50 bg-white/40" :class="i === 5 || i === 6 ? 'sm:col-span-2 lg:col-span-2 lg:row-span-2 lg:h-[220px]' : ''"></div>
                    </div>
                    <div v-else-if="failed" class="rounded-[22px] border border-danger/20 bg-danger/5 px-5 py-10 text-center">
                        <p class="text-[12px] font-bold text-ink">Не удалось загрузить виджеты</p>
                        <p class="mt-1 text-[10px] text-muted">Проверьте соединение и попробуйте открыть зону снова.</p>
                        <button type="button" class="mt-3 rounded-full bg-ink px-4 py-2 text-[10px] font-semibold text-canvas" @click="data = null; openZone()">Повторить</button>
                    </div>
                    <DashboardWidgetGrid
                        v-else-if="data"
                        :layout="layout"
                        :counters="data.counters"
                        :monthly-revenue="data.monthlyRevenue"
                        :focus-tasks="data.focusTasks"
                        :recent-history="data.recentHistory"
                        :pending-invoices="[]"
                        :upcoming-deadlines="data.upcomingDeadlines"
                        heading="Рабочий холст"
                        hint="Добавляйте любые виджеты, перетаскивайте их и меняйте размер перетягиванием за угол."
                        @update:layout="save"
                        @reset="reset"
                    />
                </main>
            </div>
        </Transition>
    </Teleport>
</template>
