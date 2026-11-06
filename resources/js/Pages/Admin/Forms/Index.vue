<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import FormTemplateCard from '@/Components/FormTemplates/FormTemplateCard.vue';
import Button from '@/Components/UI/Button.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { ArrowRightIcon, PlusIcon, RectangleStackIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});

const selectedKey = ref(props.templates[0]?.key || null);
const selected = computed(() => props.templates.find((template) => template.key === selectedKey.value) || null);

function toggle(template) {
    router.patch(route('admin.forms.toggle', template.key), {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Формы заявок">
        <template #actions>
            <Link :href="route('admin.task-templates.index')">
                <Button variant="secondary" size="sm">
                    <RectangleStackIcon class="h-4 w-4" />
                    Сценарии
                </Button>
            </Link>
        </template>

        <div class="mb-5">
            <p class="eyebrow">Библиотека</p>
            <p class="mt-2 max-w-[720px] text-[12px] leading-relaxed text-muted">
                Готовые наборы полей под конкретные ситуации. Клиент не пишет с нуля — он уточняет и структурирует задачу.
            </p>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <div class="flex rounded-full border border-line bg-white/50 p-1">
                <span class="rounded-full bg-ink px-3 py-1.5 text-[11px] font-semibold text-canvas">Формы</span>
                <Link :href="route('admin.task-templates.index')" class="rounded-full px-3 py-1.5 text-[11px] font-semibold text-muted transition hover:text-ink">Сценарии</Link>
            </div>
            <div class="ml-auto flex gap-4 font-mono text-[9px] text-muted">
                <span>{{ stats.active }} активных</span>
                <span>{{ stats.fields }} полей</span>
                <span>{{ stats.usage }} отправок</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.25fr_.75fr]">
            <div class="min-w-0">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <FormTemplateCard
                        v-for="template in templates"
                        :key="template.key"
                        :template="template"
                        :selected="selectedKey === template.key"
                        @select="selectedKey = template.key"
                    />
                </div>

                <Link :href="route('admin.task-templates.index')" class="mt-3 flex items-center gap-3 rounded-[20px] border border-line bg-white/35 px-4 py-3 text-left transition hover:border-line-strong">
                    <span class="flex h-9 w-9 items-center justify-center rounded-[12px] bg-surface-2 text-muted"><RectangleStackIcon class="h-4 w-4" /></span>
                    <span class="min-w-0">
                        <span class="block text-[12px] font-semibold text-ink">Классические сценарии</span>
                        <span class="block text-[10px] text-muted">Текст + чек-лист. Живут в отдельном разделе.</span>
                    </span>
                    <ArrowRightIcon class="ml-auto h-4 w-4 text-faint" />
                </Link>
            </div>

            <aside v-if="selected" class="lg:sticky lg:top-6 lg:self-start">
                <section class="surface-card overflow-hidden">
                    <div class="border-b border-line/80 bg-surface-2/40 px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="eyebrow">Структура формы</p>
                                <h2 class="mt-1 text-[17px] font-bold text-ink">{{ selected.name }}</h2>
                                <p class="mt-1 text-[10px] leading-relaxed text-muted">{{ selected.description }}</p>
                            </div>
                            <KikiAvatar variant="peek" :size="34" />
                        </div>
                    </div>

                    <div class="space-y-4 p-5">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-ink-soft">{{ selected.field_count }} полей</span>
                            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-ink-soft">{{ selected.required_count }} обязательных</span>
                            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-ink-soft">≈ {{ selected.estimated_minutes }} мин</span>
                        </div>

                        <div class="space-y-2">
                            <div v-for="field in selected.schema.fields" :key="field.key" class="flex items-center gap-2 rounded-[12px] border border-line/70 bg-white/50 px-3 py-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-primary-400"></span>
                                <span class="text-[11px] text-ink-soft">{{ field.label }}</span>
                                <span v-if="field.required" class="text-[11px] text-primary-600">*</span>
                                <span class="ml-auto font-mono text-[8px] uppercase tracking-[0.08em] text-faint">{{ field.type }}</span>
                            </div>
                        </div>

                        <div v-for="group in selected.schema.groups" :key="group.key" class="rounded-[14px] border border-dashed border-line px-3 py-2.5">
                            <div class="flex items-center gap-2">
                                <p class="text-[10px] font-semibold text-ink">{{ group.label }}</p>
                                <span class="rounded-full bg-primary-soft px-1.5 py-0.5 font-mono text-[8px] text-primary-700">repeat</span>
                            </div>
                            <p class="mt-1 text-[9px] leading-relaxed text-muted">{{ (group.fields || []).map((field) => field.label).join(' · ') }}</p>
                        </div>

                        <div class="flex items-center justify-between border-t border-line/70 pt-4">
                            <button type="button" class="text-[10px] font-semibold text-muted transition hover:text-ink" @click="toggle(selected)">
                                {{ selected.is_active ? 'Выключить форму' : 'Включить форму' }}
                            </button>
                            <span :class="['rounded-full px-2 py-0.5 text-[9px] font-semibold', selected.is_active ? 'bg-sage/20 text-[#397254]' : 'bg-ink/[0.06] text-muted']">
                                {{ selected.is_active ? 'активна' : 'выключена' }}
                            </span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
