<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    icon: { type: [Object, Function], default: null },
    tone: { type: String, default: 'violet' },
    mono: { type: Boolean, default: false },
});

const tones = {
    violet: 'bg-primary-soft text-primary-600',
    peach: 'bg-accent/12 text-[#b56a46]',
    sage: 'bg-sage/15 text-[#4f7b5c]',
    sky: 'bg-sky/15 text-[#3d718f]',
    ink: 'bg-ink/[0.06] text-ink-soft',
};

const toneClass = computed(() => tones[props.tone] ?? tones.violet);
</script>

<template>
    <div class="surface-card p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="eyebrow">{{ label }}</p>
                <p :class="['mt-2.5 font-display text-[25px] font-medium leading-none tracking-[-0.04em] text-ink', mono ? 'font-mono text-[21px]' : '']">
                    {{ value }}
                </p>
                <p v-if="hint" class="mt-2 text-[10px] leading-relaxed text-muted">{{ hint }}</p>
            </div>
            <span v-if="icon" :class="['flex h-9 w-9 shrink-0 items-center justify-center rounded-[12px]', toneClass]">
                <component :is="icon" class="h-4 w-4" />
            </span>
        </div>
    </div>
</template>
