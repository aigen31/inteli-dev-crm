<script setup>
import { computed } from 'vue';
import {
    ArrowsRightLeftIcon,
    BugAntIcon,
    DocumentTextIcon,
    EyeIcon,
    GlobeAltIcon,
    KeyIcon,
    LightBulbIcon,
    PencilSquareIcon,
    QueueListIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    template: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

defineEmits(['select']);

const icons = {
    ArrowsRightLeftIcon,
    BugAntIcon,
    DocumentTextIcon,
    EyeIcon,
    GlobeAltIcon,
    KeyIcon,
    LightBulbIcon,
    PencilSquareIcon,
    QueueListIcon,
};

const tones = {
    violet: 'bg-primary-soft text-primary-700',
    peach: 'bg-peach/50 text-[#a35f3d]',
    sage: 'bg-sage/25 text-[#397254]',
    sky: 'bg-sky/20 text-[#3d718f]',
};

const Icon = computed(() => icons[props.template.icon] || DocumentTextIcon);
const tone = computed(() => tones[props.template.tone] || tones.violet);
const pills = computed(() => {
    const schema = props.template.schema || {};
    const fields = (schema.fields || []).slice(0, 2).map((field) => field.label);
    const groups = (schema.groups || []).map((group) => group.label);
    return [...fields, ...groups].slice(0, 3);
});
</script>

<template>
    <button
        type="button"
        :class="[
            'group relative flex w-full flex-col rounded-[24px] border bg-surface/85 p-5 text-left shadow-[0_12px_34px_rgba(28,24,35,0.04)] transition duration-200',
            selected
                ? 'border-primary-300 ring-2 ring-primary-200/80'
                : 'border-line hover:-translate-y-0.5 hover:border-line-strong hover:shadow-[0_18px_45px_rgba(28,24,35,0.07)]',
            compact ? 'sm:p-4' : '',
        ]"
        @click="$emit('select', template)"
    >
        <span v-if="selected" class="absolute right-4 top-4 rounded-full bg-primary-soft px-2 py-0.5 font-mono text-[8px] uppercase tracking-[0.1em] text-primary-700">выбрано</span>
        <span :class="['flex h-10 w-10 items-center justify-center rounded-[13px]', tone]">
            <component :is="Icon" class="h-[19px] w-[19px]" />
        </span>

        <h3 class="mt-4 text-[14px] font-bold leading-snug tracking-[-0.01em] text-ink">{{ template.name }}</h3>
        <p class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ template.summary }}</p>

        <div class="mt-3 flex flex-wrap gap-1.5">
            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">{{ template.field_count }} полей</span>
            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">{{ template.required_count }} обяз.</span>
            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] font-medium text-ink-soft">≈ {{ template.estimated_minutes }} мин</span>
        </div>

        <div v-if="!compact" class="mt-3 flex flex-wrap gap-1">
            <span v-for="pill in pills" :key="pill" class="rounded-full border border-line/70 px-2 py-0.5 text-[9px] text-muted">{{ pill }}</span>
        </div>
    </button>
</template>
