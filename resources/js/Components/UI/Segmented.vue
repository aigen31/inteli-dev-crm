<script setup>
defineProps({
    modelValue: { type: String, default: '' },
    options: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="inline-flex flex-wrap items-center gap-1 rounded-full border border-line bg-white/50 p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :class="[
                'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold transition',
                modelValue === option.value ? 'bg-ink text-canvas shadow-sm' : 'text-muted hover:text-ink',
            ]"
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
            <span v-if="option.count != null" :class="['rounded-full px-1.5 py-0.5 font-mono text-[9px]', modelValue === option.value ? 'bg-white/15' : 'bg-ink/[0.06]']">
                {{ option.count }}
            </span>
        </button>
    </div>
</template>
