<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import BriefAnswers from '@/Components/Briefs/BriefAnswers.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import Modal from '@/Components/UI/Modal.vue';
import { formatDateTime } from '@/Utils/formatters.js';
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ArrowPathIcon,
    ClipboardDocumentIcon,
    DocumentTextIcon,
    LinkIcon,
    PencilSquareIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    brief: Object,
    sections: Array,
    progress: Number,
    revisions: Array,
    links: { type: Array, default: () => [] },
    can: Object,
});

const linkOpen = ref(false);
const linkDays = ref(7);
const creatingLink = ref(false);
const createdUrl = ref(null);
const linkError = ref(null);
const copied = ref(false);

async function createLink() {
    creatingLink.value = true;
    linkError.value = null;
    copied.value = false;

    try {
        const { data } = await window.axios.post(route('admin.briefs.links.store', props.brief.id), { days: linkDays.value });
        createdUrl.value = data.url;
    } catch (error) {
        linkError.value = error?.response?.data?.message || 'Не удалось создать ссылку.';
    } finally {
        creatingLink.value = false;
    }
}

async function copyLink() {
    if (! createdUrl.value) return;

    try {
        await navigator.clipboard.writeText(createdUrl.value);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}

function revokeLink(id) {
    router.delete(route('admin.briefs.links.destroy', [props.brief.id, id]), { preserveScroll: true });
}

function closeLinkModal() {
    linkOpen.value = false;
    createdUrl.value = null;
    linkError.value = null;
    copied.value = false;
}

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

const titleDirty = computed(() => form.title.trim() !== props.brief.title && form.title.trim().length > 0);

function saveTitle() {
    if (! titleDirty.value) return;

    form.patch(route('admin.briefs.update', props.brief.id), {
        preserveScroll: true,
        only: ['brief'],
    });
}

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
                            <div class="mt-1 flex items-center gap-2">
                                <input
                                    v-model="form.title"
                                    type="text"
                                    maxlength="200"
                                    aria-label="Заголовок брифа"
                                    class="min-w-0 flex-1 rounded-[10px] border border-transparent bg-transparent px-2 py-1 text-[18px] font-medium tracking-[-0.02em] text-ink transition hover:border-line focus:border-primary-500/60 focus:bg-white/70 focus:outline-none focus:ring-2 focus:ring-primary-500/20"
                                    @keyup.enter="saveTitle"
                                />
                                <Button v-if="titleDirty" size="sm" variant="secondary" :disabled="form.processing" @click="saveTitle">
                                    Сохранить
                                </Button>
                            </div>
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

                <section v-if="brief.status !== 'archived'" class="surface-card p-5">
                    <p class="eyebrow">Ссылка для заполнения</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">
                        Человек без аккаунта заполнит бриф по ссылке. Ссылка одноразовая, живёт до выбранного срока.
                    </p>

                    <div v-if="links.length" class="mt-3 space-y-2">
                        <div v-for="link in links" :key="link.id" class="flex items-center justify-between gap-2 rounded-[10px] bg-surface-2 px-3 py-2">
                            <span class="min-w-0 font-mono text-[9px] leading-relaxed text-muted">
                                <template v-if="link.is_active">до {{ formatDateTime(link.expires_at) }}</template>
                                <template v-else-if="link.used_at">использована</template>
                                <template v-else>отозвана</template>
                                <template v-if="link.last_used_at"><span class="block text-faint">открыта {{ formatDateTime(link.last_used_at) }}</span></template>
                            </span>
                            <button
                                v-if="link.is_active"
                                type="button"
                                class="shrink-0 font-mono text-[9px] uppercase tracking-[0.12em] text-danger hover:underline"
                                @click="revokeLink(link.id)"
                            >Отозвать</button>
                        </div>
                    </div>

                    <Button size="sm" class="mt-3 w-full" :disabled="brief.status === 'submitted'" @click="linkOpen = true">
                        <LinkIcon class="h-4 w-4" />Выпустить ссылку
                    </Button>
                    <p v-if="brief.status === 'submitted'" class="mt-2 text-[10px] text-faint">Бриф отправлен — новая ссылка не нужна.</p>
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

        <Modal :show="linkOpen" max-width="md" @close="closeLinkModal">
            <div class="p-6">
                <p class="eyebrow">Публичная ссылка</p>
                <h3 class="mt-1 text-[18px] font-medium tracking-[-0.02em] text-ink">Заполнение без аккаунта</h3>
                <p class="mt-2 text-[12px] leading-relaxed text-muted">
                    Ссылка открывает только этот бриф. Она перестанет работать после отправки или по истечении срока.
                </p>

                <label class="mt-4 block">
                    <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Срок действия</span>
                    <select v-model.number="linkDays" class="mt-1 w-full rounded-[12px] border border-line bg-white/70 px-3 py-2 text-[12px] text-ink focus:border-primary-500/60 focus:outline-none">
                        <option :value="1">1 день</option>
                        <option :value="3">3 дня</option>
                        <option :value="7">7 дней</option>
                        <option :value="14">14 дней</option>
                        <option :value="30">30 дней</option>
                    </select>
                </label>

                <div v-if="createdUrl" class="mt-4">
                    <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Ссылка (показывается один раз)</p>
                    <div class="mt-1 flex items-center gap-2 rounded-[12px] bg-surface-2 px-3 py-2">
                        <input :value="createdUrl" readonly class="min-w-0 flex-1 bg-transparent font-mono text-[10px] text-ink focus:outline-none" @focus="$event.target.select()" />
                        <button type="button" class="shrink-0 text-muted hover:text-ink" :title="copied ? 'Скопировано' : 'Скопировать'" @click="copyLink">
                            <ClipboardDocumentIcon class="h-4 w-4" />
                        </button>
                    </div>
                    <p class="mt-1 text-[10px] text-faint">Скопируйте сейчас: в базе хранится только хэш токена.</p>
                </div>

                <p v-if="linkError" class="mt-3 text-[11px] text-danger">{{ linkError }}</p>

                <div class="mt-5 flex justify-end gap-2">
                    <Button variant="ghost" size="sm" @click="closeLinkModal">Закрыть</Button>
                    <Button size="sm" :disabled="creatingLink" @click="createLink">
                        {{ creatingLink ? 'Создаю…' : (createdUrl ? 'Ещё одну' : 'Создать ссылку') }}
                    </Button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
