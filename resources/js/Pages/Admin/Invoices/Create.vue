<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import { formatMoney } from '@/Utils/formatters.js';
import { ArrowLeftIcon, BanknotesIcon, CheckCircleIcon, ClipboardDocumentListIcon, FolderIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    clients: { type: Array, default: () => [] },
    selectedTaskId: { type: String, default: null },
});

const initialTask = props.tasks.find((task) => task.id === props.selectedTaskId);
const selectedClientId = ref(initialTask?.client_id || props.clients[0]?.id || '');
const selectedIds = ref(initialTask ? [initialTask.id] : []);
const amountTouched = ref(false);

const form = useForm({
    task_ids: selectedIds.value,
    amount: '',
    description: '',
    payment_method: 'yookassa',
    is_recurring: false,
    recurring_interval: '',
    consent: false,
});

const intervalOptions = [
    { value: 'monthly', label: 'Ежемесячно' },
    { value: 'quarterly', label: 'Ежеквартально' },
    { value: 'yearly', label: 'Ежегодно' },
];
const paymentOptions = [
    { value: 'yookassa', label: 'ЮКасса — онлайн-оплата' },
    { value: 'transfer', label: 'Прямой перевод — на мои реквизиты' },
];

const clientTasks = computed(() => props.tasks.filter((task) => task.client_id === selectedClientId.value));
const selectedTasks = computed(() => props.tasks.filter((task) => selectedIds.value.includes(task.id)));
const totalBudget = computed(() => selectedTasks.value.reduce((sum, task) => sum + Number(task.budget || 0), 0));
const missingBudget = computed(() => selectedTasks.value.some((task) => task.budget === null || task.budget === ''));
const isTransfer = computed(() => form.payment_method === 'transfer');

watch(selectedClientId, () => {
    selectedIds.value = [];
    form.task_ids = [];
    amountTouched.value = false;
});

watch(selectedTasks, (tasks) => {
    form.task_ids = tasks.map((task) => task.id);
    if (!amountTouched.value) {
        form.amount = tasks.length && !missingBudget.value ? totalBudget.value.toFixed(2) : '';
    }
}, { deep: true });

watch(isTransfer, (transfer) => {
    if (transfer) {
        form.is_recurring = false;
        form.recurring_interval = '';
    }
});

function toggleTask(id) {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((taskId) => taskId !== id)
        : [...selectedIds.value, id];
}

function submit() {
    form.post(route('admin.invoices.store'));
}
</script>

