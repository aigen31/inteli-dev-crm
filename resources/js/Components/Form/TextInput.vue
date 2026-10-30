<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    type: { type: String, default: 'text' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || `input-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="mb-1.5 block text-xs font-medium text-ink-soft">
            {{ label }}
        </label>
        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :class="[
                'w-full rounded-[10px] border bg-surface-2 px-3 py-2.5 text-sm text-ink placeholder-faint shadow-sm',
                'focus:outline-none focus:ring-1',
                error
                    ? 'border-danger focus:border-danger focus:ring-danger/40'
                    : 'border-line focus:border-primary-500 focus:ring-primary-500/40',
                'disabled:bg-white/5 disabled:text-muted',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        />
        <p v-if="error" class="mt-1 text-xs text-danger-ink">{{ error }}</p>
    </div>
</template>
