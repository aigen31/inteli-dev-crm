<script setup>
import { computed } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Button from '@/Components/UI/Button.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { TrashIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    project: { type: Object, required: true },
    available: { type: Array, default: () => [] },
});

const form = useForm({
    user_id: '',
    role: 'member',
});

const roles = [
    { value: 'manager', label: 'Менеджер' },
    { value: 'member', label: 'Участник' },
    { value: 'viewer', label: 'Наблюдатель' },
];

const members = computed(() => props.project.members || []);
const owner = computed(() => props.project.client || null);
const canManage = computed(() => props.project.can?.manage_members === true);

const availableOptions = computed(() =>
    props.available.map((user) => ({ value: user.id, label: user.name + (user.email ? ` · ${user.email}` : '') })),
);

function add() {
    form.post(route('admin.projects.members.store', props.project.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('user_id'),
    });
}

function changeRole(member, role) {
    router.patch(
        route('admin.projects.members.update', [props.project.id, member.id]),
        { role },
        { preserveScroll: true },
    );
}

const roleOrder = ['manager', 'member', 'viewer'];
const roleTone = {
    manager: 'border-primary-300 bg-primary-soft text-primary-800',
    member: 'border-sky/40 bg-sky/10 text-ink-soft',
    viewer: 'border-line bg-white/70 text-muted',
};

function cycleRole(member) {
    const index = roleOrder.indexOf(member.role);
    changeRole(member, roleOrder[(index + 1) % roleOrder.length]);
}

function remove(member) {
    if (!window.confirm(`Отозвать доступ у ${member.user?.name || 'участника'}?`)) return;

    router.delete(route('admin.projects.members.destroy', [props.project.id, member.id]), {
        preserveScroll: true,
    });
}
</script>

<template>
    <section class="surface-card">
        <header class="flex flex-wrap items-center gap-2 border-b border-line/80 px-5 py-3.5">
            <UserPlusIcon class="h-4 w-4 text-primary-600" />
            <p class="text-[12px] font-bold text-ink">Доступ к проекту</p>
            <span class="font-mono text-[9px] text-faint">{{ 1 + members.length }}</span>
            <p class="ml-auto text-[10px] text-muted">клиенты видят только свои задачи</p>
        </header>

        <div class="space-y-2 px-5 py-4">
            <div v-if="owner" class="flex flex-wrap items-center gap-2 rounded-xl border border-primary-200/70 bg-primary-soft/40 px-3 py-2">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-500 text-[10px] font-bold text-white">
                    {{ owner.name.slice(0, 1).toUpperCase() }}
                </span>
                <div class="min-w-0">
                    <p class="truncate text-[11px] font-semibold text-ink">{{ owner.name }}</p>
                    <p class="truncate text-[10px] text-muted">{{ owner.email }}</p>
                </div>
                <span class="ml-auto rounded-full bg-primary-500/10 px-2 py-0.5 text-[9px] font-semibold text-primary-800">Владелец</span>
            </div>

            <div
                v-for="member in members"
                :key="member.id"
                class="flex flex-wrap items-center gap-2 rounded-xl border border-line/70 px-3 py-2"
            >
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-ink/[0.06] text-[10px] font-bold text-ink-soft">
                    {{ (member.user?.name || '?').slice(0, 1).toUpperCase() }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[11px] font-semibold text-ink">{{ member.user?.name }}</p>
                    <p class="truncate text-[10px] text-muted">{{ member.user?.email }}</p>
                </div>
                <button
                    v-if="canManage"
                    type="button"
                    class="rounded-full border px-2.5 py-1 text-[9px] font-semibold transition hover:opacity-80"
                    :class="roleTone[member.role] || roleTone.member"
                    title="Нажмите, чтобы сменить роль"
                    @click="cycleRole(member)"
                >
                    {{ member.role_label }} ↻
                </button>
                <span v-else class="rounded-full bg-ink/[0.06] px-2 py-0.5 text-[9px] text-muted">{{ member.role_label }}</span>
                <button
                    v-if="canManage"
                    type="button"
                    class="rounded-lg p-1.5 text-faint transition hover:bg-danger/10 hover:text-danger-ink"
                    title="Отозвать доступ"
                    @click="remove(member)"
                >
                    <TrashIcon class="h-3.5 w-3.5" />
                </button>
            </div>

            <p v-if="members.length === 0" class="rounded-xl border border-dashed border-line px-3 py-3 text-[10px] leading-relaxed text-muted">
                Кроме владельца доступа ни у кого нет. Добавьте клиента или коллегу — он увидит проект и свои задачи.
            </p>
        </div>

        <div v-if="canManage && availableOptions.length" class="border-t border-line/80 px-5 py-4">
            <p class="eyebrow">Выдать доступ</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-[1fr_130px]">
                <SelectInput v-model="form.user_id" :options="availableOptions" placeholder="Выберите пользователя" />
                <SelectInput v-model="form.role" :options="roles" placeholder="Роль" />
            </div>
            <p v-if="form.errors.user_id" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.user_id }}</p>
            <p v-if="form.errors.role" class="mt-1 text-[10px] text-danger-ink">{{ form.errors.role }}</p>
            <Button class="mt-2" size="sm" :disabled="form.processing || !form.user_id" @click="add">Добавить</Button>
        </div>
    </section>
</template>
