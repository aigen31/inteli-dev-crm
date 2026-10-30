<script setup>
defineProps({
    title: { type: String, default: null },
});
</script>

<template>
    <div class="rounded-[14px] border border-line bg-surface shadow-[0_6px_16px_rgba(0,0,0,0.3)]">
        <div v-if="title || $slots.header" class="border-b border-line px-6 py-4">
            <slot name="header">
                <h3 class="text-base font-semibold text-ink">{{ title }}</h3>
            </slot>
        </div>
        <div class="p-6">
            <slot />
        </div>
        <div v-if="$slots.footer" class="border-t border-line px-6 py-4">
            <slot name="footer" />
        </div>
    </div>
</template>
