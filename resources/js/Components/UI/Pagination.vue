<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="links.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-line/80 px-5 py-3">
        <p class="font-mono text-[10px] uppercase tracking-[0.12em] text-muted">
            {{ links.from }}–{{ links.to }} / {{ links.total }}
        </p>
        <div class="flex flex-wrap gap-1">
            <template v-for="link in links.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'rounded-full border px-3 py-1.5 text-[11px] transition',
                        link.active
                            ? 'border-primary-500 bg-primary-500 text-white'
                            : 'border-line bg-white/50 text-ink-soft hover:border-line-strong hover:text-ink',
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
                <span v-else class="cursor-not-allowed rounded-full border border-line px-3 py-1.5 text-[11px] text-faint opacity-50" v-html="link.label" />
            </template>
        </div>
    </nav>
</template>
