<script setup>
import { Link } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import { formatDate } from '@/Utils/formatters.js';
import { ArrowRightIcon, ClipboardDocumentListIcon, ClockIcon, PlusIcon } from '@heroicons/vue/24/outline';

defineProps({
    briefs: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});
</script>

<template>
    <ClientLayout title="Брифы">
        <template #actions>
            <Link :href="route('client.briefs.create')">
                <Button size="sm"><PlusIcon class="h-4 w-4" />Создать бриф</Button>
            </Link>
        </template>
        <div class="mb-5">
            <p class="eyebrow">Вводные по проекту</p>
            <p class="mt-2 max-w-[640px] text-[12px] leading-relaxed text-muted">
                Заполните бриф — это экономит раунды уточнений. Ответы сохраняются по ходу, отправить можно в любой момент после заполнения.
            </p>
        </div>

        <div v-if="!briefs.length" class="surface-card">
            <EmptyState
                image="/images/kiki/kiki-forms-avatar.webp"
                title="Брифов пока нет"
                text="Как только понадобятся вводные по проекту, бриф появится здесь."
            />
        </div>

        <div v-else class="grid grid-cols-1 gap-3 lg:grid-cols-2">
            <Link
                v-for="brief in briefs"
                :key="brief.id"
                :href="route('client.briefs.show', brief.id)"
                class="surface-card surface-card-hover flex flex-col gap-3 p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ brief.template?.title || 'Бриф' }}</p>
                        <h3 class="mt-1 truncate text-[15px] font-bold tracking-[-0.01em] text-ink">{{ brief.title }}</h3>
                    </div>
                    <Badge :status="brief.status" type="brief">{{ brief.status_label }}</Badge>
                </div>

                <p v-if="brief.template?.summary" class="line-clamp-2 text-[11px] leading-relaxed text-muted">{{ brief.template.summary }}</p>

                <div class="mt-auto">
                    <div class="flex items-center gap-2">
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-primary-500 transition-[width] duration-300" :style="{ width: `${brief.progress}%` }" />
                        </div>
                        <span class="font-mono text-[9px] text-muted">{{ brief.progress }}%</span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-[10px] text-faint">
                        <span class="inline-flex items-center gap-1"><ClipboardDocumentListIcon class="h-3.5 w-3.5" />{{ brief.section_count }} секций</span>
                        <span v-if="brief.estimated_minutes" class="inline-flex items-center gap-1"><ClockIcon class="h-3.5 w-3.5" />~{{ brief.estimated_minutes }} мин</span>
                        <span v-if="brief.submitted_at">отправлен {{ formatDate(brief.submitted_at) }}</span>
                        <span v-else-if="brief.is_editable" class="text-primary-700">продолжить заполнение</span>
                    </div>
                </div>

                <span class="inline-flex items-center gap-1.5 self-start text-[11px] font-semibold text-primary-700">
                    {{ brief.is_editable ? 'Продолжить' : 'Посмотреть ответы' }}
                    <ArrowRightIcon class="h-3.5 w-3.5" />
                </span>
            </Link>
        </div>
    </ClientLayout>
</template>
