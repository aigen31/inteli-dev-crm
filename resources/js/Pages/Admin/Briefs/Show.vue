<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import BriefAnswers from '@/Components/Briefs/BriefAnswers.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import { formatDateTime } from '@/Utils/formatters.js';
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ArrowPathIcon,
    DocumentTextIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    brief: Object,
    sections: Array,
    progress: Number,
    revisions: Array,
    can: Object,
});

const form = useForm({
    title: props.brief.title,
    admin_note: props.brief.admin_note || '',
});

const filled = computed(() => {
    let answered = 0;
    let total = 0;

    for (const section of props.sections) {
        for (const row of section.rows) {
            total += 1;
            if (row.answered) answered += 1;
        }
    }

    return { answered, total };
});

function saveNote() {
    form.patch(route('admin.briefs.update', props.brief.id), { preserveScroll: true });
}

function archive() {
    router.post(route('admin.briefs.archive', props.brief.id), {}, { preserveScroll: true });
}

function reopen() {
    router.post(route('admin.briefs.reopen', props.brief.id), {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout :title="brief.title">
        <template #actions>
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="route('admin.briefs.index')">
                    <Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />К списку</Button>
                </Link>
                <a :href="route('admin.briefs.export', [brief.id, 'pdf'])">
                    <Button variant="secondary" size="sm"><ArrowDownTrayIcon class="h-4 w-4" />PDF</Button>
                </a>
                <a :href="route('admin.briefs.export', [brief.id, 'md'])">
                    <Button variant="secondary" size="sm">MD</Button>
                </a>
                <a :href="route('admin.briefs.export', [brief.id, 'json'])">
                    <Button variant="secondary" size="sm">JSON</Button>
                </a>
            </div>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.35fr_.65fr]">
            <div class="min-w-0 space-y-5">
                <section class="surface-card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="eyebrow">{{ brief.template?.title || 'Шаблон удалён' }}</p>
                            <p class="mt-2 text-[11px] text-muted">
                                <template v-if="brief.client">Клиент: <strong class="text-ink">{{ brief.client.name }}</strong> · </template>
                                <template v-if="brief.project">{{ brief.project.key }} · {{ brief.project.name }} · </template>
                                {{ brief.submitted_at ? `отправлен ${formatDateTime(brief.submitted_at)}` : `создан ${formatDateTime(brief.created_at)}` }}
                            </p>
                        </div>
                        <Badge :status="brief.status" type="brief">{{ brief.status_label }}</Badge>
                    </div>

                    <div class="mt-4 flex items-center gap-3">
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-2">
                            <div class="h-full rounded-full bg-primary-500 transition-[width] duration-300" :style="{ width: `${progress}%` }" />
                        </div>
                        <span class="font-mono text-[10px] text-muted">{{ progress }}%</span>
                        <span class="font-mono text-[10px] text-faint">{{ filled.answered }}/{{ filled.total }} полей</span>
                    </div>

                    <div v-if="brief.status === 'archived'" class="mt-4 rounded-[12px] bg-surface-2 px-3 py-2 text-[11px] text-muted">
                        Бриф в архиве — клиент не может его менять.
                    </div>
                </section>

                <BriefAnswers :sections="sections" hide-empty />
            </div>

            <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                <section class="surface-card p-5">
                    <p class="eyebrow">Заметка для команды</p>
                    <textarea
                        v-model="form.admin_note"
                        rows="5"
                        placeholder="Что важно учесть при разборе брифа"
                        class="mt-3 w-full resize-y rounded-[12px] border border-line bg-white/70 px-3 py-2.5 text-[12px] leading-relaxed text-ink placeholder:text-faint focus:border-primary-500/60 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                    />
                    <Button size="sm" class="mt-3 w-full" :disabled="form.processing" @click="saveNote">
                        <PencilSquareIcon class="h-4 w-4" />Сохранить заметку
                    </Button>
                </section>

                <section class="surface-card p-5">
                    <p class="eyebrow">Действия</p>
                    <div class="mt-3 space-y-2">
                        <Button
                            v-if="can.reopen"
                            variant="secondary"
                            class="w-full"
                            size="sm"
                            @click="reopen"
                        >
                            <ArrowPathIcon class="h-4 w-4" />Вернуть на доработку
                        </Button>
                        <Button
                            v-if="can.archive"
                            variant="ghost"
                            class="w-full"
                            size="sm"
                            @click="archive"
                        >
                            В архив
                        </Button>
                    </div>
                    <p class="mt-3 text-[10px] leading-relaxed text-faint">
                        После возврата клиент снова увидит бриф в кабинете и сможет править ответы.
                    </p>
                </section>

                <section v-if="revisions?.length" class="surface-card p-5">
                    <p class="eyebrow">История</p>
                    <ul class="mt-3 space-y-2">
                        <li v-for="revision in revisions" :key="revision.id" class="flex items-start gap-2 text-[11px]">
                            <DocumentTextIcon class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />
                            <span class="min-w-0">
                                <span class="font-semibold text-ink">v{{ revision.version }}</span>
                                <span class="text-muted"> · {{ revision.reason === 'reopened' ? 'возврат на доработку' : 'отправка' }}</span>
                                <span class="block text-[10px] text-faint">{{ formatDateTime(revision.created_at) }}<template v-if="revision.author"> · {{ revision.author }}</template></span>
                            </span>
                        </li>
                    </ul>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
