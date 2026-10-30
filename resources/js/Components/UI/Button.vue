<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    href: { type: String, default: null },
});

const baseClasses =
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-[14px] font-semibold transition-all duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:cursor-not-allowed disabled:opacity-50';

const variantClasses = computed(
    () =>
        ({
            primary: 'bg-primary-500 text-white shadow-[0_10px_24px_rgba(109,90,230,0.22)] hover:-translate-y-px hover:bg-primary-600',
            secondary: 'border border-line-strong bg-white/60 text-ink hover:border-ink/30 hover:bg-white',
            danger: 'bg-danger text-white hover:bg-danger/90',
            ghost: 'text-ink-soft hover:bg-ink/[0.05] hover:text-ink',
            link: 'text-primary-700 underline-offset-4 hover:underline',
            dark: 'bg-ink text-canvas hover:bg-primary-800',
        })[props.variant],
);

const sizeClasses = computed(
    () =>
        ({
            sm: 'h-8 px-3 text-[11px]',
            md: 'h-10 px-4 text-[12px]',
            lg: 'h-12 px-5 text-[13px]',
        })[props.size],
);
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :type="href ? undefined : type"
        :href="href"
        :disabled="disabled"
        :class="[baseClasses, variantClasses, sizeClasses]"
    >
        <slot />
    </component>
</template>
