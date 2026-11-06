<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import SystemProjectCards from '@/Components/Projects/SystemProjectCards.vue';
import { FolderIcon } from '@heroicons/vue/24/outline';

defineProps({
    projects: Array,
    systemProjects: { type: Array, default: () => [] },
});

const colorBar = {
    violet: 'bg-primary-500',
    sage: 'bg-sage',
    sky: 'bg-sky',
    peach: 'bg-peach',
    ink: 'bg-ink',
};
</script>

<template>
    <ClientLayout title="Мои проекты">
        <p class="max-w-[560px] text-[12px] leading-relaxed text-muted">
            Здесь собраны проекты, к которым у вас есть доступ. Внутри — ваши задачи, сроки и прогресс.
        </p>

        <SystemProjectCards :projects="systemProjects" class="mt-5" />

        <div v-if="projects.length" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="project in projects"
                :key="project.id"
                :href="route('client.projects.show', project.id)"
                class="group relative overflow-hidden rounded-[22px] border border-line bg-surface p-5 shadow-[0_14px_36px_rgba(28,24,35,0.05)] transition hover:-translate-y-0.5 hover:shadow-[0_20px_45px_rgba(28,24,35,0.09)]"
            >
                <div :class="['absolute inset-x-0 top-0 h-[3px]', colorBar[project.color] || colorBar.violet]"></div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full border border-line bg-white/70 px-2 py-0.5 font-mono text-[9px] tracking-[0.08em] text-muted">{{ project.key }}</span>
                    <span class="rounded-full bg-primary-soft px-2 py-0.5 text-[9px] font-semibold text-primary-800">{{ project.status_label }}</span>
                    <span v-if="project.access" class="ml-auto text-[9px] text-faint">{{ project.access }}</span>
                </div>
                <h2 class="mt-3 text-[17px] font-semibold leading-snug text-ink group-hover:text-primary-800">{{ project.name }}</h2>
                <p v-if="project.description" class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ project.description }}</p>

                <div class="mt-4">
                    <div class="flex items-center justify-between font-mono text-[9px] text-faint">
                        <span>{{ project.completed }} / {{ project.total }} задач</span>
                        <span>{{ project.percent }}%</span>
                    </div>
                    <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-ink/[0.06]">
                        <div class="h-full rounded-full bg-primary-500" :style="{ width: project.percent + '%' }"></div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2 text-[10px] text-muted">
                    <FolderIcon class="h-3.5 w-3.5 text-faint" />
                    <span v-if="project.due_at">до {{ project.due_at }}</span>
                    <span v-else>срок не задан</span>
                </div>
            </Link>
        </div>

        <EmptyState
            v-else
            variant="projects"
            class="mt-6"
            title="Проектов пока нет"
            text="Когда владелец добавит вас в проект, он появится здесь вместе с вашими задачами."
        />
    </ClientLayout>
</template>
