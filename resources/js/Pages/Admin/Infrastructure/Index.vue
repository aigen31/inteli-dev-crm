<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import Modal from '@/Components/UI/Modal.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { formatDateTime, formatMoney } from '@/Utils/formatters.js';
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
    CircleStackIcon,
    CloudArrowUpIcon,
    ExclamationTriangleIcon,
    PlusIcon,
    ServerStackIcon,
    ShieldCheckIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    services: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    clients: { type: Array, default: () => [] },
    pricing: { type: Object, default: () => ({ clusters: {}, sizes: [] }) },
});

const tab = ref('all');
const createOpen = ref(false);
const expanded = ref(null);

const form = useForm({
    type: 'backup',
    client_id: '',
    name: '',
    status: 'draft',
    started_at: '',
    notes: '',
    db_type: 'mysql',
    db_name: '',
    ftp_host: '152.228.118.128',
    ftp_port: 2121,
    ftp_username: '',
    ftp_password: '',
    remote_path: '/',
    frequency_per_week: 3,
    retention: 3,
    quota_gb: 3,
    tier: 'warm',
    size: 'S',
    region: 'ru-central',
    rto_minutes: 60,
    rpo_minutes: 15,
});

const clientOptions = computed(() => props.clients.map((client) => ({ value: client.id, label: `${client.name} · ${client.email}` })));
const isBackup = computed(() => form.type === 'backup');
const tierOptions = computed(() => Object.entries(props.pricing.clusters || {}).map(([value, meta]) => ({ value, label: meta.label })));
const sizeOptions = computed(() => (props.pricing.sizes || ['S', 'M', 'L']).map((value) => ({ value, label: value })));

const previewPrice = computed(() => {
    if (isBackup.value) {
        const storage = 390 + 60 * Number(form.quota_gb || 0);
        const base = Math.max(590, storage);
        return base + 150 * (Number(form.frequency_per_week || 1) - 1);
    }

    return Number(props.pricing.clusters?.[form.tier]?.prices?.[form.size] || 0);
});

const previewSetup = computed(() => {
    if (isBackup.value) return 1500;
    return Number(props.pricing.clusters?.[form.tier]?.setup?.[form.size] || 0);
});

const filtered = computed(() => {
    if (tab.value === 'all') return props.services;
    return props.services.filter((service) => service.type === tab.value);
});

const tabs = [
    { value: 'all', label: 'Все услуги' },
    { value: 'backup', label: 'Резервные копии' },
    { value: 'cluster', label: 'DR-кластеры' },
];

function submit() {
    form.post(route('admin.infrastructure.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
        },
    });
}

function toggleStatus(service) {
    const next = service.status === 'active' ? 'suspended' : 'active';
    router.patch(route('admin.infrastructure.status', service.id), { status: next }, { preserveScroll: true });
}

function createInvoice(service) {
    router.post(route('admin.infrastructure.invoice', service.id), {}, { preserveScroll: true });
}

function sync() {
    router.post(route('admin.infrastructure.sync'), {}, { preserveScroll: true });
}

function statusTone(status) {
    if (status === 'active') return 'border-success/30 bg-success/10 text-success';
    if (status === 'suspended') return 'border-danger/25 bg-danger/10 text-danger-ink';

    return 'border-line bg-surface-2/60 text-muted';
}

function backupTone(target) {
    if (! target?.last_backup_at) return 'text-muted';
    return target.last_status === 'verified' ? 'text-success' : 'text-warning';
}
</script>

