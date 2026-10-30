<script setup>
import { computed, reactive } from 'vue';
import axios from 'axios';
import { ArrowUpTrayIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    template: { type: Object, required: true },
    modelValue: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const schema = computed(() => props.template.schema || { fields: [], groups: [] });
const uploading = reactive({});
const uploadErrors = reactive({});

function errorFor(path) {
    return props.errors?.[path] || null;
}

function setField(key, value) {
    props.modelValue.fields[key] = value;
}

function setGroupField(group, rowIndex, key, value) {
    props.modelValue.groups[group.key][rowIndex][key] = value;
}

function addRow(group) {
    const row = {};
    (group.fields || []).forEach((field) => {
        row[field.key] = field.type === 'checkbox' ? false : '';
    });
    (props.modelValue.groups[group.key] ||= []).push(row);
}

function removeRow(group, rowIndex) {
    const rows = props.modelValue.groups[group.key] || [];
    if (rows.length <= Number(group.min || 0)) return;
    rows.splice(rowIndex, 1);
}

function canRemove(group) {
    return (props.modelValue.groups[group.key] || []).length > Number(group.min || 0);
}

function inputType(field) {
    return { email: 'email', url: 'url', number: 'number', date: 'date' }[field.type] || 'text';
}

function fileUrl(id) {
    return id ? route('files.download', id) : null;
}

async function uploadFile(field, event, path, setter) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    uploading[path] = true;
    uploadErrors[path] = null;

    const data = new FormData();
    data.append('file', file);

    try {
        const response = await axios.post(route('files.upload'), data);
        setter(response.data.id);
    } catch (error) {
        uploadErrors[path] = error?.response?.data?.message || 'Не удалось загрузить файл.';
    } finally {
        uploading[path] = false;
    }
}
</script>

