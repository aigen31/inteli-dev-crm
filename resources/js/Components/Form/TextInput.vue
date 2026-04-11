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
        <label v-if="label" :for="inputId" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
        </label>
        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :class="[
                'w-full rounded-md border shadow-sm px-3 py-2 text-sm text-gray-700 placeholder-gray-400',
                'focus:ring-1 focus:outline-none',
                error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500',
                'disabled:bg-gray-100 disabled:text-gray-500',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        />
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
