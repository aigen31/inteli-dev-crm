<script setup>
import AppShell from '@/Components/Layout/AppShell.vue';

defineProps({
    title: { type: String, default: null },
    bare: { type: Boolean, default: false },
});
</script>

<template>
    <AppShell role="admin" :title="title" :bare="bare">
        <template #header-title>
            <slot name="header-title">{{ title }}</slot>
        </template>
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
        <slot />
    </AppShell>
</template>
