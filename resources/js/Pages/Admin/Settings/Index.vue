<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { formatDateTime } from '@/Utils/formatters.js';
import {
    AdjustmentsHorizontalIcon,
    ArrowDownIcon,
    ArrowUpIcon,
    CheckIcon,
    ClipboardDocumentIcon,
    CommandLineIcon,
    CreditCardIcon,
    PencilSquareIcon,
    PlusIcon,
    SparklesIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    tab: String,
    yookassa: Object,
    transfer: Object,
    customFields: Array,
    customFieldEntities: Array,
    customFieldTypes: Array,
    apiTokens: Array,
    aiActions: Array,
    mcp: Object,
});

const activeTab = ref(props.tab || 'payments');
const tabs = [
    { value: 'payments', label: 'Оплата', icon: CreditCardIcon },
    { value: 'fields', label: 'Поля', icon: AdjustmentsHorizontalIcon },
    { value: 'api', label: 'API и MCP', icon: CommandLineIcon },
    { value: 'ai', label: 'Кики', icon: SparklesIcon },
];

function go(tab) {
    activeTab.value = tab;
    router.get(route('admin.settings.edit'), { tab }, { preserveScroll: true, preserveState: true, replace: true });
}

const transferForm = useForm({
    transfer_recipient: props.transfer?.transfer_recipient || '',
    transfer_inn: props.transfer?.transfer_inn || '',
    transfer_bank: props.transfer?.transfer_bank || '',
    transfer_account: props.transfer?.transfer_account || '',
    transfer_bik: props.transfer?.transfer_bik || '',
    transfer_corr_account: props.transfer?.transfer_corr_account || '',
    transfer_phone: props.transfer?.transfer_phone || '',
    transfer_note: props.transfer?.transfer_note || '',
});

function saveTransfer() {
    transferForm.put(route('admin.settings.update'), { preserveScroll: true });
}

const entityLabels = { client: 'Клиент', task: 'Задача', invoice: 'Счёт' };
const groupedFields = computed(() => {
    const groups = { client: [], task: [], invoice: [] };
    (props.customFields || []).forEach((field) => groups[field.entity_type]?.push(field));
    return groups;
});

const fieldModal = ref(false);
const editingField = ref(null);
const fieldForm = useForm({
    entity_type: 'client',
    key: '',
    label: '',
    type: 'text',
    options: [],
    default_value: '',
    is_required: false,
    is_active: true,
    show_in_list: false,
    client_editable: false,
    help_text: '',
    placeholder: '',
});

function openNewField(entity = 'client') {
    editingField.value = null;
    fieldForm.reset();
    fieldForm.clearErrors();
    fieldForm.entity_type = entity;
    fieldForm.options = [];
    fieldModal.value = true;
}

function openEditField(field) {
    editingField.value = field;
    fieldForm.clearErrors();
    fieldForm.entity_type = field.entity_type;
    fieldForm.key = field.key;
    fieldForm.label = field.label;
    fieldForm.type = field.type;
    fieldForm.options = (field.options || []).map((option) => ({ ...option }));
    fieldForm.default_value = field.default_value ?? '';
    fieldForm.is_required = Boolean(field.is_required);
    fieldForm.is_active = Boolean(field.is_active);
    fieldForm.show_in_list = Boolean(field.show_in_list);
    fieldForm.client_editable = Boolean(field.client_editable);
    fieldForm.help_text = field.help_text || '';
    fieldForm.placeholder = field.placeholder || '';
    fieldModal.value = true;
}

function addOption() {
    fieldForm.options.push({ value: '', label: '' });
}

function removeOption(index) {
    fieldForm.options.splice(index, 1);
}

function saveField() {
    if (editingField.value) {
        fieldForm.put(route('admin.custom-fields.update', editingField.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                fieldModal.value = false;
            },
        });
    } else {
        fieldForm.post(route('admin.custom-fields.store'), {
            preserveScroll: true,
            onSuccess: () => {
                fieldModal.value = false;
            },
        });
    }
}

function deleteField(field) {
    if (!window.confirm(`Удалить поле «${field.label}» вместе со значениями?`)) return;
    router.delete(route('admin.custom-fields.destroy', field.id), { preserveScroll: true });
}

function moveField(entity, index, direction) {
    const items = [...(groupedFields.value[entity] || [])];
    const target = index + direction;
    if (target < 0 || target >= items.length) return;
    const [item] = items.splice(index, 1);
    items.splice(target, 0, item);

    router.post(route('admin.custom-fields.reorder'), { ids: items.map((field) => field.id) }, { preserveScroll: true });
}

