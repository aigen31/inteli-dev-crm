<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import SystemProjectCards from '@/Components/Projects/SystemProjectCards.vue';
import { ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    systemProjects: { type: Array, default: () => [] },
});

const page = usePage();
const isAdmin = computed(() => page.props.auth?.user?.is_admin === true);
const layout = computed(() => (isAdmin.value ? AdminLayout : ClientLayout));
</script>

<template>
    <component :is="layout" title="Обратная связь">
        <section class="relative mb-5 overflow-hidden rounded-[28px] border border-line bg-gradient-to-br from-[#fbf7ef] via-[#f5f0fb] to-[#ece5fa] px-5 py-5 sm:px-7 sm:py-6">
            <div class="pointer-events-none absolute -right-12 -top-20 h-56 w-56 rounded-full bg-primary-500/10 blur-3xl"></div>
            <div class="relative flex flex-wrap items-center gap-4">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[15px] bg-primary-soft text-primary-700">
                    <ChatBubbleLeftRightIcon class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="eyebrow">Поддержка продукта</p>
                    <h1 class="mt-1 font-display text-[23px] font-medium tracking-[-0.035em] text-ink">Идеи и баги — отдельно от проектов</h1>
                    <p class="mt-1 max-w-[650px] text-[11px] leading-relaxed text-muted">Здесь живёт обратная связь по самой CRM. Проекты, задачи и сроки остаются в рабочем пространстве; продуктовые предложения и поломки не мешают им.</p>
                </div>
                <KikiAvatar variant="face" fit="contain" :size="42" />
            </div>
        </section>

        <SystemProjectCards :projects="systemProjects" :show-heading="false" />

        <p class="mt-3 px-2 text-center text-[9px] leading-relaxed text-faint">Голоса и статусы видны всем участникам CRM. Это не проектные задачи и не влияют на прогресс проектов.</p>
    </component>
</template>
