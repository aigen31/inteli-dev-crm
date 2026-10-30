<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    type: { type: String, default: 'text' },
    id: { type: String, default: null },
    hint: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const inputId = computed(() => props.id || `input-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-[11px] font-semibold text-ink-soft">
            {{ label }}
        </label>
        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :class="[
                'h-10 w-full rounded-[13px] border bg-white/70 px-3.5 text-[13px] text-ink placeholder-faint transition',
                'focus:outline-none focus:ring-2',
                error
                    ? 'border-danger focus:border-danger focus:ring-danger/15'
                    : 'border-line focus:border-primary-400 focus:ring-primary-500/10',
                'disabled:bg-surface-2 disabled:text-muted',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        />
        <p v-if="hint && !error" class="mt-1 text-[10px] text-muted">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-[10px] text-danger-ink">{{ error }}</p>
    </div>
</template>
