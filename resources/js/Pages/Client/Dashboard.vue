<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { taskStatusLabels } from '@/Utils/constants.js';
import { formatDate, formatMoney } from '@/Utils/formatters.js';
import { ArrowUpRightIcon, BanknotesIcon, CheckCircleIcon, ClockIcon, PlusIcon, SparklesIcon } from '@heroicons/vue/24/outline';
import { useAssistant } from '@/Composables/useAssistant';

const props = defineProps({
    taskStats: Object,
    awaitingReview: Array,
    pendingInvoices: Array,
    upcomingTasks: Array,
    forms: Array,
    scenariosCount: Number,
    customFields: Array,
});

const page = usePage();
const { open } = useAssistant();
const firstName = computed(() => (page.props.auth?.user?.name || '').split(' ')[0]);
const pendingAmount = computed(() => (props.pendingInvoices || []).reduce((sum, invoice) => sum + Number(invoice.amount || 0), 0));
const intake = computed(() => (props.customFields || []).find((field) => field.key === 'intake_status'));
const activeCount = computed(() =>
    Object.entries(props.taskStats || {})
        .filter(([status]) => !['completed', 'rejected_by_admin'].includes(status))
        .reduce((sum, [, count]) => sum + Number(count), 0),
);
</script>

<template>
    <ClientLayout title="Обзор">
        <section class="relative mb-5 overflow-hidden rounded-[30px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] p-6 sm:p-8">
            <div class="relative z-10 max-w-[560px]">
                <p class="eyebrow">Личный кабинет</p>
                <h2 class="mt-2 font-display text-[26px] font-medium leading-[1.08] tracking-[-0.04em] text-ink sm:text-[33px]">
                    Здравствуйте, {{ firstName }}.
                </h2>
                <p class="mt-3 text-[13px] leading-relaxed text-ink-soft">
                    Здесь видно, что происходит с вашими задачами и счетами. Если что-то непонятно — спросите Кики.
                </p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Link :href="route('client.tasks.create')"><Button><PlusIcon class="h-4 w-4" />Новая заявка</Button></Link>
                    <Button variant="secondary" @click="open"><SparklesIcon class="h-4 w-4 text-primary-600" />Спросить Кики</Button>
                </div>
                <div v-if="intake" class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/70 bg-white/55 px-3 py-1.5 text-[10px] text-ink-soft backdrop-blur">
                    <span class="h-1.5 w-1.5 rounded-full bg-success"></span>
                    {{ intake.label }}: <strong class="text-ink">{{ intake.display || '—' }}</strong>
                </div>
            </div>
        </section>

        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <StatCard label="Активные задачи" :value="activeCount" hint="в работе и на проверке" :icon="ClockIcon" tone="violet" />
            <StatCard label="Ждут вашей оценки" :value="awaitingReview?.length || 0" hint="можно принять или вернуть" :icon="CheckCircleIcon" tone="peach" />
            <StatCard label="К оплате" :value="formatMoney(pendingAmount)" :hint="`${pendingInvoices?.length || 0} счетов`" :icon="BanknotesIcon" tone="sage" mono />
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1.4fr_1fr]">
            <Card :padded="false">
                <template #header>
                    <div class="flex w-full items-center justify-between">
                        <div><p class="eyebrow">Работа</p><h2 class="mt-1 text-[14px] font-bold text-ink">Мои задачи</h2></div>
                        <Link :href="route('client.tasks.index')" class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline">Все <ArrowUpRightIcon class="h-3.5 w-3.5" /></Link>
                    </div>
                </template>
                <div v-if="!upcomingTasks?.length" class="p-2">
                    <EmptyState compact variant="flight" :show-avatar="false" title="Пока нет активных задач" text="Создайте заявку по шаблону — это быстрее, чем описывать всё словами.">
                        <Link :href="route('client.tasks.create')"><Button size="sm">Создать заявку</Button></Link>
                    </EmptyState>
                </div>
                <div v-else class="divide-y divide-line/60">
                    <Link v-for="task in upcomingTasks" :key="task.id" :href="route('client.tasks.show', task.id)" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-ink/[0.025]">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-primary-700"><ClockIcon class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] font-semibold text-ink">{{ task.title }}</span>
                            <span class="mt-0.5 block text-[10px] text-muted">{{ task.deadline ? `до ${formatDate(task.deadline)}` : 'срок не задан' }}</span>
                        </span>
                        <Badge :status="task.status" type="task">{{ taskStatusLabels[task.status] }}</Badge>
                    </Link>
                </div>
            </Card>

            <Card :padded="false">
                <template #header>
                    <div class="flex w-full items-center justify-between">
                        <div><p class="eyebrow">Оплата</p><h2 class="mt-1 text-[14px] font-bold text-ink">Счета</h2></div>
                        <Link :href="route('client.invoices.index')" class="text-[11px] font-semibold text-primary-700 hover:underline">Все</Link>
                    </div>
                </template>
                <div v-if="!pendingInvoices?.length" class="px-5 py-8 text-center text-[11px] text-muted">Неоплаченных счетов нет.</div>
                <div v-else class="divide-y divide-line/60">
                    <Link v-for="invoice in pendingInvoices" :key="invoice.id" :href="route('client.invoices.show', invoice.id)" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-ink/[0.025]">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] font-semibold text-ink">{{ invoice.description }}</span>
                            <span class="mt-0.5 block text-[10px] text-muted">{{ invoice.task?.title || 'без задачи' }}</span>
                        </span>
                        <span class="font-mono text-[12px] font-semibold text-ink">{{ formatMoney(invoice.amount) }}</span>
                    </Link>
                </div>
            </Card>
        </div>

        <div class="mt-4">
            <div class="mb-3 flex items-center justify-between">
                <div><p class="eyebrow">Быстрый старт</p><h2 class="mt-1 text-[15px] font-bold text-ink">Формы заявок</h2></div>
                <div class="flex items-center gap-3">
                    <Link :href="route('client.tasks.scenarios')" class="text-[10px] font-semibold text-muted hover:text-ink">Сценарии</Link>
                    <Link :href="route('client.tasks.create')" class="text-[11px] font-semibold text-primary-700 hover:underline">Все формы</Link>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link v-for="form in forms" :key="form.key" :href="route('client.tasks.create', { form: form.key })" class="surface-card surface-card-hover p-4">
                    <p class="text-[12px] font-bold text-ink">{{ form.name }}</p>
                    <p class="mt-1 line-clamp-2 text-[10px] leading-relaxed text-muted">{{ form.summary }}</p>
                    <p class="mt-3 font-mono text-[9px] text-faint">{{ form.field_count }} полей · {{ form.required_count }} обяз.</p>
                </Link>
            </div>
        </div>

        <div v-if="awaitingReview?.length" class="surface-card mt-4 flex flex-wrap items-center gap-3 p-4">
            <KikiAvatar :size="40" />
            <p class="text-[12px] text-ink-soft">Работа ждёт вашей оценки: <strong class="text-ink">{{ awaitingReview[0].title }}</strong></p>
            <Link :href="route('client.tasks.show', awaitingReview[0].id)" class="ml-auto"><Button size="sm" variant="secondary">Открыть</Button></Link>
        </div>
    </ClientLayout>
</template>
