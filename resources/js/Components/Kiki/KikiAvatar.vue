<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'avatar' },
    size: { type: [Number, String], default: 44 },
    alt: { type: String, default: 'Кики — ассистент inteli dev' },
    rounded: { type: String, default: 'full' },
    fit: { type: String, default: 'cover' },
});

const sources = {
    avatar: '/images/kiki/kiki-flight-avatar.webp',
    hero: '/images/kiki/kiki-flight-portrait.webp',
    empty: '/images/kiki/kiki-seated.webp',
    thinking: '/images/kiki/kiki-thinking.webp',
    flight: '/images/kiki/kiki-flight-badge.webp',
    seated: '/images/kiki/kiki-seated.webp',
    classic: '/images/kiki/kiki-avatar.webp',
    peek: '/images/kiki/kiki-forms-avatar.webp',
    face: '/images/kiki/kiki-avatar-face.png?v=3',
};

const src = computed(() => sources[props.variant] ?? sources.avatar);
const fitClass = computed(() => (props.fit === 'contain' || props.variant === 'hero' || props.variant === 'empty' ? 'object-contain' : 'object-cover'));
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
            'block shrink-0',
            fitClass,
            rounded === 'full' ? 'rounded-full' : 'rounded-[22px]',
            variant === 'face' ? 'p-[4px]' : '',
        ]"
        loading="lazy"
        draggable="false"
    />
</template>
