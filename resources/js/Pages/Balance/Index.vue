<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import BalanceSummaryCard from '@/Components/Balance/BalanceSummaryCard.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { ArrowDownLeftIcon, ArrowUpRightIcon, ReceiptPercentIcon, WalletIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    balance: { type: Number, default: 0 },
    transactions: Object,
    summary: { type: Object, default: () => ({ charged: 0, topped_up: 0 }) },
});

const isNegative = computed(() => props.balance < 0);
const items = computed(() => props.transactions?.data || []);

function reasonLabel(transaction) {
    if (transaction.reason === 'task_completed') return 'Списание за завершённую задачу';
    if (transaction.reason === 'manual') return transaction.amount > 0 ? 'Пополнение администратором' : 'Ручная корректировка';

    return 'Операция';
}
</script>

<template>
    <ClientLayout title="Баланс">
        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0 space-y-5">
                <BalanceSummaryCard :balance="balance" :transactions="items.slice(0, 3)" />

                <section class="surface-card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line/70 px-5 py-4 sm:px-6">
                        <div>
                            <p class="eyebrow">История</p>
                            <h2 class="mt-1 text-[15px] font-bold text-ink">Операции по балансу</h2>
                        </div>
                        <span class="rounded-full border border-line bg-surface-2/60 px-2.5 py-1 font-mono text-[9px] uppercase tracking-[0.1em] text-muted">
                            Операций: {{ transactions?.total || 0 }}
                        </span>
                    </div>

                    <div v-if="items.length" class="divide-y divide-line/70">
                        <article v-for="transaction in items" :key="transaction.id" class="flex items-start gap-3 px-5 py-4 transition hover:bg-ink/[0.02] sm:px-6">
                            <span :class="['mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px]', transaction.amount < 0 ? 'bg-danger/10 text-danger-ink' : 'bg-success/10 text-success']">
                                <ArrowUpRightIcon v-if="transaction.amount < 0" class="h-4 w-4" />
                                <ArrowDownLeftIcon v-else class="h-4 w-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[12px] font-semibold text-ink">{{ transaction.description || reasonLabel(transaction) }}</p>
                                <p class="mt-0.5 text-[10px] text-muted">{{ reasonLabel(transaction) }} · {{ formatDateTime(transaction.created_at) }}</p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    <Link
                                        v-if="transaction.task"
                                        :href="route('client.tasks.show', transaction.task.id)"
                                        class="inline-flex items-center gap-1 rounded-full border border-line bg-surface-2/50 px-2 py-0.5 text-[9px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700"
                                    >
                                        <ReceiptPercentIcon class="h-3 w-3" />{{ transaction.task.title }}
                                    </Link>
                                    <span v-if="transaction.author" class="text-[9px] text-faint">Изменил: {{ transaction.author }}</span>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p :class="['font-mono text-[12px] font-semibold', transaction.amount < 0 ? 'text-danger-ink' : 'text-success']">
                                    {{ transaction.amount > 0 ? '+' : '' }}{{ formatMoney(transaction.amount) }}
                                </p>
                                <p class="mt-0.5 font-mono text-[9px] text-faint">остаток {{ formatMoney(transaction.balance_after) }}</p>
                            </div>
                        </article>
                    </div>

                    <div v-else class="px-6 py-14 text-center">
                        <img src="/images/kiki/kiki-invoices-coin.webp" alt="" class="mx-auto h-24 w-24 object-contain opacity-90" />
                        <p class="mt-3 text-[14px] font-bold text-ink">Операций пока нет</p>
                        <p class="mx-auto mt-1 max-w-[320px] text-[11px] leading-relaxed text-muted">
                            Баланс изменится, когда администратор его пополнит или завершится задача со стоимостью.
                        </p>
                        <Link :href="route('client.tasks.index')" class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-line bg-surface px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700">
                            К задачам
                        </Link>
                    </div>

                    <div v-if="transactions?.links?.length > 3" class="border-t border-line/70 px-5 py-3 sm:px-6">
                        <Pagination :links="transactions.links" />
                    </div>
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card p-5">
                    <p class="eyebrow">За этот месяц</p>
                    <dl class="mt-3 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[11px] text-muted">Пополнения</dt>
                            <dd class="font-mono text-[12px] font-semibold text-success">+{{ formatMoney(summary.topped_up) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-[11px] text-muted">Списания</dt>
                            <dd class="font-mono text-[12px] font-semibold text-danger-ink">{{ formatMoney(summary.charged) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-t border-line/70 pt-3">
                            <dt class="text-[11px] font-semibold text-ink">Текущий баланс</dt>
                            <dd :class="['font-mono text-[13px] font-bold', isNegative ? 'text-danger-ink' : 'text-ink']">{{ formatMoney(balance) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="surface-card p-5">
                    <p class="inline-flex items-center gap-1.5 text-[11px] font-bold text-ink"><WalletIcon class="h-3.5 w-3.5 text-primary-600" />Как это работает</p>
                    <ul class="mt-3 space-y-2.5 text-[10px] leading-relaxed text-muted">
                        <li>Завершённая задача со стоимостью создаёт счёт и списывает сумму с баланса.</li>
                        <li>Баланс может уходить в минус — работа не останавливается.</li>
                        <li>Пополнение и корректировки делает администратор; операции видны здесь.</li>
                    </ul>
                </section>
            </aside>
        </div>
    </ClientLayout>
</template>
