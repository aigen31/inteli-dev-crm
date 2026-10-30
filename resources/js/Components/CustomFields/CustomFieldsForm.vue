<script setup>
import CustomFieldInput from '@/Components/CustomFields/CustomFieldInput.vue';

const props = defineProps({
    fields: { type: Array, default: () => [] },
    modelValue: { type: Object, default: () => ({}) },
    errors: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

function valueFor(field) {
    if (Object.prototype.hasOwnProperty.call(props.modelValue, field.key)) {
        return props.modelValue[field.key];
    }

    return field.default_value ?? null;
}

function update(field, value) {
    emit('update:modelValue', {
        ...props.modelValue,
        [field.key]: value,
    });
}

function errorFor(field) {
    return props.errors?.[`custom_fields.${field.key}`] ?? null;
}
</script>

<template>
    <div v-if="fields.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <CustomFieldInput
            v-for="field in fields"
            :key="field.key"
            :field="field"
            :model-value="valueFor(field)"
            :error="errorFor(field)"
            @update:model-value="update(field, $event)"
        />
    </div>
</template>
