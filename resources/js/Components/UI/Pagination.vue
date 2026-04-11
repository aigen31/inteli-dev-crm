<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="links.last_page > 1" class="flex items-center justify-between px-6 py-3">
        <p class="text-sm text-gray-500">
            Показано {{ links.from }}–{{ links.to }} из {{ links.total }}
        </p>
        <div class="flex space-x-1">
            <template v-for="link in links.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-md border',
                        link.active
                            ? 'bg-primary-500 text-white border-primary-500'
                            : 'border-gray-300 hover:bg-gray-50 text-gray-700',
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    :class="['px-3 py-1.5 text-sm rounded-md border border-gray-300 opacity-50 cursor-not-allowed text-gray-400']"
                    v-html="link.label"
                />
            </template>
        </div>
    </nav>
</template>
