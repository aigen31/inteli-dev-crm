<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="links.last_page > 1" class="flex items-center justify-between px-6 py-3">
        <p class="font-mono text-[11px] uppercase tracking-[0.12em] text-muted">
            {{ links.from }}–{{ links.to }} / {{ links.total }}
        </p>
        <div class="flex space-x-1">
            <template v-for="link in links.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'rounded-[14px] border px-3 py-1.5 text-sm transition-colors',
                        link.active
                            ? 'border-primary-500 bg-primary-500 text-white'
                            : 'border-line text-ink-soft hover:border-line-strong hover:bg-white/5 hover:text-white',
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    class="cursor-not-allowed rounded-[14px] border border-line px-3 py-1.5 text-sm text-faint opacity-50"
                    v-html="link.label"
                />
            </template>
        </div>
    </nav>
</template>
