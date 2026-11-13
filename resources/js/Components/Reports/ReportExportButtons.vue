<script setup>
import { DocumentArrowDownIcon, TableCellsIcon } from '@heroicons/vue/24/outline';

defineProps({ reportId: { type: String, required: true } });
</script>

<template>
    <div class="inline-flex items-center gap-1.5">
        <a :href="route('reports.export.xlsx', reportId)" class="inline-flex items-center gap-1.5 rounded-[10px] border border-line bg-white/60 px-2.5 py-2 text-[10px] font-semibold text-ink-soft transition hover:border-sage/50 hover:text-ink" title="Скачать Excel">
            <TableCellsIcon class="h-3.5 w-3.5" />Excel
        </a>
        <a :href="route('reports.export.pdf', reportId)" class="inline-flex items-center gap-1.5 rounded-[10px] border border-line bg-white/60 px-2.5 py-2 text-[10px] font-semibold text-ink-soft transition hover:border-danger/30 hover:text-danger-ink" title="Скачать PDF">
            <DocumentArrowDownIcon class="h-3.5 w-3.5" />PDF
        </a>
    </div>
</template>
