<script setup>
import { computed } from 'vue';
import { ArrowDownTrayIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    template: { type: Object, required: true },
    data: { type: Object, default: () => ({}) },
});

const schema = computed(() => props.template?.schema || { fields: [], groups: [] });
const fieldsData = computed(() => props.data?.fields || {});
const groupsData = computed(() => props.data?.groups || {});

function labelFor(field, value) {
    if (field.type === 'select') {
        return (field.options || []).find((option) => option.value === value)?.label || value;
    }
    if (field.type === 'checkbox') return value ? 'Да' : 'Нет';
    return value;
}

function hasValue(value) {
    return value !== null && value !== undefined && value !== '';
}
</script>

<template>
    <div class="space-y-5">
        <div class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div v-for="field in schema.fields" :key="field.key">
                <p class="text-[10px] font-semibold uppercase tracking-[0.07em] text-muted">{{ field.label }}</p>
                <div v-if="field.type === 'image' && hasValue(fieldsData[field.key])" class="mt-1">
                    <a :href="route('files.download', fieldsData[field.key])" target="_blank" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-primary-700 hover:underline">
                        <ArrowDownTrayIcon class="h-3.5 w-3.5" /> Изображение
                    </a>
                </div>
                <p v-else class="mt-1 whitespace-pre-wrap text-[12px] leading-relaxed text-ink-soft">
                    {{ hasValue(fieldsData[field.key]) ? labelFor(field, fieldsData[field.key]) : '—' }}
                </p>
            </div>
        </div>

        <div v-for="group in schema.groups" :key="group.key" class="border-t border-line/70 pt-4">
            <div class="flex items-center gap-2">
                <p class="text-[10px] font-semibold uppercase tracking-[0.07em] text-muted">{{ group.label }}</p>
                <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-[8px] text-faint">{{ (groupsData[group.key] || []).length }}</span>
            </div>
            <div v-if="(groupsData[group.key] || []).length" class="mt-2 space-y-2">
                <div v-for="(row, index) in groupsData[group.key]" :key="index" class="rounded-[14px] border border-line/70 bg-surface-2/30 p-3">
                    <p class="font-mono text-[9px] text-faint">{{ String(index + 1).padStart(2, '0') }}</p>
                    <div class="mt-1.5 grid grid-cols-1 gap-x-5 gap-y-2 sm:grid-cols-2">
                        <div v-for="field in group.fields || []" :key="field.key">
                            <p class="text-[9px] font-semibold uppercase tracking-[0.06em] text-muted">{{ field.label }}</p>
                            <a v-if="field.type === 'image' && hasValue(row[field.key])" :href="route('files.download', row[field.key])" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline">
                                <ArrowDownTrayIcon class="h-3 w-3" /> Скачать
                            </a>
                            <p v-else class="mt-0.5 whitespace-pre-wrap text-[11px] leading-relaxed text-ink-soft">{{ hasValue(row[field.key]) ? labelFor(field, row[field.key]) : '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="mt-1 text-[11px] text-faint">Не заполнено.</p>
        </div>
    </div>
</template>
