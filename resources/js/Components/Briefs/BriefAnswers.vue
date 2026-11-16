<script setup>
const props = defineProps({
    sections: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false },
    hideEmpty: { type: Boolean, default: false },
});

function rowsFor(section) {
    return props.hideEmpty ? section.rows.filter((row) => row.answered) : section.rows;
}
</script>

<template>
    <div class="space-y-4">
        <section
            v-for="section in sections"
            :key="section.key"
            :class="compact ? '' : 'surface-card overflow-hidden'"
        >
            <header :class="compact ? 'mb-2' : 'border-b border-line/80 bg-surface-2/40 px-5 py-3'">
                <h3 class="text-[13px] font-bold tracking-[-0.01em] text-ink">{{ section.title }}</h3>
                <p v-if="section.description" class="mt-0.5 text-[10px] text-faint">{{ section.description }}</p>
            </header>

            <div :class="compact ? 'space-y-2' : 'divide-y divide-line/60'">
                <div
                    v-for="row in rowsFor(section)"
                    :key="row.key"
                    :class="compact ? '' : 'px-5 py-3'"
                >
                    <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-faint">{{ row.label }}</p>
                    <p
                        :class="[
                            'mt-1 whitespace-pre-line text-[12px] leading-relaxed',
                            row.answered ? 'text-ink' : 'text-faint italic',
                        ]"
                    >
                        {{ row.answered ? row.display : 'Не заполнено' }}
                    </p>
                </div>
                <p v-if="hideEmpty && !rowsFor(section).length" class="px-5 py-3 text-[11px] italic text-faint">
                    В этой секции пока нет ответов
                </p>
            </div>
        </section>
    </div>
</template>
