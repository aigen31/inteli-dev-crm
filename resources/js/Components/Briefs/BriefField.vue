<script setup>
import { computed, ref } from 'vue';
import { contactFields } from '@/Utils/briefs.js';
import { CloudArrowUpIcon, DocumentIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    error: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const uploading = ref(false);
const uploadError = ref(null);

const inputClass =
    'w-full rounded-[12px] border border-line bg-white/70 px-3 py-2.5 text-[13px] text-ink placeholder:text-faint transition focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20 disabled:opacity-60';

const errorClass = computed(() => (props.error ? 'border-danger/60 focus:border-danger focus:ring-danger/20' : ''));
const describedBy = computed(() => (props.error ? `${props.field.key}-error` : props.field.help ? `${props.field.key}-help` : undefined));

const value = computed({
    get: () => props.modelValue,
    set: (next) => emit('update:modelValue', next),
});

const textValue = computed({
    get: () => props.modelValue ?? '',
    set: (next) => emit('update:modelValue', next),
});

const numberValue = computed({
    get: () => (props.modelValue === null || props.modelValue === undefined ? '' : props.modelValue),
    set: (next) => emit('update:modelValue', next === '' ? null : Number(next)),
});

const multiValue = computed(() => new Set(Array.isArray(props.modelValue) ? props.modelValue : []));

function toggleMulti(optionValue) {
    const next = new Set(multiValue.value);
    if (next.has(optionValue)) next.delete(optionValue);
    else next.add(optionValue);
    emit('update:modelValue', [...next]);
}

const scaleValues = computed(() => {
    const min = Number(props.field.validation?.min ?? 1);
    const max = Number(props.field.validation?.max ?? 5);
    const step = Number(props.field.validation?.step ?? 1) || 1;
    const values = [];
    for (let current = min; current <= max; current += step) values.push(current);
    return values;
});

const contactFieldsList = computed(() => contactFields(props.field));
const contactValue = computed({
    get: () => (props.modelValue && typeof props.modelValue === 'object' ? props.modelValue : {}),
    set: (next) => emit('update:modelValue', next),
});

function setContact(key, next) {
    emit('update:modelValue', { ...contactValue.value, [key]: next });
}

const uploadedName = ref(null);

async function uploadFile(event) {
    const file = event.target.files?.[0];

    if (!file) return;

    uploadError.value = null;
    uploading.value = true;

    try {
        const body = new FormData();
        body.append('file', file);
        const { data } = await window.axios.post(route('files.upload'), body);
        emit('update:modelValue', data.id);
        uploadedName.value = data.original_name;
    } catch (error) {
        uploadError.value = error?.response?.data?.message || 'Не удалось загрузить файл';
    } finally {
        uploading.value = false;
        event.target.value = '';
    }
}
</script>

<template>
    <div>
        <label :for="field.key" class="flex flex-wrap items-baseline gap-1.5">
            <span class="text-[11px] font-semibold text-ink-soft">{{ field.label }}</span>
            <span v-if="field.required" class="text-[10px] text-danger">*</span>
            <span v-if="field.validation?.unit" class="font-mono text-[9px] text-faint">{{ field.validation.unit }}</span>
        </label>

        <p v-if="field.help" :id="`${field.key}-help`" class="mt-1 text-[10px] leading-relaxed text-faint">{{ field.help }}</p>

        <!-- text / url / email / phone / date -->
        <input
            v-if="['text', 'url', 'email', 'phone', 'date'].includes(field.type)"
            :id="field.key"
            v-model="textValue"
            :type="field.type === 'text' ? 'text' : field.type"
            :inputmode="field.type === 'phone' ? 'tel' : undefined"
            :placeholder="field.placeholder || ''"
            :maxlength="field.validation?.max_length || undefined"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="[inputClass, errorClass, field.type === 'date' ? 'pr-2' : '']"
        >

        <!-- number / scale -->
        <input
            v-else-if="field.type === 'number'"
            :id="field.key"
            v-model="numberValue"
            type="number"
            :min="field.validation?.min"
            :max="field.validation?.max"
            :step="field.validation?.step || 'any'"
            :placeholder="field.placeholder || ''"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="[inputClass, errorClass]"
        >

        <!-- textarea -->
        <textarea
            v-else-if="field.type === 'textarea'"
            :id="field.key"
            v-model="textValue"
            :rows="field.rows || 4"
            :maxlength="field.validation?.max_length || undefined"
            :placeholder="field.placeholder || ''"
            :disabled="disabled"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="describedBy"
            :class="[inputClass, errorClass, 'min-h-[96px] resize-y leading-relaxed']"
        />

        <!-- single choice -->
        <div v-else-if="field.type === 'single_choice'" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button
                v-for="option in field.options"
                :key="option.value"
                type="button"
                :disabled="disabled"
                :class="[
                    'rounded-[12px] border px-3 py-2.5 text-left text-[12px] transition',
                    modelValue === option.value
                        ? 'border-primary-500/60 bg-primary-soft/60 text-ink ring-2 ring-primary-500/15'
                        : 'border-line bg-white/60 text-ink-soft hover:border-line-strong',
                ]"
                @click="emit('update:modelValue', option.value)"
            >
                <span class="flex items-center gap-2">
                    <span
                        :class="[
                            'grid h-4 w-4 shrink-0 place-items-center rounded-full border',
                            modelValue === option.value ? 'border-primary-500 bg-primary-500' : 'border-line-strong',
                        ]"
                    >
                        <span v-if="modelValue === option.value" class="h-1.5 w-1.5 rounded-full bg-white" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ option.label }}</span>
                        <span v-if="option.description" class="mt-0.5 block text-[10px] text-muted">{{ option.description }}</span>
                    </span>
                </span>
            </button>

            <button
                v-if="field.allow_other"
                type="button"
                :disabled="disabled"
                :class="[
                    'rounded-[12px] border px-3 py-2.5 text-left text-[12px] transition',
                    modelValue === 'other'
                        ? 'border-primary-500/60 bg-primary-soft/60 text-ink ring-2 ring-primary-500/15'
                        : 'border-line bg-white/60 text-ink-soft hover:border-line-strong',
                ]"
                @click="emit('update:modelValue', 'other')"
            >
                <span class="font-semibold">Другое</span>
            </button>
        </div>

        <!-- multi choice -->
        <div v-else-if="field.type === 'multi_choice'" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button
                v-for="option in field.options"
                :key="option.value"
                type="button"
                :disabled="disabled"
                :class="[
                    'rounded-[12px] border px-3 py-2.5 text-left text-[12px] transition',
                    multiValue.has(option.value)
                        ? 'border-primary-500/60 bg-primary-soft/60 text-ink ring-2 ring-primary-500/15'
                        : 'border-line bg-white/60 text-ink-soft hover:border-line-strong',
                ]"
                :aria-pressed="multiValue.has(option.value) ? 'true' : 'false'"
                @click="toggleMulti(option.value)"
            >
                <span class="flex items-start gap-2">
                    <span
                        :class="[
                            'mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-[5px] border',
                            multiValue.has(option.value) ? 'border-primary-500 bg-primary-500 text-white' : 'border-line-strong',
                        ]"
                    >
                        <svg v-if="multiValue.has(option.value)" viewBox="0 0 12 12" class="h-2.5 w-2.5" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M2 6.2 4.6 8.8 10 3.4" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ option.label }}</span>
                        <span v-if="option.description" class="mt-0.5 block text-[10px] text-muted">{{ option.description }}</span>
                    </span>
                </span>
            </button>
        </div>

        <!-- scale -->
        <div v-else-if="field.type === 'scale'" class="mt-2">
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="scaleValue in scaleValues"
                    :key="scaleValue"
                    type="button"
                    :disabled="disabled"
                    :class="[
                        'h-10 min-w-10 rounded-[11px] border px-2 font-mono text-[12px] transition',
                        Number(modelValue) === scaleValue
                            ? 'border-primary-500 bg-primary-500 text-white'
                            : 'border-line bg-white/60 text-ink-soft hover:border-line-strong',
                    ]"
                    @click="emit('update:modelValue', scaleValue)"
                >
                    {{ scaleValue }}
                </button>
            </div>
            <div class="mt-1.5 flex justify-between font-mono text-[9px] text-faint">
                <span>{{ field.validation?.min_label || field.validation?.min || 1 }}</span>
                <span>{{ field.validation?.max_label || field.validation?.max || 5 }}</span>
            </div>
        </div>

        <!-- contact block -->
        <div v-else-if="field.type === 'contact_block'" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
            <label v-for="contact in contactFieldsList" :key="contact.key" class="block">
                <span class="text-[10px] font-semibold text-muted">{{ contact.label }}</span>
                <input
                    :type="contact.type === 'phone' ? 'tel' : contact.type === 'email' ? 'email' : 'text'"
                    :value="contactValue?.[contact.key] || ''"
                    :disabled="disabled"
                    :class="[inputClass, 'mt-1']"
                    @input="setContact(contact.key, $event.target.value)"
                >
            </label>
        </div>

        <!-- file -->
        <div v-else-if="field.type === 'file'" class="mt-2">
            <label
                :class="[
                    'flex cursor-pointer items-center gap-3 rounded-[12px] border border-dashed px-3 py-3 text-[12px] transition',
                    error ? 'border-danger/60' : 'border-line-strong hover:border-primary-500/60 hover:bg-primary-soft/30',
                ]"
            >
                <span class="grid h-9 w-9 place-items-center rounded-[10px] bg-surface-2 text-primary-700">
                    <CloudArrowUpIcon class="h-5 w-5" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold text-ink">{{ uploading ? 'Загружаю…' : uploadedName || (modelValue ? 'Файл прикреплён' : 'Прикрепить файл') }}</span>
                    <span class="mt-0.5 block text-[10px] text-faint">
                        {{ field.validation?.accept?.join(', ') || 'PDF, DOC, изображения' }}
                        <template v-if="field.validation?.max_size_mb"> · до {{ field.validation.max_size_mb }} МБ</template>
                    </span>
                </span>
                <DocumentIcon v-if="modelValue" class="h-4 w-4 text-success" />
                <input type="file" class="hidden" :disabled="disabled || uploading" @change="uploadFile">
            </label>
            <p v-if="uploadError" class="mt-1 text-[10px] text-danger">{{ uploadError }}</p>
        </div>

        <p v-else class="mt-2 text-[11px] text-faint">Тип поля «{{ field.type }}» пока не поддерживается в мастере.</p>

        <p v-if="error" :id="`${field.key}-error`" class="mt-1.5 text-[10px] font-medium text-danger" role="alert">{{ error }}</p>
    </div>
</template>