const tokenForm = useForm({ name: '', abilities: ['read'], expires_in_days: 30 });
const tokenError = ref(null);
const tokenCopied = ref(false);

function formatArgs(args) {
    try {
        return JSON.stringify(args);
    } catch {
        return '';
    }
}

function createToken() {
    tokenForm.post(route('admin.api-tokens.store'), {
        preserveScroll: true,
        onSuccess: () => tokenForm.reset('name'),
    });
}

function revokeToken(token) {
    if (!window.confirm(`Отозвать токен «${token.name}»?`)) return;
    router.delete(route('admin.api-tokens.destroy', token.id), { preserveScroll: true });
}

async function copy(value) {
    try {
        await navigator.clipboard.writeText(value);
        tokenCopied.value = true;
        window.setTimeout(() => (tokenCopied.value = false), 1600);
    } catch {
        tokenError.value = 'Браузер не разрешил копирование. Выделите токен вручную.';
    }
}


const copiedMcp = ref(false);
async function copyMcp() {
    await copy(`CRM_API_URL=${props.mcp?.api_url || ''}\nCRM_API_TOKEN=<ваш токен>\nnode mcp/src/server.mjs`);
    copiedMcp.value = true;
    window.setTimeout(() => (copiedMcp.value = false), 1600);
}
</script>

