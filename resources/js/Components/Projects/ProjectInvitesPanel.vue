<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/UI/Button.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { CheckIcon, ClipboardDocumentIcon, LinkIcon, TrashIcon, UserPlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    project: { type: Object, required: true },
    invites: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
});

const role = ref('member');
const days = ref(7);
const email = ref('');
const generating = ref(false);
const error = ref(null);
const link = ref('');
const copied = ref(false);

const expiryOptions = [
    { value: 1, label: '1 день' },
    { value: 3, label: '3 дня' },
    { value: 7, label: '7 дней' },
    { value: 14, label: '14 дней' },
    { value: 30, label: '30 дней' },
];

const roleOptions = computed(() => props.roles.map((r) => ({ value: r.value, label: r.label })));
const roleHint = computed(() => props.roles.find((r) => r.value === role.value)?.hint || '');
const activeInvites = computed(() => props.invites.filter((invite) => invite.status === 'active'));
const pastInvites = computed(() => props.invites.filter((invite) => invite.status !== 'active'));

const tone = {
    active: 'border-success/30 bg-success/5 text-success',
    used: 'border-line bg-ink/[0.03] text-muted',
    expired: 'border-warning/30 bg-warning/5 text-warning',
};

async function generate() {
    generating.value = true;
    error.value = null;
    link.value = '';
    copied.value = false;

    try {
        const { data } = await window.axios.post(route('admin.projects.invites.store', props.project.id), {
            role: role.value,
            days: days.value,
            email: email.value.trim() || null,
        });
        link.value = data.url;
        email.value = '';
        router.reload({ preserveScroll: true });
    } catch (e) {
        error.value = e.response?.data?.message || 'Не удалось создать ссылку.';
    } finally {
        generating.value = false;
    }
}

async function copy() {
    if (!link.value) return;
    try {
        await navigator.clipboard.writeText(link.value);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 1800);
    } catch {
        const input = document.getElementById('invite-link');
        input?.select();
        document.execCommand('copy');
        copied.value = true;
    }
}

function revoke(invite) {
    if (!window.confirm('Отозвать это приглашение? Ссылка перестанет работать.')) return;

    window.axios.delete(route('admin.projects.invites.destroy', [props.project.id, invite.id])).then(() => {
        router.reload({ preserveScroll: true });
    });
}

function formatDate(value) {
    if (!value) return '';
    return new Intl.DateTimeFormat('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(new Date(value));
}
</script>

<template>
    <section class="surface-card">
        <header class="flex flex-wrap items-center gap-2 border-b border-line/80 px-5 py-3.5">
            <LinkIcon class="h-4 w-4 text-primary-600" />
            <p class="text-[12px] font-bold text-ink">Приглашения по ссылке</p>
            <span class="font-mono text-[9px] text-faint">{{ activeInvites.length }} активных</span>
            <p class="ml-auto text-[10px] text-muted">одноразовая ссылка</p>
        </header>

        <div class="space-y-3 px-5 py-4">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <SelectInput v-model="role" label="Роль" :options="roleOptions" />
                <SelectInput v-model="days" label="Срок" :options="expiryOptions" />
            </div>
            <p class="text-[10px] leading-relaxed text-muted">{{ roleHint }}</p>
            <TextInput v-model="email" label="E-mail (необязательно)" type="email" placeholder="Если ссылка именная" hint="Если указан, зарегистрироваться сможет только этот e-mail." />

            <div class="flex items-center gap-2">
                <Button size="sm" :disabled="generating" @click="generate">
                    <UserPlusIcon class="h-3.5 w-3.5" />
                    {{ generating ? 'Создаём…' : 'Создать ссылку' }}
                </Button>
                <p v-if="error" class="text-[10px] text-danger-ink">{{ error }}</p>
            </div>

            <div v-if="link" class="rounded-[14px] border border-primary-200 bg-primary-soft/40 p-3">
                <p class="text-[10px] font-semibold text-primary-900">Ссылка готова — скопируйте сейчас, повторно она не покажется</p>
                <div class="mt-2 flex items-center gap-2">
                    <input id="invite-link" :value="link" readonly class="h-9 min-w-0 flex-1 rounded-[10px] border border-line bg-white/80 px-2.5 font-mono text-[10px] text-ink" @focus="$event.target.select()" />
                    <Button size="sm" variant="ghost" @click="copy">
                        <CheckIcon v-if="copied" class="h-3.5 w-3.5" />
                        <ClipboardDocumentIcon v-else class="h-3.5 w-3.5" />
                        {{ copied ? 'Скопировано' : 'Копировать' }}
                    </Button>
                </div>
            </div>

            <div v-if="activeInvites.length" class="space-y-2 pt-1">
                <p class="eyebrow">Активные</p>
                <div v-for="invite in activeInvites" :key="invite.id" class="flex flex-wrap items-center gap-2 rounded-xl border border-line/70 px-3 py-2">
                    <span class="rounded-full border px-2 py-0.5 text-[9px] font-semibold" :class="tone[invite.status]">{{ invite.status_label }}</span>
                    <span class="text-[10px] font-semibold text-ink">{{ invite.role_label }}</span>
                    <span v-if="invite.email" class="text-[10px] text-muted">{{ invite.email }}</span>
                    <span class="text-[9px] text-faint">до {{ formatDate(invite.expires_at) }}</span>
                    <button type="button" class="ml-auto rounded-lg p-1.5 text-faint transition hover:bg-danger/10 hover:text-danger-ink" title="Отозвать" @click="revoke(invite)">
                        <TrashIcon class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <div v-if="pastInvites.length" class="space-y-2 pt-1">
                <p class="eyebrow">История</p>
                <div v-for="invite in pastInvites.slice(0, 6)" :key="invite.id" class="flex flex-wrap items-center gap-2 rounded-xl border border-line/60 px-3 py-2 opacity-80">
                    <span class="rounded-full border px-2 py-0.5 text-[9px] font-semibold" :class="tone[invite.status]">{{ invite.status_label }}</span>
                    <span class="text-[10px] font-semibold text-ink">{{ invite.role_label }}</span>
                    <span v-if="invite.accepted_user" class="text-[10px] text-muted">{{ invite.accepted_user.name }}</span>
                    <span v-else-if="invite.email" class="text-[10px] text-muted">{{ invite.email }}</span>
                    <span class="ml-auto text-[9px] text-faint">{{ formatDate(invite.used_at || invite.expires_at) }}</span>
                </div>
            </div>
        </div>
    </section>
</template>
