<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

const emit = defineEmits(['close']);

const maxWidthClass = computed(
    () =>
        ({
            sm: 'max-w-sm',
            md: 'max-w-md',
            lg: 'max-w-lg',
            xl: 'max-w-xl',
            '2xl': 'max-w-2xl',
        })[props.maxWidth],
);
</script>

<template>
    <Teleport to="body">
        <Transition leave-active-class="duration-200">
            <div v-show="show" class="fixed inset-0 z-40 overflow-y-auto" @click.self="emit('close')">
                <div class="fixed inset-0 bg-[#060512]/70 backdrop-blur-sm transition-opacity" />
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-show="show"
                            :class="[
                                'max-h-[90vh] w-full overflow-y-auto rounded-[20px] border border-line bg-surface shadow-[0_16px_40px_rgba(0,0,0,0.5)]',
                                maxWidthClass,
                            ]"
                            @click.stop
                        >
                            <div v-if="$slots.header" class="border-b border-line px-6 py-4">
                                <slot name="header" />
                            </div>
                            <div class="px-6 py-4">
                                <slot />
                            </div>
                            <div v-if="$slots.footer" class="flex justify-end space-x-3 border-t border-line px-6 py-4">
                                <slot name="footer" />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