<template>
    <AdminLayout title="Настройки">
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[220px_1fr]">
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <nav class="surface-card flex gap-1 overflow-x-auto p-1.5 lg:flex-col">
                    <button
                        v-for="item in tabs"
                        :key="item.value"
                        type="button"
                        :class="[
                            'flex shrink-0 items-center gap-2 rounded-[14px] px-3.5 py-2.5 text-left text-[12px] font-semibold transition',
                            activeTab === item.value ? 'bg-primary-soft text-primary-800' : 'text-muted hover:bg-ink/[0.04] hover:text-ink',
                        ]"
                        @click="go(item.value)"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                    </button>
                </nav>
                <div class="surface-card mt-3 hidden p-4 lg:block">
                    <KikiAvatar :size="42" />
                    <p class="mt-2 text-[11px] font-semibold text-ink">Кики рядом</p>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">Настройте API, и ассистент сможет работать с вашими данными.</p>
                </div>
            </aside>

            <div class="min-w-0 space-y-5">
                <!-- Payments -->
                <template v-if="activeTab === 'payments'">
                    <Card title="Реквизиты для прямого перевода">
                        <p class="mb-5 text-[12px] leading-relaxed text-ink-soft">
                            Клиент увидит эти данные в счетах со способом «Прямой перевод». Оплата приходит напрямую вам, вне кассы.
                        </p>
                        <form class="space-y-4" @submit.prevent="saveTransfer">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <TextInput v-model="transferForm.transfer_recipient" label="Получатель" :error="transferForm.errors.transfer_recipient" placeholder="ФИО или название" />
                                <TextInput v-model="transferForm.transfer_inn" label="ИНН" :error="transferForm.errors.transfer_inn" />
                                <div class="sm:col-span-2">
                                    <TextInput v-model="transferForm.transfer_bank" label="Банк" :error="transferForm.errors.transfer_bank" placeholder="Название банка" />
                                </div>
                                <TextInput v-model="transferForm.transfer_account" label="Счёт получателя" :error="transferForm.errors.transfer_account" />
                                <TextInput v-model="transferForm.transfer_bik" label="БИК" :error="transferForm.errors.transfer_bik" />
                                <TextInput v-model="transferForm.transfer_corr_account" label="Корр. счёт" :error="transferForm.errors.transfer_corr_account" />
                                <TextInput v-model="transferForm.transfer_phone" label="Телефон для СБП" :error="transferForm.errors.transfer_phone" placeholder="+7 …" />
                                <div class="sm:col-span-2">
                                    <TextArea v-model="transferForm.transfer_note" label="Комментарий к переводу" :error="transferForm.errors.transfer_note" :rows="3" placeholder="Например: укажите номер счёта в назначении платежа" />
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <Button type="submit" :disabled="transferForm.processing">Сохранить реквизиты</Button>
                            </div>
                        </form>
                    </Card>

                    <Card title="ЮКасса">
                        <div class="flex flex-wrap items-center gap-4">
                            <span :class="['inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[11px] font-semibold', yookassa?.is_configured ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning']">
                                <span :class="['h-1.5 w-1.5 rounded-full', yookassa?.is_configured ? 'bg-success' : 'bg-warning']"></span>
                                {{ yookassa?.is_configured ? 'Настроена' : 'Требуется настройка' }}
                            </span>
                            <span class="font-mono text-[10px] text-muted">{{ yookassa?.shop_id || 'shop id не задан' }}</span>
                        </div>
                        <p class="mt-3 text-[11px] leading-relaxed text-muted">
                            Ключи задаются переменными <code class="rounded bg-surface-2 px-1 py-0.5 font-mono text-[10px]">YOOKASSA_SHOP_ID</code> и
                            <code class="rounded bg-surface-2 px-1 py-0.5 font-mono text-[10px]">YOOKASSA_SECRET_KEY</code> в окружении.
                        </p>
                    </Card>
                </template>

                <!-- Custom fields -->
                <template v-else-if="activeTab === 'fields'">
                    <div class="surface-card p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="eyebrow">Данные под ваш процесс</p>
                                <h2 class="mt-1 font-display text-[21px] font-medium tracking-[-0.03em] text-ink">Пользовательские поля</h2>
                                <p class="mt-1 max-w-[620px] text-[12px] leading-relaxed text-muted">
                                    Добавляйте поля к клиентам, задачам и счетам. Они доступны в карточке, фильтрах и REST API — например, «Статус приёма заявок».
                                </p>
                            </div>
                            <Button size="sm" @click="openNewField('client')">
                                <PlusIcon class="h-4 w-4" />
                                Новое поле
                            </Button>
                        </div>
                    </div>

                    <div v-for="entity in ['client', 'task', 'invoice']" :key="entity" class="surface-card overflow-hidden">
                        <div class="flex items-center justify-between gap-3 border-b border-line/80 px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <h3 class="text-[13px] font-bold text-ink">{{ entityLabels[entity] }}</h3>
                                <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-[9px] text-muted">{{ groupedFields[entity].length }}</span>
                            </div>
                            <button type="button" class="inline-flex items-center gap-1 text-[10px] font-semibold text-primary-700 hover:underline" @click="openNewField(entity)">
                                <PlusIcon class="h-3 w-3" />
                                добавить
                            </button>
                        </div>

                        <div v-if="!groupedFields[entity].length" class="px-5 py-6 text-[11px] text-muted">Полей пока нет.</div>
                        <div v-else class="divide-y divide-line/60">
                            <div v-for="(field, index) in groupedFields[entity]" :key="field.id" class="flex flex-wrap items-center gap-3 px-5 py-3">
                                <div class="flex flex-col gap-0.5">
                                    <button type="button" class="text-muted hover:text-ink" :disabled="index === 0" @click="moveField(entity, index, -1)"><ArrowUpIcon class="h-3 w-3" /></button>
                                    <button type="button" class="text-muted hover:text-ink" :disabled="index === groupedFields[entity].length - 1" @click="moveField(entity, index, 1)"><ArrowDownIcon class="h-3 w-3" /></button>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-[12px] font-semibold text-ink">{{ field.label }}</p>
                                        <span class="font-mono text-[9px] text-faint">{{ field.key }}</span>
                                        <span class="rounded-full bg-primary-soft px-2 py-0.5 text-[9px] font-medium text-primary-700">{{ field.type_label }}</span>
                                        <span v-if="field.is_required" class="rounded-full bg-warning/10 px-2 py-0.5 text-[9px] font-medium text-warning">обязательное</span>
                                        <span v-if="field.show_in_list" class="rounded-full bg-sky/15 px-2 py-0.5 text-[9px] font-medium text-[#3d718f]">в списке</span>
                                    </div>
                                    <p v-if="field.help_text" class="mt-0.5 text-[10px] text-muted">{{ field.help_text }}</p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="rounded-lg p-1.5 text-muted hover:text-ink" title="Изменить" @click="openEditField(field)"><PencilSquareIcon class="h-4 w-4" /></button>
                                    <button type="button" class="rounded-lg p-1.5 text-muted hover:text-danger" title="Удалить" @click="deleteField(field)"><TrashIcon class="h-4 w-4" /></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- API -->
                <template v-else-if="activeTab === 'api'">
                    <div v-if="$page.props.flash?.api_token" class="rounded-[24px] border border-success/25 bg-success/[0.06] p-5">
                        <p class="text-[11px] font-bold uppercase tracking-[0.08em] text-success">Токен создан — скопируйте сейчас</p>
                        <p class="mt-2 text-[12px] text-ink-soft">{{ $page.props.flash.api_token.name }}</p>
                        <div class="mt-3 flex items-center gap-2">
                            <code class="min-w-0 flex-1 overflow-x-auto rounded-xl bg-white/70 px-3 py-2 font-mono text-[11px] text-ink">{{ $page.props.flash.api_token.plain }}</code>
                            <Button size="sm" variant="secondary" @click="copy($page.props.flash.api_token.plain)">
                                <CheckIcon v-if="tokenCopied" class="h-4 w-4" />
                                <ClipboardDocumentIcon v-else class="h-4 w-4" />
                                {{ tokenCopied ? 'Скопировано' : 'Копировать' }}
                            </Button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                        <Card title="Новый API-токен">
                            <div class="space-y-4">
                                <TextInput v-model="tokenForm.name" label="Название" :error="tokenForm.errors.name" placeholder="Например: Claude Desktop" />
                                <div>
                                    <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">Права</p>
                                    <div class="flex gap-2">
                                        <label v-for="ability in [{ value: 'read', label: 'Чтение' }, { value: 'write', label: 'Запись' }]" :key="ability.value" class="flex cursor-pointer items-center gap-2 rounded-xl border border-line bg-white/60 px-3 py-2 text-[11px]">
                                            <input v-model="tokenForm.abilities" type="checkbox" :value="ability.value" class="h-3.5 w-3.5 rounded border-line-strong text-primary-500" />
                                            {{ ability.label }}
                                        </label>
                                    </div>
                                    <p v-if="tokenForm.errors.abilities" class="mt-1 text-[10px] text-danger-ink">{{ tokenForm.errors.abilities }}</p>
                                </div>
                                <SelectInput v-model="tokenForm.expires_in_days" label="Срок действия" :options="[{ value: 30, label: '30 дней' }, { value: 90, label: '90 дней' }, { value: 365, label: '1 год' }]" placeholder="Без срока" />
                                <Button :disabled="tokenForm.processing" @click="createToken">Создать токен</Button>
                            </div>
                        </Card>

                        <Card title="MCP-сервер">
                            <p class="text-[12px] leading-relaxed text-ink-soft">
                                Локальный MCP-сервер даёт ИИ-клиенту доступ к клиентам, задачам, шаблонам, счетам и полям.
                            </p>
                            <pre class="mt-3 overflow-x-auto rounded-2xl bg-ink p-4 font-mono text-[10px] leading-relaxed text-canvas">CRM_API_URL={{ mcp?.api_url }}
