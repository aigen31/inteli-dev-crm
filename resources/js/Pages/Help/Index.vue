<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import { useAssistant } from '@/Composables/useAssistant';
import { ArrowTopRightOnSquareIcon, MagnifyingGlassIcon, SparklesIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    sections: { type: Array, default: () => [] },
    version: String,
});

const page = usePage();
const { open } = useAssistant();
const query = ref('');
const isAdmin = computed(() => page.props.auth?.user?.role === 'admin');
const layout = computed(() => (isAdmin.value ? AdminLayout : ClientLayout));

const normalized = computed(() => query.value.trim().toLowerCase());

const filtered = computed(() => {
    if (!normalized.value) return props.sections;

    return props.sections
        .map((section) => ({
            ...section,
            entries: section.entries.filter((entry) =>
                [entry.title, entry.summary, entry.section, ...(entry.keywords || []), ...(entry.steps || [])]
                    .join(' ')
                    .toLowerCase()
                    .includes(normalized.value),
            ),
        }))
        .filter((section) => section.entries.length > 0);
});
</script>

<template>
    <component :is="layout" title="Помощь">
        <div class="mx-auto max-w-4xl">
            <section class="surface-card overflow-hidden p-6 sm:p-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-[560px]">
                        <p class="eyebrow">Справочник CRM</p>
                        <h1 class="mt-2 font-display text-[28px] leading-[1.05] tracking-[-0.04em] text-ink sm:text-[34px]">
                            Каждый раздел — с шагами и ссылкой
                        </h1>
                        <p class="mt-3 text-[12px] leading-relaxed text-muted">
                            Найдите нужное сами или спросите Кики: она знает эту карту, объяснит и откроет раздел.
                        </p>
                    </div>
                    <Button @click="open">
                        <SparklesIcon class="h-4 w-4" />
                        Спросить Кики
                    </Button>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <MagnifyingGlassIcon class="h-4 w-4 text-muted" />
                    <TextInput v-model="query" placeholder="Найти раздел: счета, приглашение, принять работу…" class="flex-1" />
                </div>
            </section>

            <div v-if="!filtered.length" class="mt-6 surface-card p-8 text-center">
                <p class="text-[13px] font-semibold text-ink">Ничего не нашлось</p>
                <p class="mt-1 text-[11px] text-muted">Сформулируйте иначе или спросите Кики — она подскажет раздел.</p>
                <Button class="mt-4" size="sm" @click="open">Спросить Кики</Button>
            </div>

            <div v-else class="mt-6 space-y-5">
                <section v-for="section in filtered" :key="section.title" class="space-y-2">
                    <div class="flex items-center gap-2 px-1">
                        <p class="eyebrow">{{ section.title }}</p>
                        <span class="font-mono text-[9px] text-faint">{{ section.entries.length }}</span>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <article
                            v-for="entry in section.entries"
                            :id="entry.id"
                            :key="entry.id"
                            class="surface-card flex flex-col p-4"
                        >
                            <h2 class="text-[13px] font-bold text-ink">{{ entry.title }}</h2>
                            <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ entry.summary }}</p>

                            <ol class="mt-3 space-y-1.5">
                                <li v-for="(step, index) in entry.steps" :key="index" class="flex gap-2 text-[10.5px] leading-relaxed text-ink-soft">
                                    <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-primary-soft font-mono text-[8px] font-bold text-primary-800">{{ index + 1 }}</span>
                                    <span>{{ step }}</span>
                                </li>
                            </ol>

                            <Link :href="entry.path" class="mt-4 inline-flex items-center gap-1 text-[10px] font-semibold text-primary-700 hover:underline">
                                Открыть раздел
                                <ArrowTopRightOnSquareIcon class="h-3 w-3" />
                            </Link>
                        </article>
                    </div>
                </section>
            </div>

            <section class="mt-6 surface-card flex flex-wrap items-center justify-between gap-3 p-5">
                <div>
                    <p class="text-[12px] font-bold text-ink">Не нашли ответ?</p>
                    <p class="mt-1 text-[11px] text-muted">
                        {{ isAdmin ? 'Спросите Кики — она разберёт раздел по шагам.' : 'Создайте заявку — владелец увидит её и ответит.' }}
                    </p>
                </div>
                <Link v-if="!isAdmin" :href="route('client.tasks.create')">
                    <Button size="sm">Создать заявку</Button>
                </Link>
                <Button v-else size="sm" @click="open">Спросить Кики</Button>
            </section>

            <p class="mt-8 px-2 text-center text-[9px] text-faint">
                Справочник обновляется вместе с CRM · версия {{ version }}
            </p>
        </div>
    </component>
</template>
