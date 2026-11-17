<script setup>
import { computed, ref } from 'vue';
import ActivityCalendarWidget from '@/Components/Dashboard/ActivityCalendarWidget.vue';

const props = defineProps({
    users: { type: Array, default: () => [] },
    currentUserId: { type: String, default: null },
    compact: { type: Boolean, default: false },
});

const selected = ref(props.currentUserId || props.users[0]?.id || null);

const roleLabel = (role) => (role === 'executor' ? 'исполнитель' : 'админ');

const current = computed(() => props.users.find((user) => user.id === selected.value) || null);
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <label class="flex min-w-0 items-center gap-2">
                <span class="sr-only">Сотрудник</span>
                <select
                    v-model="selected"
                    class="max-w-full rounded-[10px] border border-line bg-surface/80 px-2.5 py-1.5 text-[11px] text-ink transition focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                >
                    <option v-for="user in users" :key="user.id" :value="user.id">
                        {{ user.name }} · {{ roleLabel(user.role) }}
                    </option>
                </select>
            </label>
            <span v-if="current" class="font-mono text-[9px] uppercase tracking-[0.12em] text-faint">
                нагрузка
            </span>
        </div>

        <div class="min-h-0 flex-1">
            <ActivityCalendarWidget :assignee-id="selected" :compact="compact" />
        </div>
    </div>
</template>
