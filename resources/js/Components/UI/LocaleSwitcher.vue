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
            <span class="mt-0.5 block text-[10px] text-muted">{{ $t('Интерфейс CRM будет показан на выбранном языке. Это также влияет на письма и уведомления.') }}</span>
        </span>
        <span v-else class="hidden text-[9px] font-semibold uppercase tracking-[0.12em] text-muted sm:block">lang</span>
        <select
            :value="current"
            :aria-label="$t('Выберите язык')"
            :class="[
                'appearance-none bg-transparent font-semibold text-ink outline-none',
                isSettings ? 'rounded-xl border border-line bg-white/70 px-2.5 py-1.5 text-[12px]' : 'max-w-[88px] cursor-pointer border-0 bg-transparent p-0 text-[11px]',
            ]"
            @change="change"
        >
            <option v-for="item in locales" :key="item.code" :value="item.code">
                {{ item.label }}
            </option>
        </select>
        <ChevronDownIcon :class="isSettings ? 'mr-1 h-4 w-4 text-muted' : 'h-3 w-3 text-muted'" class="pointer-events-none shrink-0" />
    </div>
</template>
