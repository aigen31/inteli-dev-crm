<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatDateTime, formatMoney } from '@/Utils/formatters.js';
import {
    ArrowUpRightIcon,
    BanknotesIcon,
    CheckCircleIcon,
    ClockIcon,
    PlusIcon,
    SparklesIcon,
    Squares2X2Icon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
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
});

const page = usePage();
const { open } = useAssistant();

const hour = new Date().getHours();
const greeting = hour < 6 ? 'Доброй ночи' : hour < 12 ? 'Доброе утро' : hour < 18 ? 'Добрый день' : 'Добрый вечер';
const firstName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0]);
const todayLabel = new Date().toLocaleDateString('ru-RU', { weekday: 'long', day: 'numeric', month: 'long' });

const focusTasks = computed(() => (props.todayTasks?.length ? props.todayTasks : props.pendingTasks ?? []));
const pendingTotal = computed(() => (props.pendingInvoices ?? []).reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0));
</script>

<template>
    <AdminLayout title="Обзор">
        <section class="relative mb-6 overflow-hidden rounded-[30px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] p-6 sm:p-8">
            <div class="pointer-events-none absolute -right-20 -top-24 h-72 w-72 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative z-10 max-w-[540px]">
                <p class="eyebrow">{{ todayLabel }}</p>
                <h2 class="mt-2 font-display text-[27px] font-medium leading-[1.08] tracking-[-0.04em] text-ink sm:text-[34px]">
                    {{ greeting }}, {{ firstName }}.
                </h2>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-soft">
                    В работе {{ counters?.active_tasks ?? 0 }} задач, {{ counters?.overdue ?? 0 }} просрочено. Кики собрала главное на сегодня.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('admin.tasks.create')">
                        <Button size="md">
                            <PlusIcon class="h-4 w-4" />
                            Новая задача
                        </Button>
                    </Link>
                    <Link :href="route('admin.forms.index')">
                        <Button variant="secondary" size="md">
                            <Squares2X2Icon class="h-4 w-4" />
                            Формы
                        </Button>
                    </Link>
                    <Button variant="ghost" size="md" @click="open">
                        <SparklesIcon class="h-4 w-4 text-primary-600" />
                        Спросить Кики
                    </Button>
                </div>
            </div>

            <div class="pointer-events-none absolute bottom-0 right-2 hidden h-[230px] w-[360px] sm:block lg:h-[245px] lg:w-[420px]">
                <img src="/images/kiki/kiki-flight-hero.webp" alt="Кики" class="h-full w-full object-contain object-right-bottom" />
            </div>
        </section>

        <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Клиенты" :value="counters?.clients ?? 0" hint="активные аккаунты" :icon="UserGroupIcon" tone="violet" />
            <StatCard label="В работе" :value="counters?.active_tasks ?? 0" :hint="`${counters?.overdue ?? 0} просрочено`" :icon="ClockIcon" tone="peach" />
            <StatCard label="Доход за месяц" :value="formatMoney(monthlyRevenue ?? 0)" hint="оплаченные счета" :icon="BanknotesIcon" tone="sage" mono />
            <StatCard label="Формы" :value="counters?.forms ?? 0" hint="структурированные заявки" :icon="Squares2X2Icon" tone="sky" />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.55fr_1fr]">
            <Card :padded="false">
                <template #header>
                    <div class="flex w-full items-center justify-between gap-3">
                        <div>
                            <p class="eyebrow">Фокус</p>
                            <h2 class="mt-1 text-[15px] font-bold text-ink">Сегодня и ближайшее</h2>
                        </div>
                        <Link :href="route('admin.tasks.index')" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline">
                            Все задачи
                            <ArrowUpRightIcon class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </template>

                <div v-if="!focusTasks.length" class="p-2">
                    <EmptyState compact variant="flight" title="День свободен" text="Новые заявки и задачи появятся здесь. Можно начать с шаблона.">
                        <Link :href="route('admin.forms.index')">
                            <Button variant="secondary" size="sm">Открыть формы</Button>
                        </Link>
                    </EmptyState>
                </div>

                <div v-else class="divide-y divide-line/70">
                    <Link
                        v-for="task in focusTasks"
                        :key="task.id"
                        :href="route('admin.tasks.show', task.id)"
                        class="group flex items-center gap-3 px-5 py-3.5 transition hover:bg-ink/[0.025] sm:px-6"
                    >
                        <span :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-full', task.status === 'completed' ? 'bg-success/10 text-success' : 'bg-primary-soft text-primary-700']">
                            <CheckCircleIcon v-if="task.status === 'completed'" class="h-4 w-4" />
                            <ClockIcon v-else class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-semibold text-ink group-hover:text-primary-800">{{ task.title }}</span>
                            <span class="mt-0.5 block truncate text-[11px] text-muted">
                                {{ task.client?.name || 'Без клиента' }}
                                <span v-if="task.deadline"> · до {{ formatDate(task.deadline) }}</span>
                            </span>
                        </span>
                        <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                    </Link>
                </div>
            </Card>

            <div class="space-y-4">
                <Card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="eyebrow">Деньги</p>
                            <p class="mt-2 font-display text-[26px] font-medium tracking-[-0.04em] text-ink">{{ formatMoney(monthlyRevenue ?? 0) }}</p>
                            <p class="mt-1 text-[10px] text-muted">оплачено в этом месяце</p>
                        </div>
                        <span class="flex h-9 w-9 items-center justify-center rounded-[12px] bg-sage/15 text-[#4f7b5c]">
                            <BanknotesIcon class="h-4 w-4" />
                        </span>
                    </div>
                    <div class="mt-4 rounded-2xl bg-surface-2/60 px-3.5 py-3">
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="text-muted">Ждут оплаты</span>
                            <span class="font-mono font-semibold text-ink">{{ pendingInvoices?.length ?? 0 }}</span>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[11px]">
                            <span class="text-muted">Сумма</span>
                            <span class="font-mono font-semibold text-ink">{{ formatMoney(pendingTotal) }}</span>
                        </div>
                    </div>
                    <Link :href="route('admin.invoices.index')" class="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline">
                        Перейти к счетам
                        <ArrowUpRightIcon class="h-3.5 w-3.5" />
                    </Link>
                </Card>

                <Card :padded="false">
                    <template #header>
                        <div>
                            <p class="eyebrow">Движение</p>
                            <h2 class="mt-1 text-[14px] font-bold text-ink">Последние изменения</h2>
                        </div>
                    </template>
                    <div v-if="!recentHistory?.length" class="px-5 py-6 text-[11px] text-muted">Пока тихо.</div>
                    <div v-else class="divide-y divide-line/60">
                        <div v-for="item in recentHistory" :key="item.id" class="px-5 py-3">
                            <p class="truncate text-[12px] font-medium text-ink">{{ item.task?.title || 'Задача' }}</p>
                            <p class="mt-0.5 text-[10px] text-muted">
                                {{ item.changed_by?.name || 'Система' }} · {{ item.new_status ? taskStatusLabels[item.new_status] || item.new_status : 'обновление' }}
                            </p>
                            <p class="mt-0.5 font-mono text-[9px] text-faint">{{ formatDateTime(item.created_at) }}</p>
                        </div>
                    </div>
                </Card>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Link :href="route('admin.task-templates.create', { ai: 1 })" class="surface-card surface-card-hover flex items-center gap-4 p-5">
                <KikiAvatar :size="52" />
                <span class="min-w-0">
                    <span class="block text-[13px] font-bold text-ink">Собрать сценарий с Кики</span>
                    <span class="mt-0.5 block text-[11px] text-muted">Опишите идею одной фразой — получите структуру и чек-лист.</span>
                </span>
                <ArrowUpRightIcon class="ml-auto h-4 w-4 shrink-0 text-primary-600" />
            </Link>
            <Link :href="route('admin.clients.index')" class="surface-card surface-card-hover flex items-center gap-4 p-5">
                <span class="flex h-[52px] w-[52px] items-center justify-center rounded-full bg-primary-soft text-primary-700">
                    <UserGroupIcon class="h-5 w-5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-[13px] font-bold text-ink">Клиенты и заявки</span>
                    <span class="mt-0.5 block text-[11px] text-muted">Статус приёма заявок, поля и история — в одной карточке.</span>
                </span>
                <ArrowUpRightIcon class="ml-auto h-4 w-4 shrink-0 text-primary-600" />
            </Link>
        </div>
    </AdminLayout>
</template>
