<script setup>
defineProps({
    title: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div v-if="title || $slots.header" class="flex items-center justify-between gap-4 border-b border-line/80 px-5 py-4 sm:px-6">
            <slot name="header">
                <h3 class="text-[14px] font-bold tracking-[-0.01em] text-ink">{{ title }}</h3>
            </slot>
        </div>
        <div :class="padded ? 'p-5 sm:p-6' : ''">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-line/80 px-5 py-4 sm:px-6">
            <slot name="footer" />
        </div>
    </section>
</template>
