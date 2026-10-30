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
    'inline-flex items-center justify-center gap-2 font-medium rounded-[10px] transition-colors duration-150 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/70 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:opacity-50 disabled:cursor-not-allowed';

const variantClasses = computed(
    () =>
        ({
            primary: 'bg-primary-500 hover:bg-primary-400 text-white shadow-[0_0_24px_rgba(139,127,245,0.2)]',
            secondary:
                'bg-white/5 hover:bg-white/10 text-ink border border-line-strong hover:border-primary-500',
            danger: 'bg-danger hover:bg-danger/85 text-white',
            ghost: 'bg-transparent hover:bg-white/5 text-ink-soft hover:text-white',
            link: 'text-primary-300 hover:text-primary-200 underline-offset-2 hover:underline',
        })[props.variant],
);

const sizeClasses = computed(
    () =>
        ({
            sm: 'px-3 py-1.5 text-xs',
            md: 'px-4 py-2 text-sm',
            lg: 'px-6 py-3 text-base',
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
