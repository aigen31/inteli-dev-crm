<script setup>
import { computed } from 'vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const inputType = computed(() => {
    switch (props.field.type) {
        case 'number':
        case 'currency':
            return 'number';
        case 'date':
            return 'date';
        case 'email':
            return 'email';
        case 'url':
            return 'url';
        case 'phone':
            return 'tel';
        default:
            return 'text';
    }
});

const value = computed({
    get: () => props.modelValue ?? '',
    set: (next) => emit('update:modelValue', next),
});

const multiValue = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));

function toggleOption(optionValue) {
    const current = [...multiValue.value];
    const index = current.indexOf(optionValue);

    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push(optionValue);
    }

    emit('update:modelValue', current);
}
</script>

<template>
    <div :class="field.type === 'textarea' ? 'sm:col-span-2' : ''">
        <TextArea
            v-if="field.type === 'textarea'"
            v-model="value"
            :label="field.label"
            :error="error"
            :hint="field.help_text"
            :placeholder="field.placeholder || ''"
            :rows="4"
        />

        <SelectInput
            v-else-if="field.type === 'select'"
            v-model="value"
            :label="field.label"
            :error="error"
            :options="field.options || []"
            :placeholder="field.placeholder || 'Не выбрано'"
        />

        <div v-else-if="field.type === 'multiselect'">
            <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">{{ field.label }}</p>
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="option in field.options || []"
                    :key="option.value"
                    type="button"
                    :class="[
                        'rounded-full border px-3 py-1.5 text-[11px] font-medium transition',
                        multiValue.includes(option.value)
                            ? 'border-primary-400 bg-primary-soft text-primary-800'
                            : 'border-line bg-white/60 text-ink-soft hover:border-line-strong',
                    ]"
                    @click="toggleOption(option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
            <p v-if="field.help_text" class="mt-1 text-[10px] text-muted">{{ field.help_text }}</p>
            <p v-if="error" class="mt-1 text-[10px] text-danger-ink">{{ error }}</p>
        </div>

        <label v-else-if="field.type === 'checkbox'" class="flex cursor-pointer items-start gap-2.5 rounded-[13px] border border-line bg-white/60 px-3.5 py-3">
            <input
                type="checkbox"
                :checked="Boolean(modelValue)"
                class="mt-0.5 h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30"
                @change="emit('update:modelValue', $event.target.checked)"
            />
            <span>
                <span class="block text-[12px] font-semibold text-ink-soft">{{ field.label }}</span>
                <span v-if="field.help_text" class="mt-0.5 block text-[10px] text-muted">{{ field.help_text }}</span>
            </span>
        </label>

        <TextInput
            v-else
            v-model="value"
            :label="field.type === 'currency' ? `${field.label}, ₽` : field.label"
            :type="inputType"
            :error="error"
            :hint="field.help_text"
            :placeholder="field.placeholder || ''"
            :step="field.type === 'currency' ? '0.01' : undefined"
            :min="field.type === 'currency' ? 0 : undefined"
        />
    </div>
</template>
