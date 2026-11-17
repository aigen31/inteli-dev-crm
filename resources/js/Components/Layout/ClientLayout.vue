<script setup>
import AppShell from '@/Components/Layout/AppShell.vue';
import WidgetZone from '@/Components/Dashboard/WidgetZone.vue';

defineProps({
    title: { type: String, default: null },
    bare: { type: Boolean, default: false },
});
</script>

<template>
    <AppShell role="client" :title="title" :bare="bare">
        <template #header-title>
            <slot name="header-title">{{ title }}</slot>
        </template>
        <template v-if="$slots.actions" #actions>
            <slot name="actions" />
        </template>
        <slot />
        <WidgetZone />
    </AppShell>
</template>
