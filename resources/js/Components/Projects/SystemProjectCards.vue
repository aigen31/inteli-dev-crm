<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowUpRightIcon, BugAntIcon, LightBulbIcon } from '@heroicons/vue/24/outline';

defineProps({
    projects: { type: Array, default: () => [] },
});

const tones = {
    violet: {
        shell: 'from-primary-50/90 via-white to-white',
        accent: 'bg-primary-500',
        icon: 'bg-primary-soft text-primary-700',
    },
    peach: {
        shell: 'from-warning/[0.09] via-white to-white',
        accent: 'bg-warning',
        icon: 'bg-warning/10 text-warning',
    },
};

function tone(project) {
    return tones[project.color] || tones.violet;
}
</script>

<template>
    <section v-if="projects.length" class="mb-6">
        <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
            <div>
                <p class="eyebrow">Сервисные проекты</p>
                <h2 class="mt-1 text-[15px] font-bold text-ink">CRM развивается вместе с вами</h2>
            </div>
            <span class="rounded-full border border-line bg-white/60 px-2.5 py-1 font-mono text-[8px] uppercase tracking-[0.12em] text-muted">
                доступно всем
            </span>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            <Link
                v-for="project in projects"
                :key="project.key"
                :href="project.url"
                :class="[
                    'group relative overflow-hidden rounded-[24px] border border-line bg-gradient-to-br p-5 shadow-[0_14px_34px_rgba(28,24,35,0.045)] transition duration-200 hover:-translate-y-0.5 hover:shadow-[0_20px_42px_rgba(28,24,35,0.08)]',
                    tone(project).shell,
                ]"
            >
                <span :class="['absolute inset-x-0 top-0 h-[3px]', tone(project).accent]"></span>
                <div class="flex items-start gap-3">
                    <span :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-[13px]', tone(project).icon]">
                        <BugAntIcon v-if="project.icon === 'bug'" class="h-5 w-5" />
                        <LightBulbIcon v-else class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full border border-line bg-white/70 px-2 py-0.5 font-mono text-[8px] tracking-[0.08em] text-muted">{{ project.code }}</span>
                            <span class="font-mono text-[8px] uppercase tracking-[0.12em] text-faint">{{ project.item_label }} · для всех</span>
                        </div>
                        <h3 class="mt-2 text-[16px] font-semibold leading-snug text-ink group-hover:text-primary-800">{{ project.name }}</h3>
                        <p class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ project.description }}</p>
                    </div>
                    <ArrowUpRightIcon class="h-4 w-4 shrink-0 text-primary-600 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line/70 pt-3 font-mono text-[9px] text-faint">
                    <span class="rounded-full bg-white/70 px-2 py-1">{{ project.open }} открытых</span>
                    <span class="rounded-full bg-white/70 px-2 py-1">{{ project.done }} закрытых</span>
                    <span class="ml-auto">{{ project.total }} всего</span>
                </div>
            </Link>
        </div>
    </section>
</template>
