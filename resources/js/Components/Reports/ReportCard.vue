<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowUpRightIcon } from '@heroicons/vue/24/outline';
import { formatDateTime } from '@/Utils/formatters.js';

const props = defineProps({
    report: { type: Object, required: true },
    showUrl: { type: String, default: null },
});

const preview = computed(() => props.report.items?.[0]?.summary || props.report.summary || '');
const groups = computed(() => props.report.items?.length || 0);
const title = computed(() => props.report.display_title || props.report.title || 'Отчёт');
</script>

<template>
    <component
        :is="showUrl ? Link : 'article'"
        :href="showUrl || undefined"
        class="group relative flex h-full flex-col overflow-hidden rounded-[24px] border border-line bg-surface p-5 shadow-[0_14px_36px_rgba(28,24,35,0.05)] transition duration-200 hover:-translate-y-0.5 hover:border-primary-200 hover:shadow-[0_22px_48px_rgba(28,24,35,0.09)]"
    >
        <div class="flex items-center gap-2">
            <span
                :class="[
                    'h-1.5 w-1.5 rounded-full',
                    report.read_at ? 'bg-success' : 'bg-primary-500',
                ]"
            ></span>
            <span class="font-mono text-[9px] uppercase tracking-[0.14em] text-faint">
                {{ report.read_at ? 'прочитан' : 'новый отчёт' }}
            </span>
            <span class="ml-auto font-mono text-[9px] text-faint">{{ formatDateTime(report.sent_at) }}</span>
        </div>

        <h3 class="mt-3 line-clamp-2 font-display text-[19px] font-medium leading-snug tracking-[-0.03em] text-ink group-hover:text-primary-800">
            {{ title }}
        </h3>

        <p class="mt-2 line-clamp-3 text-[11px] leading-relaxed text-muted">{{ preview }}</p>

        <div class="mt-auto flex items-center gap-2 border-t border-line/70 pt-3 text-[10px] text-muted">
            <span v-if="groups" class="rounded-full border border-line bg-white/60 px-2 py-0.5 font-mono text-[9px] text-ink-soft">
                {{ groups }} {{ groups === 1 ? 'группа' : 'групп' }}
            </span>
            <span v-if="report.recipient" class="truncate">→ {{ report.recipient.name }}</span>
            <span class="ml-auto inline-flex items-center gap-1 font-semibold text-ink">
                Открыть
                <ArrowUpRightIcon class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
            </span>
        </div>
    </component>
</template>