<template>
    <AdminLayout title="Новый счёт">
        <template #actions>
            <Link :href="route('admin.invoices.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Все счета</Button></Link>
        </template>

        <section class="relative mb-5 overflow-hidden rounded-[28px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-5 sm:px-7 sm:py-6">
            <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700"><BanknotesIcon class="h-5 w-5" /></span>
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Сборка счёта</p>
                    <h1 class="mt-1 font-display text-[22px] font-medium tracking-[-0.035em] text-ink">Один счёт — несколько задач</h1>
                    <p class="mt-1 max-w-[620px] text-[11px] leading-relaxed text-muted">Выберите клиента и отметьте задачи. Итог соберётся из их бюджетов; если бюджета нет, его можно указать в задаче позже.</p>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_350px]">
            <div class="space-y-5">
                <section class="surface-card p-5 sm:p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div><p class="eyebrow">01 / Клиент</p><h2 class="mt-1 text-[14px] font-bold text-ink">Чьи задачи включаем?</h2></div>
                        <span class="font-mono text-[9px] text-faint">{{ clients.length }} клиентов</span>
                    </div>
                    <div class="mt-4">
                        <SelectInput v-model="selectedClientId" label="Клиент" :options="clients.map((client) => ({ value: client.id, label: client.name }))" placeholder="Выберите клиента" />
                    </div>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <div class="flex flex-wrap items-end justify-between gap-2">
                        <div><p class="eyebrow">02 / Задачи</p><h2 class="mt-1 text-[14px] font-bold text-ink">Что войдёт в счёт</h2></div>
                        <span class="font-mono text-[9px] text-faint">выбрано {{ selectedIds.length }} из {{ clientTasks.length }}</span>
                    </div>

                    <div v-if="!clientTasks.length" class="mt-4 rounded-2xl border border-dashed border-line px-4 py-6 text-center text-[11px] text-muted">
                        У клиента пока нет задач, доступных для счёта.
                    </div>
                    <div v-else class="mt-4 grid grid-cols-1 gap-2">
                        <label
                            v-for="task in clientTasks"
                            :key="task.id"
                            :class="[
                                'flex cursor-pointer items-start gap-3 rounded-[16px] border px-3.5 py-3 transition',
                                selectedIds.includes(task.id) ? 'border-primary-300 bg-primary-50/40' : 'border-line bg-white/35 hover:border-line-strong',
                            ]"
                        >
                            <input
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded border-line-strong text-primary-600 focus:ring-primary-500/30"
                                :checked="selectedIds.includes(task.id)"
                                @change="toggleTask(task.id)"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="text-[12px] font-semibold text-ink">{{ task.title }}</span>
                                    <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-[8px] uppercase tracking-[0.08em] text-muted">{{ task.status }}</span>
                                </span>
                                <span class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-muted">
                                    <span v-if="task.project" class="inline-flex items-center gap-1"><FolderIcon class="h-3 w-3" />{{ task.project.key }} · {{ task.project.name }}</span>
                                    <span class="inline-flex items-center gap-1"><ClipboardDocumentListIcon class="h-3 w-3" />{{ task.type }}</span>
                                </span>
                            </span>
                            <span :class="['shrink-0 font-mono text-[11px] font-semibold', task.budget ? 'text-ink' : 'text-faint']">
                                {{ task.budget ? formatMoney(task.budget) : 'без бюджета' }}
                            </span>
                        </label>
                    </div>

                    <p v-if="selectedTasks.length && missingBudget" class="mt-3 rounded-[12px] border border-warning/25 bg-warning/[0.07] px-3 py-2 text-[10px] leading-relaxed text-warning">
                        У одной из выбранных задач не заполнен бюджет. Сумму можно указать вручную ниже.
                    </p>
                </section>

                <section class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">03 / Основание</p>
                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-1.5 block text-[11px] font-semibold text-ink-soft">Назначение (необязательно)</label>
                            <TextArea v-model="form.description" :rows="2" placeholder="Оставьте пустым — соберём из названий задач" :error="form.errors.description" />
                        </div>
                        <SelectInput v-model="form.payment_method" label="Способ оплаты" :options="paymentOptions" :error="form.errors.payment_method" placeholder="Выберите способ" />
                        <p class="text-[10px] leading-relaxed text-muted">
                            <template v-if="isTransfer">Клиент увидит реквизиты из <Link :href="route('admin.settings.edit', { tab: 'payments' })" class="text-primary-700 underline">настроек</Link>. Поступление подтвердите вручную.</template>
                            <template v-else>Клиент оплатит картой на стороне ЮКассы, статус обновится автоматически.</template>
                        </p>
                        <template v-if="!isTransfer">
                            <label class="flex items-center gap-2 text-[12px] text-ink-soft">
                                <input v-model="form.is_recurring" type="checkbox" class="h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30" />
                                Рекуррентный платёж
                            </label>
                            <SelectInput v-if="form.is_recurring" v-model="form.recurring_interval" label="Интервал" :options="intervalOptions" :error="form.errors.recurring_interval" />
                        </template>
                        <p v-if="form.errors.is_recurring" class="text-[10px] text-danger-ink">{{ form.errors.is_recurring }}</p>
                    </div>
                </section>
            </div>

            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="surface-card overflow-hidden">
                    <div class="border-b border-line/70 bg-surface-2/50 px-5 py-4">
                        <p class="eyebrow">Итог</p>
                        <p class="mt-1 font-display text-[28px] font-medium tracking-[-0.04em] text-ink">{{ formatMoney(totalBudget) }}</p>
                        <p class="mt-1 text-[10px] text-muted">{{ selectedTasks.length }} задач · сумма бюджетов</p>
                    </div>
                    <div class="p-5">
                        <div v-if="!selectedTasks.length" class="rounded-[12px] border border-dashed border-line px-3 py-4 text-center text-[10px] text-muted">
                            Отметьте задачи слева, чтобы увидеть итог.
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="task in selectedTasks" :key="task.id" class="flex items-start justify-between gap-3">
                                <span class="min-w-0 flex-1 truncate text-[10px] text-ink-soft">{{ task.title }}</span>
                                <span class="shrink-0 font-mono text-[10px] font-semibold text-ink">{{ task.budget ? formatMoney(task.budget) : '—' }}</span>
                            </div>
                        </div>

                        <div v-if="missingBudget" class="mt-4">
                            <label class="mb-1.5 block text-[10px] font-semibold text-ink-soft">Сумма счёта, ₽</label>
                            <input v-model="form.amount" type="number" step="0.01" min="0.01" class="h-10 w-full rounded-[13px] border border-line bg-white/70 px-3.5 text-[13px] text-ink focus:border-primary-400 focus:outline-none" placeholder="Введите сумму" @input="amountTouched = true" />
                            <p v-if="form.errors.amount" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.amount }}</p>
                        </div>
                        <p v-else class="mt-4 text-[10px] leading-relaxed text-muted">Сумма считается автоматически из бюджетов выбранных задач.</p>

                        <div class="mt-4 border-t border-line/70 pt-4">
                            <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="admin-invoice-consent" variant="contract" />
                        </div>
                        <p v-if="form.errors.task_ids" class="mt-2 text-[10px] text-danger-ink">{{ form.errors.task_ids }}</p>
                        <Button class="mt-4 w-full" :disabled="form.processing || !form.consent || !selectedTasks.length" @click="submit">
                            <CheckCircleIcon class="h-4 w-4" />
                            {{ form.processing ? 'Создаю…' : 'Создать счёт' }}
                        </Button>
                        <p class="mt-2 text-center text-[9px] leading-relaxed text-faint">Счёт будет доступен клиенту сразу после создания.</p>
                    </div>
                </section>

                <section class="rounded-[20px] border border-line/70 bg-white/35 p-4">
                    <p class="eyebrow">Как это работает</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Бюджет задаётся в карточке задачи. Один счёт может объединять любое количество задач одного клиента; каждая задача попадёт в счёт отдельной строкой.</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
