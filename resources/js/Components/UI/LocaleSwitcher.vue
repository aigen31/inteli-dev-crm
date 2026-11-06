<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChevronDownIcon, GlobeAltIcon } from '@heroicons/vue/24/outline';
import { locales } from '@/i18n';

const props = defineProps({
    variant: { type: String, default: 'compact' },
});

const page = usePage();
const current = computed(() => page.props.locale || 'en');
const currentLabel = computed(() => locales.find((item) => item.code === current.value)?.label || 'English');
const isSettings = computed(() => props.variant === 'settings');

function change(event) {
    window.location.assign(route('locale.update', { locale: event.target.value }));
}
</script>

<template>
    <div
        data-i18n-skip
        :class="[
            'relative inline-flex items-center gap-2',
            isSettings
                ? 'w-full rounded-[20px] border border-line bg-white/55 px-4 py-3.5'
                : 'rounded-full border border-line bg-white/60 py-1.5 pl-2.5 pr-2',
        ]"
    >
        <GlobeAltIcon :class="isSettings ? 'h-5 w-5 text-primary-600' : 'h-3.5 w-3.5 text-muted'" />
        <span v-if="isSettings" class="min-w-0 flex-1">
            <span class="block text-[12px] font-bold text-ink">{{ $t('Язык интерфейса') }}</span>
            <span class="mt-0.5 block text-[10px] text-muted">{{ $t('Выберите язык интерфейса CRM.') }}</span>
        </span>
        <span class="hidden text-[9px] font-semibold uppercase tracking-[0.12em] text-muted sm:block">lang</span>
        <span :class="['min-w-0 truncate font-semibold text-ink', isSettings ? 'text-[12px]' : 'max-w-[88px] text-[11px]']">{{ currentLabel }}</span>
        <ChevronDownIcon :class="isSettings ? 'mr-1 h-4 w-4 text-muted' : 'h-3 w-3 text-muted'" class="pointer-events-none shrink-0" />
        <select
            :value="current"
            :aria-label="$t('Выберите язык')"
            class="absolute inset-0 h-full w-full cursor-pointer appearance-none rounded-[inherit] opacity-0"
            @change="change"
        >
            <option v-for="item in locales" :key="item.code" :value="item.code">
                {{ item.label }}
            </option>
        </select>
    </div>
</template>
