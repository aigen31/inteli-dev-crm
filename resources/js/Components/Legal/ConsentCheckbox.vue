<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    error: { type: String, default: null },
    id: { type: String, default: 'consent' },
    variant: { type: String, default: 'subject' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id);
</script>

<template>
    <div>
        <div class="flex items-start gap-2.5 rounded-[14px] border border-line bg-white/50 px-3.5 py-3">
            <input
                :id="inputId"
                :checked="modelValue"
                type="checkbox"
                class="mt-0.5 h-4 w-4 shrink-0 rounded border-line-strong text-primary-600 focus:ring-primary-500/30"
                @change="emit('update:modelValue', $event.target.checked)"
            />
            <label :for="inputId" class="text-[10px] leading-relaxed text-muted">
                <template v-if="variant === 'contract'">
                    Подтверждаю, что клиент ознакомлен с
                    <a href="/legal/privacy" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline decoration-primary-200 underline-offset-2 hover:text-primary-800" @click.stop>политикой</a>
                    и уведомлён об обработке данных для исполнения договора.
                </template>
                <template v-else-if="variant === 'privacy'">
                    Я ознакомлен с
                    <a href="/legal/privacy" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline decoration-primary-200 underline-offset-2 hover:text-primary-800" @click.stop>политикой обработки персональных данных</a>.
                </template>
                <template v-else>
                    Я даю
                    <a href="/legal/consent" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline decoration-primary-200 underline-offset-2 hover:text-primary-800" @click.stop>согласие на обработку персональных данных</a>
                    и подтверждаю, что ознакомлен с
                    <a href="/legal/privacy" target="_blank" rel="noopener" class="font-semibold text-primary-700 underline decoration-primary-200 underline-offset-2 hover:text-primary-800" @click.stop>политикой</a>.
                </template>
            </label>
        </div>
        <p v-if="error" class="mt-1 text-[10px] text-danger-ink">{{ error }}</p>
    </div>
</template>
