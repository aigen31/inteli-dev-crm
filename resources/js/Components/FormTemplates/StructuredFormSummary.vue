<script setup>
import { computed, reactive } from 'vue';
import { ArrowsPointingOutIcon } from '@heroicons/vue/24/outline';
import ImageLightbox from '@/Components/UI/ImageLightbox.vue';

const props = defineProps({
    template: { type: Object, required: true },
    data: { type: Object, default: () => ({}) },
});

const schema = computed(() => props.template?.schema || { fields: [], groups: [] });
const fieldsData = computed(() => props.data?.fields || {});
const groupsData = computed(() => props.data?.groups || {});
const lightbox = reactive({ open: false, index: 0 });

const images = computed(() => {
    const list = [];

    (schema.value.fields || []).forEach((field) => {
        const id = fieldsData.value[field.key];
        if (field.type === 'image' && hasValue(id)) {
            list.push({ id, alt: field.label, url: route('files.preview', id), download: route('files.download', id) });
        }
    });

    (schema.value.groups || []).forEach((group) => {
        (groupsData.value[group.key] || []).forEach((row, index) => {
            (group.fields || []).forEach((field) => {
                const id = row[field.key];
                if (field.type === 'image' && hasValue(id)) {
                    list.push({ id, alt: `${group.label} — ${field.label} ${index + 1}`, url: route('files.preview', id), download: route('files.download', id) });
                }
            });
        });
    });

    return list;
});

function openImage(id) {
    const index = images.value.findIndex((image) => image.id === id);
    lightbox.index = index >= 0 ? index : 0;
    lightbox.open = true;
}

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
                    <button type="button" class="group relative overflow-hidden rounded-[12px] border border-line bg-surface-2" @click="openImage(fieldsData[field.key])">
                        <img :src="route('files.preview', fieldsData[field.key])" :alt="field.label" class="h-24 w-32 object-cover transition group-hover:scale-[1.02]" loading="lazy" />
                        <span class="absolute inset-0 flex items-center justify-center bg-ink/25 opacity-0 transition group-hover:opacity-100">
                            <ArrowsPointingOutIcon class="h-4 w-4 text-white" />
                        </span>
                    </button>
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
                            <button v-if="field.type === 'image' && hasValue(row[field.key])" type="button" class="group relative mt-1 overflow-hidden rounded-[10px] border border-line bg-surface-2" @click="openImage(row[field.key])">
                                <img :src="route('files.preview', row[field.key])" :alt="field.label" class="h-16 w-20 object-cover transition group-hover:scale-[1.02]" loading="lazy" />
                                <span class="absolute inset-0 flex items-center justify-center bg-ink/25 opacity-0 transition group-hover:opacity-100">
                                    <ArrowsPointingOutIcon class="h-3.5 w-3.5 text-white" />
                                </span>
                            </button>
                            <p v-else class="mt-0.5 whitespace-pre-wrap text-[11px] leading-relaxed text-ink-soft">{{ hasValue(row[field.key]) ? labelFor(field, row[field.key]) : '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="mt-1 text-[11px] text-faint">Не заполнено.</p>
        </div>

        <ImageLightbox v-model:open="lightbox.open" v-model:index="lightbox.index" :images="images" />
    </div>
</template>