CRM_API_TOKEN=&lt;токен&gt;
node mcp/src/server.mjs</pre>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" @click="copyMcp">
                                    <CheckIcon v-if="copiedMcp" class="h-4 w-4" />
                                    <ClipboardDocumentIcon v-else class="h-4 w-4" />
                                    {{ copiedMcp ? 'Скопировано' : 'Скопировать' }}
                                </Button>
                                <span class="self-center font-mono text-[9px] text-faint">{{ mcp?.docs }}</span>
                            </div>
                        </Card>
                    </div>

                    <Card title="Активные токены" :padded="false">
                        <div v-if="!apiTokens.length" class="px-5 py-8 text-center text-[11px] text-muted">Токенов пока нет.</div>
                        <div v-else class="divide-y divide-line/60">
                            <div v-for="token in apiTokens" :key="token.id" class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-[12px] font-semibold text-ink">{{ token.name }}</p>
                                    <p class="mt-0.5 font-mono text-[9px] text-faint">{{ token.prefix }}… · {{ token.abilities.join(', ') }} · создан {{ formatDateTime(token.created_at) }}</p>
                                </div>
                                <span class="text-[9px] text-muted">{{ token.last_used_at ? `исп. ${formatDateTime(token.last_used_at)}` : 'не использовался' }}</span>
                                <button type="button" class="rounded-lg p-1.5 text-muted hover:text-danger" title="Отозвать" @click="revokeToken(token)"><TrashIcon class="h-4 w-4" /></button>
                            </div>
                        </div>
                    </Card>
                </template>

                <!-- AI -->
                <template v-else-if="activeTab === 'ai'">
                    <div class="surface-card flex flex-wrap items-center gap-4 p-5">
                        <KikiAvatar :size="56" class="kiki-ring" />
                        <div class="min-w-0 flex-1">
                            <p class="eyebrow">Ассистент</p>
                            <h2 class="mt-1 text-[15px] font-bold text-ink">Кики</h2>
                            <p class="mt-1 text-[11px] text-muted">Модель, ключи и инструменты настраиваются в сервисе ядра. Здесь только журнал действий Кики.</p>
                        </div>
                    </div>

                    <Card title="Последние действия" :padded="false">
                        <div v-if="!aiActions.length" class="px-5 py-8 text-center text-[11px] text-muted">Кики ещё ничего не меняла.</div>
                        <div v-else class="divide-y divide-line/60">
                            <div v-for="action in aiActions" :key="action.id" class="flex items-center gap-3 px-5 py-3">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-400"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-mono text-[10px] text-ink">{{ action.tool_name }}</p>
                                    <p class="mt-0.5 truncate text-[10px] text-muted">{{ formatArgs(action.arguments) }}</p>
                                </div>
                                <span class="text-[9px] text-muted">{{ action.status_label }}</span>
                                <span class="font-mono text-[9px] text-faint">{{ formatDateTime(action.created_at) }}</span>
                            </div>
                        </div>
                    </Card>
                </template>
            </div>
        </div>

        <!-- Field modal -->
        <div v-if="fieldModal" class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center">
            <div class="absolute inset-0 bg-ink/25 backdrop-blur-[2px]" @click="fieldModal = false"></div>
            <div class="relative max-h-[92vh] w-full max-w-[560px] overflow-y-auto rounded-[26px] border border-line bg-surface p-5 shadow-2xl sm:p-6">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="eyebrow">{{ editingField ? 'Изменить поле' : 'Новое поле' }}</p>
                        <h3 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em] text-ink">{{ fieldForm.label || 'Пользовательское поле' }}</h3>
                    </div>
                    <button type="button" class="rounded-xl p-1.5 text-muted hover:bg-ink/[0.05]" @click="fieldModal = false"><XMarkIcon class="h-5 w-5" /></button>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <SelectInput v-model="fieldForm.entity_type" label="Где показывать" :options="customFieldEntities" placeholder="Выберите" />
                    <SelectInput v-model="fieldForm.type" label="Тип поля" :options="customFieldTypes" placeholder="Выберите" />
                    <TextInput v-model="fieldForm.label" label="Название" :error="fieldForm.errors.label" placeholder="Статус приёма заявок" />
                    <TextInput v-model="fieldForm.key" label="Ключ в API" :error="fieldForm.errors.key" placeholder="intake_status" hint="Латиница, цифры и _; можно оставить пустым." />
                    <div class="sm:col-span-2">
                        <TextInput v-model="fieldForm.help_text" label="Подсказка" :error="fieldForm.errors.help_text" placeholder="Короткое пояснение для клиента" />
                    </div>
                    <div class="sm:col-span-2">
                        <TextInput v-model="fieldForm.placeholder" label="Placeholder" :error="fieldForm.errors.placeholder" />
                    </div>
                </div>

                <div v-if="['select', 'multiselect'].includes(fieldForm.type)" class="mt-4 rounded-2xl border border-line bg-surface-2/40 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold text-ink-soft">Варианты</p>
                        <Button size="sm" variant="secondary" @click="addOption"><PlusIcon class="h-3 w-3" />Добавить</Button>
                    </div>
                    <div class="mt-2 space-y-2">
                        <div v-for="(option, index) in fieldForm.options" :key="index" class="flex gap-2">
                            <input v-model="option.value" placeholder="value" class="h-9 w-1/3 rounded-[10px] border border-line bg-white/70 px-2.5 font-mono text-[10px]" />
                            <input v-model="option.label" placeholder="Подпись" class="h-9 flex-1 rounded-[10px] border border-line bg-white/70 px-2.5 text-[11px]" />
                            <button type="button" class="text-muted hover:text-danger" @click="removeOption(index)"><TrashIcon class="h-4 w-4" /></button>
                        </div>
                    </div>
                    <p v-if="fieldForm.errors.options" class="mt-1 text-[10px] text-danger-ink">{{ fieldForm.errors.options }}</p>
                </div>

                <div class="mt-4 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-[11px] text-ink-soft"><input v-model="fieldForm.is_required" type="checkbox" class="h-3.5 w-3.5 rounded border-line-strong text-primary-500" />Обязательное</label>
                    <label class="flex items-center gap-2 text-[11px] text-ink-soft"><input v-model="fieldForm.is_active" type="checkbox" class="h-3.5 w-3.5 rounded border-line-strong text-primary-500" />Активное</label>
                    <label class="flex items-center gap-2 text-[11px] text-ink-soft"><input v-model="fieldForm.show_in_list" type="checkbox" class="h-3.5 w-3.5 rounded border-line-strong text-primary-500" />Показывать в списке</label>
                    <label class="flex items-center gap-2 text-[11px] text-ink-soft"><input v-model="fieldForm.client_editable" type="checkbox" class="h-3.5 w-3.5 rounded border-line-strong text-primary-500" />Клиент может менять</label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <Button variant="ghost" size="sm" @click="fieldModal = false">Отмена</Button>
                    <Button size="sm" :disabled="fieldForm.processing" @click="saveField">Сохранить</Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
