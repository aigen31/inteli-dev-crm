<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: null },
});
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center px-4 py-12">
        <Head :title="title" />

        <div class="w-full max-w-[420px]">
            <div class="mb-8 flex items-center justify-center gap-2">
                <span class="text-2xl font-bold tracking-tight text-ink">inteli dev</span>
                <span
                    class="rounded-full border border-line px-2 py-[3px] font-mono text-[9px] uppercase tracking-[0.18em] text-muted"
                >
                    CRM
                </span>
            </div>

            <div
                class="rounded-[20px] border border-line bg-surface/90 p-8 shadow-[0_16px_40px_rgba(0,0,0,0.4)] backdrop-blur-sm"
            >
                <slot />
            </div>

            <p class="mt-8 text-center font-mono text-[10px] uppercase tracking-[0.18em] text-faint">
                inteli dev · crm
            </p>
        </div>
    </div>
</template>
