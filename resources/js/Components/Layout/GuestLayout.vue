<script setup>
import { Head } from '@inertiajs/vue3';
import LocaleSwitcher from '@/Components/UI/LocaleSwitcher.vue';

defineProps({
    title: { type: String, default: null },
});
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center px-4 py-12">
        <Head :title="title" />

        <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
            <LocaleSwitcher />
        </div>

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

            <div class="mt-6 flex flex-wrap items-center justify-center gap-x-3 gap-y-1 text-[10px] text-muted">
                <a href="/legal/privacy" class="hover:text-ink">Политика</a>
                <a href="/legal/consent" class="hover:text-ink">Согласие</a>
                <a href="/legal/requisites" class="hover:text-ink">Реквизиты</a>
            </div>

            <p class="mt-3 text-center font-mono text-[10px] uppercase tracking-[0.18em] text-faint">
                inteli dev · crm
            </p>
        </div>
    </div>
</template>