<template>
    <div class="space-y-5">
        <div v-for="field in schema.fields" :key="field.key">
            <div class="mb-1.5 flex items-baseline justify-between gap-3">
                <label :for="`field-${field.key}`" class="text-[11px] font-semibold text-ink-soft">
                    {{ field.label }}<span v-if="field.required" class="ml-0.5 text-primary-600">*</span>
                </label>
                <span v-if="field.help" class="text-right text-[9px] leading-snug text-faint">{{ field.help }}</span>
            </div>

            <textarea
                v-if="field.type === 'textarea'"
                :id="`field-${field.key}`"
                :value="modelValue.fields[field.key]"
                :placeholder="field.placeholder"
                rows="3"
                :class="['w-full rounded-[15px] border bg-white/70 px-3.5 py-3 text-[12px] leading-relaxed text-ink placeholder-faint transition focus:border-primary-400 focus:outline-none', errorFor(`form_data.fields.${field.key}`) ? 'border-danger/60' : 'border-line']"
                @input="setField(field.key, $event.target.value)"
            ></textarea>

            <select
                v-else-if="field.type === 'select'"
                :id="`field-${field.key}`"
                :value="modelValue.fields[field.key]"
                :class="['h-10 w-full rounded-[13px] border bg-white/70 px-3.5 text-[12px] text-ink focus:border-primary-400 focus:outline-none', errorFor(`form_data.fields.${field.key}`) ? 'border-danger/60' : 'border-line']"
                @change="setField(field.key, $event.target.value)"
            >
                <option value="">Выберите вариант</option>
                <option v-for="option in field.options || []" :key="option.value" :value="option.value">{{ option.label }}</option>
            </select>

            <label v-else-if="field.type === 'checkbox'" class="flex cursor-pointer items-start gap-2.5 rounded-[15px] border border-line bg-white/50 px-3.5 py-3 text-[11px] leading-relaxed text-ink-soft">
                <input
                    type="checkbox"
                    :checked="Boolean(modelValue.fields[field.key])"
                    class="mt-0.5 h-4 w-4 rounded border-line-strong text-primary-500 focus:ring-primary-500/30"
                    @change="setField(field.key, $event.target.checked)"
                />
                {{ field.label }}
            </label>

            <div v-else-if="field.type === 'image'">
                <div v-if="modelValue.fields[field.key]" class="flex items-center gap-3 rounded-[15px] border border-line bg-white/50 p-2.5">
                    <img :src="fileUrl(modelValue.fields[field.key])" alt="" class="h-14 w-14 rounded-[10px] object-cover" />
                    <span class="text-[10px] text-muted">Изображение прикреплено</span>
                    <button type="button" class="ml-auto rounded-lg p-1.5 text-muted hover:text-danger" @click="setField(field.key, '')">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>
                <label v-else class="flex cursor-pointer items-center gap-2 rounded-[15px] border border-dashed border-line px-3.5 py-3 text-[10px] font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-50/40">
                    <ArrowUpTrayIcon class="h-4 w-4" />
                    {{ uploading[`form_data.fields.${field.key}`] ? 'Загружаю…' : 'Прикрепить изображение' }}
                    <input type="file" accept="image/*" class="sr-only" @change="uploadFile(field, $event, `form_data.fields.${field.key}`, (id) => setField(field.key, id))" />
                </label>
                <p v-if="uploadErrors[`form_data.fields.${field.key}`]" class="mt-1 text-[10px] text-danger-ink">{{ uploadErrors[`form_data.fields.${field.key}`] }}</p>
            </div>

            <input
                v-else
                :id="`field-${field.key}`"
                :type="inputType(field)"
                :value="modelValue.fields[field.key]"
                :placeholder="field.placeholder"
                :class="['h-10 w-full rounded-[13px] border bg-white/70 px-3.5 text-[12px] text-ink placeholder-faint transition focus:border-primary-400 focus:outline-none', errorFor(`form_data.fields.${field.key}`) ? 'border-danger/60' : 'border-line']"
                @input="setField(field.key, $event.target.value)"
            />

            <p v-if="errorFor(`form_data.fields.${field.key}`)" class="mt-1 text-[10px] text-danger-ink">{{ errorFor(`form_data.fields.${field.key}`) }}</p>
        </div>

        <section v-for="group in schema.groups" :key="group.key" class="rounded-[18px] border border-line/80 bg-surface-2/35 p-3.5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold text-ink">{{ group.label }}<span v-if="Number(group.min) > 0" class="ml-0.5 text-primary-600">*</span></p>
                    <p v-if="group.help" class="mt-0.5 text-[9px] leading-relaxed text-muted">{{ group.help }}</p>
                </div>
                <span class="shrink-0 font-mono text-[9px] text-faint">{{ (modelValue.groups[group.key] || []).length }} / {{ group.max || 20 }}</span>
            </div>

            <div class="mt-3 space-y-2.5">
                <div v-for="(row, rowIndex) in modelValue.groups[group.key] || []" :key="rowIndex" class="rounded-[15px] border border-line bg-white/60 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <span class="font-mono text-[9px] text-faint">{{ String(rowIndex + 1).padStart(2, '0') }}</span>
                        <button v-if="canRemove(group)" type="button" class="rounded-lg p-1 text-muted transition hover:text-danger" @click="removeRow(group, rowIndex)">
                            <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="mt-2 space-y-2.5">
                        <div v-for="field in group.fields || []" :key="field.key">
                            <label class="mb-1 block text-[10px] font-semibold text-ink-soft">
                                {{ field.label }}<span v-if="field.required" class="ml-0.5 text-primary-600">*</span>
                            </label>
                            <textarea
                                v-if="field.type === 'textarea'"
                                :value="row[field.key]"
                                :placeholder="field.placeholder"
                                rows="2"
                                class="w-full rounded-[12px] border border-line bg-white/75 px-3 py-2 text-[11px] leading-relaxed text-ink placeholder-faint focus:border-primary-400 focus:outline-none"
                                @input="setGroupField(group, rowIndex, field.key, $event.target.value)"
                            ></textarea>
                            <div v-else-if="field.type === 'image'">
                                <div v-if="row[field.key]" class="flex items-center gap-2 rounded-[12px] border border-line bg-white/60 p-2">
                                    <img :src="fileUrl(row[field.key])" alt="" class="h-10 w-10 rounded-[8px] object-cover" />
                                    <span class="text-[9px] text-muted">Прикреплено</span>
                                    <button type="button" class="ml-auto rounded-lg p-1 text-muted hover:text-danger" @click="setGroupField(group, rowIndex, field.key, '')"><TrashIcon class="h-3.5 w-3.5" /></button>
                                </div>
                                <label v-else class="flex cursor-pointer items-center gap-1.5 rounded-[12px] border border-dashed border-line px-3 py-2 text-[9px] font-semibold text-primary-700 hover:border-primary-300">
                                    <ArrowUpTrayIcon class="h-3.5 w-3.5" />
                                    {{ uploading[`form_data.groups.${group.key}.${rowIndex}.${field.key}`] ? 'Загружаю…' : 'Прикрепить скриншот' }}
                                    <input type="file" accept="image/*" class="sr-only" @change="uploadFile(field, $event, `form_data.groups.${group.key}.${rowIndex}.${field.key}`, (id) => setGroupField(group, rowIndex, field.key, id))" />
                                </label>
                                <p v-if="uploadErrors[`form_data.groups.${group.key}.${rowIndex}.${field.key}`]" class="mt-1 text-[9px] text-danger-ink">{{ uploadErrors[`form_data.groups.${group.key}.${rowIndex}.${field.key}`] }}</p>
                            </div>
                            <select
                                v-else-if="field.type === 'select'"
                                :value="row[field.key]"
                                class="h-9 w-full rounded-[12px] border border-line bg-white/75 px-2.5 text-[11px] text-ink focus:border-primary-400 focus:outline-none"
                                @change="setGroupField(group, rowIndex, field.key, $event.target.value)"
                            >
                                <option value="">Выберите</option>
                                <option v-for="option in field.options || []" :key="option.value" :value="option.value">{{ option.label }}</option>
                            </select>
                            <input
                                v-else
                                :type="inputType(field)"
                                :value="row[field.key]"
                                :placeholder="field.placeholder"
                                class="h-9 w-full rounded-[12px] border border-line bg-white/75 px-2.5 text-[11px] text-ink placeholder-faint focus:border-primary-400 focus:outline-none"
                                @input="setGroupField(group, rowIndex, field.key, $event.target.value)"
                            />
                            <p v-if="errorFor(`form_data.groups.${group.key}.${rowIndex}.${field.key}`)" class="mt-0.5 text-[9px] text-danger-ink">{{ errorFor(`form_data.groups.${group.key}.${rowIndex}.${field.key}`) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <button
                v-if="(modelValue.groups[group.key] || []).length < (group.max || 20)"
                type="button"
                class="mt-2.5 flex w-full items-center justify-center gap-1.5 rounded-[12px] border border-dashed border-line py-2 text-[10px] font-semibold text-primary-700 transition hover:border-primary-300 hover:bg-primary-50/40"
                @click="addRow(group)"
            >
                <PlusIcon class="h-3.5 w-3.5" />
                {{ group.add_label || 'Добавить' }}
            </button>
            <p v-if="errorFor(`form_data.groups.${group.key}`)" class="mt-1 text-[9px] text-danger-ink">{{ errorFor(`form_data.groups.${group.key}`) }}</p>
        </section>
    </div>
</template>
