<script setup>
import { computed } from 'vue';

const props = defineProps({
    fields: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
});

const visible = computed(() => props.fields.filter((field) => field.is_active !== false));
</script>

<template>
    <div v-if="visible.length" :class="['grid grid-cols-1 gap-x-6 gap-y-4', compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2']">
        <div v-for="field in visible" :key="field.key" class="min-w-0">
            <p class="text-[10px] font-medium uppercase tracking-[0.08em] text-muted">{{ field.label }}</p>
            <div class="mt-1 text-[13px] text-ink-soft">
                <template v-if="Array.isArray(field.display)">
                    <span v-if="field.display.length" class="flex flex-wrap gap-1">
                        <span v-for="item in field.display" :key="item" class="rounded-full bg-primary-soft px-2 py-0.5 text-[11px] text-primary-800">{{ item }}</span>
                    </span>
                    <span v-else class="text-faint">—</span>
                </template>
                <template v-else-if="field.type === 'checkbox'">
                    <span :class="field.value ? 'text-success' : 'text-muted'">{{ field.value ? 'Да' : 'Нет' }}</span>
                </template>
                <template v-else>
                    <span :class="field.display ? '' : 'text-faint'">{{ field.display || '—' }}</span>
                </template>
            </div>
        </div>
    </div>
</template>