<template>
    <AdminLayout title="Инфраструктура" eyebrow="Сервисы">
        <template #actions>
            <Button variant="secondary" size="sm" :disabled="form.processing" @click="sync">
                <ArrowPathIcon class="h-4 w-4" />Проверить копии
            </Button>
            <Button size="sm" @click="createOpen = true">
                <PlusIcon class="h-4 w-4" />Добавить услугу
            </Button>
        </template>

        <section class="relative mb-5 overflow-hidden rounded-[26px] border border-line bg-gradient-to-br from-primary-50/80 via-surface to-surface p-5 sm:p-6">
            <div class="pointer-events-none absolute -right-16 -top-20 h-52 w-52 rounded-full bg-primary-300/25 blur-3xl"></div>
            <div class="relative flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <p class="inline-flex items-center gap-1.5 font-mono text-[9px] uppercase tracking-[0.16em] text-primary-700">
                        <ServerStackIcon class="h-3.5 w-3.5" />Резервный контур
                    </p>
                    <h2 class="mt-2 font-display text-[24px] leading-tight tracking-[-0.03em] text-ink sm:text-[30px]">
                        Бэкапы и DR-кластеры клиентов
                    </h2>
                    <p class="mt-2 max-w-[640px] text-[11px] leading-relaxed text-muted">
                        aaPanel присылает дампы по FTP/SFTP, мы проверяем целостность, храним три последние копии и выставляем счёт за услугу.
                    </p>
                </div>
                <div class="rounded-[16px] border border-line bg-surface/80 px-4 py-3">
                    <p class="font-mono text-[8px] uppercase tracking-[0.14em] text-muted">MRR</p>
                    <p class="mt-1 font-display text-[22px] text-ink">{{ formatMoney(stats.mrr || 0) }}</p>
                </div>
            </div>
        </section>

        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="surface-card p-4">
                <p class="flex items-center gap-1.5 text-[10px] font-semibold text-muted"><ShieldCheckIcon class="h-3.5 w-3.5 text-success" />Активные услуги</p>
                <p class="mt-1.5 font-display text-[22px] text-ink">{{ stats.active || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="flex items-center gap-1.5 text-[10px] font-semibold text-muted"><CloudArrowUpIcon class="h-3.5 w-3.5 text-primary-600" />Копий за неделю</p>
                <p class="mt-1.5 font-display text-[22px] text-ink">{{ stats.backups_week || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="flex items-center gap-1.5 text-[10px] font-semibold text-muted"><ExclamationTriangleIcon class="h-3.5 w-3.5 text-warning" />Без копий 48 ч</p>
                <p class="mt-1.5 font-display text-[22px] text-ink">{{ stats.failed || 0 }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="flex items-center gap-1.5 text-[10px] font-semibold text-muted"><ArrowTrendingUpIcon class="h-3.5 w-3.5 text-primary-600" />DR-кластеры</p>
                <p class="mt-1.5 font-display text-[22px] text-ink">{{ stats.clusters || 0 }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <button
                v-for="item in tabs"
                :key="item.value"
                type="button"
                :class="[
                    'rounded-full border px-3 py-1.5 text-[10px] font-semibold transition',
                    tab === item.value ? 'border-primary-500 bg-primary-600 text-white' : 'border-line bg-surface-2/50 text-muted hover:text-ink',
                ]"
                @click="tab = item.value"
            >{{ item.label }}</button>
            <span class="ml-auto font-mono text-[9px] uppercase tracking-[0.12em] text-muted">{{ filtered.length }} услуг</span>
        </div>

        <div class="space-y-3">
            <article v-for="service in filtered" :key="service.id" class="surface-card overflow-hidden">
                <div class="flex flex-wrap items-start gap-4 p-4 sm:p-5">
                    <span :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-[13px]', service.type === 'backup' ? 'bg-primary-soft text-primary-700' : 'bg-sage/15 text-sage']">
                        <CircleStackIcon v-if="service.type === 'backup'" class="h-5 w-5" />
                        <ServerStackIcon v-else class="h-5 w-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-[13px] font-bold text-ink">{{ service.name }}</h3>
                            <span :class="['rounded-full border px-2 py-0.5 text-[9px] font-semibold', statusTone(service.status)]">{{ service.status_label }}</span>
                            <span class="rounded-full border border-line bg-surface-2/50 px-2 py-0.5 text-[9px] text-muted">{{ service.type_label }}</span>
                        </div>
                        <p class="mt-1 text-[10px] text-muted">{{ service.client?.name }} · {{ service.client?.email }}</p>

                        <div v-if="service.target" class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-muted">
                            <span>{{ service.target.db_type }}<template v-if="service.target.db_name"> · {{ service.target.db_name }}</template></span>
                            <span>{{ service.target.frequency_per_week }}×/нед · храним {{ service.target.retention }}</span>
                            <span>{{ service.target.quota_gb }} ГБ</span>
                            <span :class="backupTone(service.target)">
                                {{ service.target.last_backup_at ? 'копия ' + formatDateTime(service.target.last_backup_at) : 'копий ещё нет' }}
                            </span>
                        </div>

                        <div v-if="service.cluster" class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[10px] text-muted">
                            <span>{{ service.cluster.tier }} · {{ service.cluster.size }}</span>
                            <span>RTO {{ service.cluster.rto_minutes }} мин · RPO {{ service.cluster.rpo_minutes }} мин</span>
                            <span>{{ service.cluster.region }}</span>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="font-display text-[18px] text-ink">{{ formatMoney(service.price_monthly) }}<span class="text-[10px] text-muted">/мес</span></p>
                        <p class="text-[9px] text-faint">настройка {{ formatMoney(service.setup_fee) }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 border-t border-line/70 bg-surface-2/30 px-4 py-2.5 sm:px-5">
                    <Button variant="secondary" size="sm" @click="toggleStatus(service)">
                        {{ service.status === 'active' ? 'Приостановить' : 'Активировать' }}
                    </Button>
                    <Button variant="ghost" size="sm" :disabled="form.processing" @click="createInvoice(service)">Выставить счёт</Button>
                    <button
                        v-if="service.target"
                        type="button"
                        class="ml-auto text-[10px] font-semibold text-muted hover:text-ink"
                        @click="expanded = expanded === service.id ? null : service.id"
                    >{{ expanded === service.id ? 'Скрыть копии' : 'Копии' }}</button>
                </div>

                <div v-if="expanded === service.id && service.target" class="border-t border-line/70 px-4 py-3 sm:px-5">
                    <div v-if="service.target.runs.length" class="space-y-1.5">
                        <div v-for="run in service.target.runs" :key="run.filename" class="flex flex-wrap items-center justify-between gap-2 text-[10px]">
                            <span class="font-mono text-ink-soft">{{ run.filename }}</span>
                            <span class="text-muted">{{ (run.size_bytes / 1048576).toFixed(1) }} МБ · {{ run.status }} · {{ formatDateTime(run.uploaded_at) }}</span>
                        </div>
                    </div>
                    <p v-else class="text-[10px] text-muted">Агент ещё не присылал манифест по этой цели.</p>
                </div>
            </article>

            <div v-if="!filtered.length" class="surface-card p-10 text-center">
                <ServerStackIcon class="mx-auto h-8 w-8 text-faint" />
                <p class="mt-3 text-[13px] font-bold text-ink">Пока нет услуг</p>
                <p class="mt-1 text-[11px] text-muted">Добавьте резервное копирование или DR-кластер — цена посчитается по тарифу.</p>
                <Button class="mt-4" size="sm" @click="createOpen = true"><PlusIcon class="h-4 w-4" />Добавить услугу</Button>
            </div>
        </div>

        <Modal :show="createOpen" max-width="2xl" @close="createOpen = false">
            <div class="p-5 sm:p-6">
                <p class="eyebrow">Новая услуга</p>
                <h2 class="mt-1 font-display text-[22px] text-ink">Подключить клиента</h2>

                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="button" :class="['flex-1 rounded-[12px] border px-3 py-2 text-[11px] font-semibold', isBackup ? 'border-primary-400 bg-primary-soft/60 text-primary-800' : 'border-line text-muted']" @click="form.type = 'backup'">Резервное копирование</button>
                        <button type="button" :class="['flex-1 rounded-[12px] border px-3 py-2 text-[11px] font-semibold', !isBackup ? 'border-primary-400 bg-primary-soft/60 text-primary-800' : 'border-line text-muted']" @click="form.type = 'cluster'">DR-кластер</button>
                    </div>

                    <SelectInput v-model="form.client_id" label="Клиент" :options="clientOptions" placeholder="Выберите клиента" :error="form.errors.client_id" />
                    <TextInput v-model="form.name" label="Название услуги" placeholder="Бэкап БД интернет-магазина" :error="form.errors.name" />
                    <SelectInput v-model="form.status" label="Статус" :options="[{ value: 'draft', label: 'Черновик' }, { value: 'active', label: 'Активна' }]" :error="form.errors.status" />
                    <TextInput v-model="form.started_at" type="date" label="Дата старта" :error="form.errors.started_at" />

                    <template v-if="isBackup">
                        <SelectInput v-model="form.db_type" label="Тип БД" :options="[{ value: 'mysql', label: 'MySQL' }, { value: 'mariadb', label: 'MariaDB' }, { value: 'postgresql', label: 'PostgreSQL' }, { value: 'sqlite', label: 'SQLite' }]" :error="form.errors.db_type" />
                        <TextInput v-model="form.db_name" label="Имя БД" placeholder="shop_prod" :error="form.errors.db_name" />
                        <TextInput v-model="form.ftp_host" label="FTP-хост" :error="form.errors.ftp_host" />
                        <TextInput v-model.number="form.ftp_port" type="number" label="FTP-порт" :error="form.errors.ftp_port" />
                        <TextInput v-model="form.ftp_username" label="FTP-логин" :error="form.errors.ftp_username" />
                        <TextInput v-model="form.ftp_password" type="password" label="FTP-пароль" :error="form.errors.ftp_password" />
                        <SelectInput v-model.number="form.frequency_per_week" label="Частота" :options="[1,2,3,4,5,6,7].map((n) => ({ value: n, label: `${n}× в неделю` }))" :error="form.errors.frequency_per_week" />
                        <TextInput v-model.number="form.quota_gb" type="number" step="0.1" label="Объём трёх копий, ГБ" :error="form.errors.quota_gb" />
                    </template>

                    <template v-else>
                        <SelectInput v-model="form.tier" label="Уровень" :options="tierOptions" :error="form.errors.tier" />
                        <SelectInput v-model="form.size" label="Размер" :options="sizeOptions" :error="form.errors.size" />
                        <TextInput v-model="form.region" label="Регион" :error="form.errors.region" />
                        <div class="grid grid-cols-2 gap-3">
                            <TextInput v-model.number="form.rto_minutes" type="number" label="RTO, мин" :error="form.errors.rto_minutes" />
                            <TextInput v-model.number="form.rpo_minutes" type="number" label="RPO, мин" :error="form.errors.rpo_minutes" />
                        </div>
                    </template>

                    <div class="sm:col-span-2 rounded-[14px] border border-line bg-surface-2/50 px-4 py-3">
                        <p class="text-[10px] uppercase tracking-[0.1em] text-muted">Предварительная цена</p>
                        <p class="mt-1 font-display text-[22px] text-ink">{{ formatMoney(previewPrice) }}<span class="text-[10px] text-muted">/мес</span></p>
                        <p class="mt-1 text-[10px] text-muted">Настройка: {{ formatMoney(previewSetup) }} · списывается отдельным счётом при подключении.</p>
                    </div>
                </div>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <Button variant="ghost" size="sm" @click="createOpen = false">Отмена</Button>
                    <Button size="sm" :disabled="form.processing" @click="submit">Создать услугу</Button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
