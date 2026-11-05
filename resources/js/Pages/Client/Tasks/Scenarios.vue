<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import EmptyState from '@/Components/UI/EmptyState.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import { ArrowLeftIcon, CheckIcon, PlusIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    templates: { type: Array, default: () => [] },
});

const selected = ref(null);
const form = useForm({ title: '', description: '', deadline: '', consent: false });

function choose(template) {
    selected.value = template;
    form.title = template.name;
    form.description = '';
    form.deadline = '';
    form.clearErrors();
}

function submit() {
    form.post(route('client.tasks.from-template', selected.value.id));
}
</script>

<template>
    <ClientLayout title="Сценарии">
        <template #actions>
            <Link :href="route('client.tasks.create')" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-muted transition hover:text-ink">
                <ArrowLeftIcon class="h-3.5 w-3.5" />
                К формам
            </Link>
        </template>

        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="eyebrow">Классические шаблоны</p>
                <p class="mt-2 max-w-[620px] text-[12px] leading-relaxed text-muted">
                    Готовое описание и чек-лист. Выберите сценарий и добавьте детали — структура уже внутри.
                </p>
            </div>
            <div class="flex rounded-full border border-line bg-white/50 p-1">
                <Link :href="route('client.tasks.create')" class="rounded-full px-3 py-1.5 text-[11px] font-semibold text-muted transition hover:text-ink">Формы заявок</Link>
                <span class="rounded-full bg-ink px-3 py-1.5 text-[11px] font-semibold text-canvas">Сценарии</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.25fr_.75fr]">
            <div class="min-w-0">
                <div v-if="templates.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        :class="[
                            'surface-card group p-5 text-left transition',
                            selected?.id === template.id ? 'border-primary-300 ring-2 ring-primary-200/70' : 'surface-card-hover',
                        ]"
                        @click="choose(template)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="rounded-full bg-primary-soft px-2.5 py-1 text-[9px] font-semibold text-primary-800">{{ template.default_due_days ? `${template.default_due_days} дн.` : 'срок обсуждается' }}</span>
                            <CheckIcon :class="['h-4 w-4 text-primary-500 transition', selected?.id === template.id ? 'opacity-100' : 'opacity-0 group-hover:opacity-100']" />
                        </div>
                        <h3 class="mt-3 text-[14px] font-bold text-ink">{{ template.name }}</h3>
                        <p class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-muted">{{ template.summary || 'Готовый сценарий заявки' }}</p>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <span v-for="item in (template.checklist || []).slice(0, 3)" :key="item.text" class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-ink-soft">{{ item.text }}</span>
                            <span v-if="(template.checklist || []).length > 3" class="rounded-full bg-surface-2 px-2 py-0.5 text-[9px] text-muted">+{{ template.checklist.length - 3 }}</span>
                        </div>
                    </button>
                </div>
                <div v-else class="surface-card">
                    <EmptyState variant="flight" title="Сценариев пока нет" text="Пока доступны только формы заявок. Начните с них — это быстрее.">
                        <Link :href="route('client.tasks.create')"><Button size="sm">К формам</Button></Link>
                    </EmptyState>
                </div>
            </div>

            <aside class="lg:sticky lg:top-6 lg:self-start">
                <section v-if="selected" class="surface-card p-5">
                    <p class="eyebrow">Сценарий</p>
                    <h2 class="mt-1 text-[17px] font-bold text-ink">{{ selected.name }}</h2>
                    <p class="mt-1 text-[10px] leading-relaxed text-muted">{{ selected.summary }}</p>

                    <div v-if="selected.checklist?.length" class="mt-4 rounded-[16px] border border-line/70 bg-surface-2/30 p-3">
                        <p class="text-[9px] font-semibold uppercase tracking-[0.08em] text-muted">Чек-лист внутри</p>
                        <ul class="mt-2 space-y-1.5">
                            <li v-for="item in selected.checklist" :key="item.text" class="flex items-start gap-2 text-[10px] leading-relaxed text-ink-soft">
                                <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary-400"></span>{{ item.text }}
                            </li>
                        </ul>
                    </div>

                    <div class="mt-4 space-y-3">
                        <TextInput v-model="form.title" label="Название заявки" :error="form.errors.title" />
                        <div>
                            <p class="mb-1.5 text-[11px] font-semibold text-ink-soft">Что уточнить</p>
                            <TextArea v-model="form.description" :rows="4" placeholder="Детали, ссылки, пожелания…" :error="form.errors.description" />
                        </div>
                        <TextInput v-model="form.deadline" label="Желаемый срок" type="date" :error="form.errors.deadline" />
                    </div>
                    <div class="mt-4">
                        <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="scenario-consent" />
                    </div>
                    <Button class="mt-4 w-full" size="sm" :disabled="form.processing || !form.consent" @click="submit">
                        <PlusIcon class="h-4 w-4" />
                        Создать заявку
                    </Button>
                </section>
                <section v-else class="surface-card p-5 text-center">
                    <p class="text-[12px] font-semibold text-ink">Выберите сценарий</p>
                    <p class="mt-1 text-[10px] text-muted">Справа появятся поля для уточнения.</p>
                </section>
            </aside>
        </div>
    </ClientLayout>
</template>
