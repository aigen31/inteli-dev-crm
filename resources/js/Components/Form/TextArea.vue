<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    rows: { type: Number, default: 4 },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const inputId = computed(() => props.id || `textarea-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-xs font-medium text-ink-soft">
            {{ label }}
        </label>
        <textarea
            :id="inputId"
            :rows="rows"
            :value="modelValue"
            :class="[
                'min-h-[100px] w-full resize-y rounded-[10px] border bg-surface-2 px-3 py-2.5 text-sm text-ink placeholder-faint shadow-sm',
                'focus:outline-none focus:ring-1',
                error
                    ? 'border-danger focus:border-danger focus:ring-danger/40'
                    : 'border-line focus:border-primary-500 focus:ring-primary-500/40',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        />
        <p v-if="error" class="mt-1 text-xs text-danger-ink">{{ error }}</p>
    </div>
</template>
