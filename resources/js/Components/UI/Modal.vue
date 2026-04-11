<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

const emit = defineEmits(['close']);

const maxWidthClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};
</script>

<template>
    <Teleport to="body">
        <Transition leave-active-class="duration-200">
            <div v-show="show" class="fixed inset-0 z-40 overflow-y-auto" @click.self="emit('close')">
                <div class="fixed inset-0 bg-gray-900/50 transition-opacity" />
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
                            :class="['bg-white rounded-xl shadow-xl w-full max-h-[90vh] overflow-y-auto', maxWidthClass[maxWidth]]"
                            @click.stop
                        >
                            <div v-if="$slots.header" class="px-6 py-4 border-b border-gray-200">
                                <slot name="header" />
                            </div>
                            <div class="px-6 py-4">
                                <slot />
                            </div>
                            <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                                <slot name="footer" />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
