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
    avatar: '/images/kiki/kiki-flight-avatar.png',
    hero: '/images/kiki/kiki-flight-portrait.webp',
    empty: '/images/kiki/kiki-empty-generic.png',
    clients: '/images/kiki/kiki-empty-clients.png',
    projects: '/images/kiki/kiki-template-steps.webp',
    projectTasks: '/images/kiki/kiki-empty-project-tasks.png',
    thinking: '/images/kiki/kiki-thinking.png',
    flight: '/images/kiki/kiki-flight-badge.webp',
    classic: '/images/kiki/kiki-avatar.png',
    peek: '/images/kiki/kiki-forms-avatar.webp',
    face: '/images/kiki/kiki-avatar-face.png?v=3',
};

const src = computed(() => sources[props.variant] ?? sources.avatar);
const fitClass = computed(() => (props.fit === 'contain' || props.variant === 'hero' || ['empty', 'clients', 'projects', 'projectTasks'].includes(props.variant) ? 'object-contain' : 'object-cover'));
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
