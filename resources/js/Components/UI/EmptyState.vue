<script setup>
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';

defineProps({
    title: { type: String, default: 'Пока пусто' },
    text: { type: String, default: null },
    variant: { type: String, default: 'empty' },
    compact: { type: Boolean, default: false },
    showAvatar: { type: Boolean, default: true },
    image: { type: String, default: null },
});
</script>

<template>
    <div :class="['flex flex-col items-center justify-center text-center', compact ? 'px-4 py-8' : 'px-6 py-14']">
        <img
            v-if="image"
            :src="image"
            :alt="title"
            class="pointer-events-none h-[170px] w-auto max-w-full select-none object-contain"
            loading="lazy"
            draggable="false"
        />
        <KikiAvatar v-else-if="showAvatar" :size="compact ? 92 : 132" :variant="variant" class="kiki-ring" />
        <h3 :class="['font-display font-medium tracking-[-0.03em] text-ink', (showAvatar || image) ? (compact ? 'mt-4 text-[17px]' : 'mt-5 text-[21px]') : (compact ? 'text-[16px]' : 'text-[20px]')]">{{ title }}</h3>
        <p v-if="text" :class="['mx-auto mt-2 max-w-[360px] leading-relaxed text-muted', compact ? 'text-[12px]' : 'text-[13px]']">{{ text }}</p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
