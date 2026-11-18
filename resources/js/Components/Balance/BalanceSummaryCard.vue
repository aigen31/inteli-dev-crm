<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatDateTime, formatMoney } from '@/Utils/formatters.js';
import { ArrowRightIcon, ArrowTrendingDownIcon, ArrowTrendingUpIcon, WalletIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    balance: { type: Number, default: 0 },
    transactions: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});

const isNegative = computed(() => props.balance < 0);

function tone(amount) {
    return amount < 0 ? 'text-danger-ink' : 'text-success';
}

function reasonLabel(transaction) {
    if (transaction.reason === 'task_completed') return 'Списание за задачу';
    if (transaction.reason === 'manual') return transaction.amount > 0 ? 'Пополнение' : 'Корректировка';

    return 'Операция';
}
</script>

<template>
    <section class="relative overflow-hidden rounded-[26px] border border-line bg-gradient-to-br from-primary-50/80 via-surface to-surface p-5 sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-20 h-52 w-52 rounded-full bg-primary-300/25 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-24 left-10 h-44 w-44 rounded-full bg-sage/20 blur-3xl"></div>

        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="inline-flex items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.16em] text-primary-700">
                    <WalletIcon class="h-3.5 w-3.5" />Баланс
                </p>
                <p :class="['mt-2 font-display text-[34px] leading-none tracking-[-0.04em] sm:text-[40px]', isNegative ? 'text-danger-ink' : 'text-ink']">
                    {{ formatMoney(balance) }}
                </p>
                <p class="mt-2 max-w-[360px] text-[11px] leading-relaxed text-muted">
                    {{ isNegative
                        ? 'Баланс отрицательный: стоимость завершённых задач списана вперёд. Пополнить может администратор.'
                        : 'Из баланса списывается стоимость завершённых задач. Пополнить может администратор.' }}
                </p>
            </div>

            <Link
                :href="route('balance.index')"
                class="group inline-flex items-center gap-1.5 rounded-full border border-line bg-surface/80 px-3 py-1.5 text-[10px] font-semibold text-ink-soft transition hover:border-primary-300 hover:text-primary-700"
            >
                Все операции
                <ArrowRightIcon class="h-3 w-3 transition group-hover:translate-x-0.5" />
            </Link>
        </div>

        <ul v-if="transactions.length" class="relative mt-5 divide-y divide-line/60 border-t border-line/60">
            <li v-for="transaction in transactions" :key="transaction.id" class="flex items-center gap-3 py-2.5">
                <span :class="['flex h-8 w-8 shrink-0 items-center justify-center rounded-[10px]', transaction.amount < 0 ? 'bg-danger/10 text-danger-ink' : 'bg-success/10 text-success']">
                    <ArrowTrendingDownIcon v-if="transaction.amount < 0" class="h-4 w-4" />
                    <ArrowTrendingUpIcon v-else class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[11px] font-semibold text-ink">{{ transaction.description || reasonLabel(transaction) }}</span>
                    <span class="block text-[10px] text-muted">{{ reasonLabel(transaction) }} · {{ formatDateTime(transaction.created_at) }}</span>
                </span>
                <span :class="['shrink-0 font-mono text-[11px] font-semibold', tone(transaction.amount)]">
                    {{ transaction.amount > 0 ? '+' : '' }}{{ formatMoney(transaction.amount) }}
                </span>
            </li>
        </ul>

        <p v-else class="relative mt-5 rounded-[16px] border border-dashed border-line px-4 py-5 text-center text-[11px] text-muted">
            Операций пока нет. Они появятся, когда администратор пополнит баланс или завершится задача со стоимостью.
        </p>
    </section>
</template>
