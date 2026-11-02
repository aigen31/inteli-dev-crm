<script setup>
import { computed } from 'vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';

const props = defineProps({
    size: { type: [Number, String], default: 32 },
});

const px = computed(() => (typeof props.size === 'number' ? props.size : 32));
const inner = computed(() => Math.max(px.value - 5, 16));
</script>

<template>
    <span
        class="relative inline-flex shrink-0 items-center justify-center rounded-full border border-dashed border-primary-300/90 bg-primary-50/70 p-[2px]"
        :style="{ width: `${px}px`, height: `${px}px` }"
        title="Заглушка аватара Кики"
        aria-label="Заглушка аватара Кики"
        role="img"
    >
        <KikiAvatar :size="inner" />
        <span
            class="absolute -bottom-0.5 -right-0.5 flex items-center justify-center rounded-full bg-primary-500 font-bold leading-none text-white ring-2 ring-surface"
            :style="{ width: `${Math.max(13, Math.round(px * 0.4))}px`, height: `${Math.max(13, Math.round(px * 0.4))}px`, fontSize: `${Math.max(8, Math.round(px * 0.22))}px` }"
            aria-hidden="true"
        >?</span>
    </span>
</template>
