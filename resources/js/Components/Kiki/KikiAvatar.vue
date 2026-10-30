<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'avatar' },
    size: { type: [Number, String], default: 44 },
    alt: { type: String, default: 'Кики — ассистент inteli dev' },
    rounded: { type: String, default: 'full' },
});

const sources = {
    avatar: '/images/kiki/kiki-avatar.webp',
    hero: '/images/kiki/kiki-hero.webp',
    empty: '/images/kiki/kiki-empty.webp',
    thinking: '/images/kiki/kiki-thinking.webp',
};

const src = computed(() => sources[props.variant] ?? sources.avatar);
const style = computed(() => ({
    width: typeof props.size === 'number' ? `${props.size}px` : props.size,
    height: typeof props.size === 'number' ? `${props.size}px` : props.size,
}));
</script>

<template>
    <img
        :src="src"
        :alt="alt"
        :style="style"
        :class="[
            'shrink-0 object-cover',
            rounded === 'full' ? 'rounded-full' : 'rounded-[22px]',
            variant === 'hero' || variant === 'empty' ? 'object-contain' : 'object-cover',
        ]"
        loading="lazy"
        draggable="false"
    />
</template>
