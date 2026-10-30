<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Pagination from '@/Components/UI/Pagination.vue';
import { formatDate } from '@/Utils/formatters.js';
import { MagnifyingGlassIcon, PlusIcon, UserGroupIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    clients: Object,
    fields: Array,
    filters: Object,
});

const local = reactive({ q: props.filters?.q || '' });

function search() {
    router.get(route('admin.clients.index'), { q: local.q || undefined }, { preserveState: true, replace: true });
}

function initials(name) {
    return (name || '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
}

function rawValue(client, field) {
    const value = (client.custom_field_values || []).find((item) => item.custom_field?.key === field.key);
    const raw = value
        ? {
              text: value.value_text,
              number: value.value_number,
              date: value.value_date,
              boolean: value.value_boolean,
              json: value.value_json,
          }
        : field.default_value !== null && field.default_value !== undefined
          ? { text: field.default_value, number: field.default_value, date: field.default_value, boolean: field.default_value, json: field.default_value }
          : null;

    if (!raw) return null;

    if (field.type === 'select') {
        return (field.options || []).find((option) => option.value === raw.text)?.label ?? raw.text;
    }

    if (field.type === 'checkbox') return raw.boolean ? 'Да' : 'Нет';
    if (field.type === 'date') return raw.date ? formatDate(raw.date) : null;
    if (['number', 'currency'].includes(field.type)) return raw.number;
    if (field.type === 'multiselect') return Array.isArray(raw.json) ? raw.json.join(', ') : raw.json;

    return raw.text;
}

function statusTone(label) {
    if (!label) return 'bg-ink/[0.06] text-muted';
    if (label.includes('Принимаю')) return 'bg-success/10 text-success';
    if (label.includes('Ограниченно')) return 'bg-warning/10 text-warning';
    if (label.includes('Пауза')) return 'bg-ink/[0.06] text-muted';
    return 'bg-danger/10 text-danger-ink';
}
</script>

<template>
    <AdminLayout title="Клиенты">
        <template #actions>
            <Link :href="route('admin.clients.create')">
                <Button>
                    <PlusIcon class="h-4 w-4" />
                    Новый клиент
                </Button>
            </Link>
        </template>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <form class="relative w-full max-w-[360px]" @submit.prevent="search">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
                <input
                    v-model="local.q"
                    type="search"
                    placeholder="Имя, email или телефон…"
                    class="h-10 w-full rounded-full border border-line bg-white/60 pl-10 pr-4 text-[12px] text-ink placeholder-faint focus:border-primary-400 focus:outline-none"
                />
            </form>
            <span class="font-mono text-[10px] uppercase tracking-[0.12em] text-muted">{{ clients.total }} клиентов</span>
        </div>

        <div v-if="!clients.data.length" class="surface-card">
            <EmptyState title="Клиентов пока нет" text="Создайте первый аккаунт — клиент получит доступ к своим задачам, счетам и шаблонам.">
                <Link :href="route('admin.clients.create')"><Button size="sm">Создать клиента</Button></Link>
            </EmptyState>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="client in clients.data"
                :key="client.id"
                :href="route('admin.clients.show', client.id)"
                class="surface-card surface-card-hover group flex flex-col p-5"
            >
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[12px] font-bold text-primary-800">
                        {{ initials(client.name) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[14px] font-bold text-ink group-hover:text-primary-800">{{ client.name }}</p>
                        <p class="mt-0.5 truncate text-[11px] text-muted">{{ client.email }}</p>
                        <p class="mt-0.5 text-[10px] text-faint">{{ client.phone || 'телефон не указан' }}</p>
                    </div>
                    <span :class="['rounded-full px-2 py-0.5 text-[9px] font-semibold', client.is_active ? 'bg-success/10 text-success' : 'bg-ink/[0.06] text-muted']">
                        {{ client.is_active ? 'активен' : 'выкл' }}
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    <template v-for="field in fields" :key="field.id">
                        <span v-if="rawValue(client, field)" :class="['rounded-full px-2.5 py-1 text-[9px] font-medium', field.key === 'intake_status' ? statusTone(rawValue(client, field)) : 'bg-surface-2 text-ink-soft']">
                            {{ field.key === 'intake_status' ? rawValue(client, field) : `${field.label}: ${rawValue(client, field)}` }}
                        </span>
                    </template>
                </div>

                <div class="mt-4 flex items-center gap-4 border-t border-line/70 pt-3 text-[10px] text-muted">
                    <span>{{ client.client_tasks_count ?? 0 }} задач</span>
                    <span>{{ client.invoices_count ?? 0 }} счетов</span>
                    <span class="ml-auto font-mono text-[9px] text-faint">{{ formatDate(client.created_at) }}</span>
                </div>
            </Link>
        </div>

        <Pagination :links="clients" />
    </AdminLayout>
</template>
