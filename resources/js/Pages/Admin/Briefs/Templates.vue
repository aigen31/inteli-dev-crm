<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Badge from '@/Components/UI/Badge.vue';
import Button from '@/Components/UI/Button.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import Modal from '@/Components/UI/Modal.vue';
import { formatDate } from '@/Utils/formatters.js';
import { ArrowLeftIcon, ClipboardDocumentListIcon, SparklesIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const pending = ref(null);
const deleting = ref(false);
const error = ref(null);

function askDelete(template) {
    error.value = null;
    pending.value = template;
}

function confirmDelete() {
    if (!pending.value) return;

    deleting.value = true;

    router.delete(route('admin.briefs.templates.destroy', pending.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            pending.value = null;
        },
        onError: (bag) => {
            error.value = bag.error || Object.values(bag)[0] || 'Не удалось удалить шаблон.';
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout title="Шаблоны брифов">
        <template #actions>
            <Link :href="route('admin.briefs.index')">
                <Button variant="secondary" size="sm"><ArrowLeftIcon class="h-4 w-4" />К брифам</Button>
            </Link>
        </template>

        <div class="mb-5">
            <p class="eyebrow">Библиотека схем</p>
            <p class="mt-2 max-w-[720px] text-[12px] leading-relaxed text-muted">
                Шаблон задаёт секции и поля брифа. Создание и правку схемы делает Кики — так правила не расходятся с движком.
                Здесь можно удалить неиспользуемый шаблон.
            </p>
        </div>

        <div class="mb-5 grid grid-cols-3 gap-3">
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Всего</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-ink">{{ stats.total }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Активных</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-ink">{{ stats.active }}</p>
            </div>
            <div class="surface-card p-4">
                <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-faint">Используются</p>
                <p class="mt-1 text-[22px] font-medium tracking-[-0.03em] text-ink">{{ stats.used }}</p>
            </div>
        </div>

        <div v-if="!templates.length" class="surface-card">
            <EmptyState
                image="/images/kiki/kiki-forms-avatar.webp"
                title="Шаблонов нет"
                text="Попросите Кики собрать шаблон: «сделай шаблон брифа для аудита рекламы»."
            >
                <Link :href="route('assistant.index')">
                    <Button size="sm"><SparklesIcon class="h-4 w-4" />Открыть Кики</Button>
                </Link>
            </EmptyState>
        </div>

        <div v-else class="space-y-2.5">
            <div
                v-for="template in templates"
                :key="template.id"
                class="surface-card flex flex-wrap items-center gap-4 p-4 sm:p-5"
            >
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[12px] bg-surface-2 text-primary-700">
                    <ClipboardDocumentListIcon class="h-5 w-5" />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-[13px] font-bold text-ink">{{ template.title }}</span>
                        <span class="rounded-full bg-surface-2 px-2 py-0.5 font-mono text-[9px] text-muted">{{ template.slug }}</span>
                        <span v-if="!template.is_active" class="font-mono text-[9px] uppercase tracking-[0.12em] text-faint">выключен</span>
                    </span>
                    <span class="mt-1 block text-[11px] leading-relaxed text-muted">{{ template.summary }}</span>
                    <span class="mt-1 block font-mono text-[9px] text-faint">
                        {{ template.section_count }} секций · {{ template.field_count }} полей · v{{ template.version }}
                        · брифов: {{ template.briefs_count }}
                        · изменён {{ formatDate(template.updated_at) }}
                    </span>
                </span>

                <Badge :status="template.briefs_count > 0 ? 'submitted' : 'draft'" type="brief">
                    {{ template.briefs_count > 0 ? 'Используется' : 'Свободен' }}
                </Badge>

                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="template.briefs_count > 0"
                    :title="template.briefs_count > 0 ? 'Сначала удалите или архивируйте брифы' : 'Удалить шаблон'"
                    @click="askDelete(template)"
                >
                    <TrashIcon class="h-4 w-4" />Удалить
                </Button>
            </div>
        </div>

        <Modal :show="pending !== null" max-width="md" @close="pending = null">
            <div class="p-6">
                <p class="eyebrow">Удаление шаблона</p>
                <h3 class="mt-1 text-[18px] font-medium tracking-[-0.02em] text-ink">Удалить «{{ pending?.title }}»?</h3>
                <p class="mt-2 text-[12px] leading-relaxed text-muted">
                    Шаблон исчезнет из выбора при создании брифа. Уже созданные брифы не затрагиваются — они хранят снимок схемы,
                    поэтому удаление доступно только для шаблонов без брифов.
                </p>
                <p v-if="pending?.briefs_count" class="mt-3 rounded-[12px] bg-warning/10 px-3 py-2 text-[11px] text-warning">
                    По шаблону уже создано брифов: {{ pending.briefs_count }}. Удаление заблокировано.
                </p>
                <p v-if="error" class="mt-3 text-[11px] text-danger">{{ error }}</p>

                <div class="mt-5 flex justify-end gap-2">
                    <Button variant="ghost" size="sm" @click="pending = null">Отмена</Button>
                    <Button
                        variant="danger"
                        size="sm"
                        :disabled="deleting || (pending?.briefs_count || 0) > 0"
                        @click="confirmDelete"
                    >
                        {{ deleting ? 'Удаляю…' : 'Удалить' }}
                    </Button>
                </div>
            </div>
        </Modal>
    </AdminLayout>
</template>
