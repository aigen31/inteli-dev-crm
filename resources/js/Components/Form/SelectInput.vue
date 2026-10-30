<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Выберите…' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const inputId = computed(() => props.id || `select-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-[11px] font-semibold text-ink-soft">
            {{ label }}
        </label>
        <select
            :id="inputId"
            :value="modelValue"
            :class="[
                'h-10 w-full rounded-[13px] border bg-white/70 px-3.5 text-[13px] text-ink transition',
                'focus:outline-none focus:ring-2',
                error
                    ? 'border-danger focus:border-danger focus:ring-danger/15'
                    : 'border-line focus:border-primary-400 focus:ring-primary-500/10',
            ]"
            @change="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        >
            <option value="">{{ placeholder }}</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="mt-1 text-[10px] text-danger-ink">{{ error }}</p>
    </div>
</template>
