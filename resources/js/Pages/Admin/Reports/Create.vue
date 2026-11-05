<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextArea from '@/Components/Form/TextArea.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import SelectInput from '@/Components/Form/SelectInput.vue';
import { ArrowLeftIcon, PlusIcon, PaperClipIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    tasks: Array,
    recipients: Array,
    preselectedTask: String,
});

const form = useForm({
    title: '',
    task_id: props.preselectedTask || '',
    recipient_id: '',
    items: [{ summary: '', test_url: '', screenshot: null }],
});

const taskOptions = computed(() => props.tasks.map((task) => ({
    value: task.id,
    label: (task.reference ? task.reference + ' · ' : '') + task.title,
})));

const recipientOptions = computed(() => props.recipients.map((user) => ({
    value: user.id,
    label: user.name + ' · ' + user.email,
})));

function addItem() {
    form.items.push({ summary: '', test_url: '', screenshot: null });
}

function removeItem(index) {
    if (form.items.length === 1) return;
    form.items.splice(index, 1);
}

function setScreenshot(index, event) {
    form.items[index].screenshot = event.target.files?.[0] || null;
}

function submit() {
    form.post(route('admin.reports.store'), { forceFormData: true });
}
</script>

<template>
    <AdminLayout title="Новый отчёт">
        <template #actions>
            <Link :href="route('admin.reports.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Назад</Button></Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Отправить</Button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.3fr_.7fr]">
            <section class="space-y-4">
                <div class="surface-card p-5 sm:p-6">
                    <p class="eyebrow">Отчёт</p>
                    <div class="mt-4">
                        <TextInput
                            v-model="form.title"
                            label="Заголовок (необязательно)"
                            placeholder="Например: Исправил форму заявки на главной"
                            :error="form.errors.title"
                        />
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <SelectInput v-model="form.task_id" label="Задача (необязательно)" :options="taskOptions" placeholder="Отчёт без задачи" :error="form.errors.task_id" />
                        <SelectInput v-model="form.recipient_id" label="Получатель" :options="recipientOptions" placeholder="Кому отправить" :error="form.errors.recipient_id" />
                    </div>
                </div>

                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="surface-card p-5 sm:p-6"
                >
                    <div class="flex items-center gap-2">
                        <p class="eyebrow">Группа {{ index + 1 }}</p>
                        <button
                            v-if="form.items.length > 1"
                            type="button"
                            class="ml-auto inline-flex items-center gap-1 text-[10px] text-faint hover:text-danger-ink"
                            @click="removeItem(index)"
                        >
                            <TrashIcon class="h-3.5 w-3.5" />убрать
                        </button>
                    </div>

                    <div class="mt-3">
                        <TextArea
                            v-model="item.summary"
                            label="Краткое объяснение"
                            :rows="4"
                            placeholder="Что сделано, как проверить, на что обратить внимание…"
                            :error="form.errors[`items.${index}.summary`]"
                        />
                    </div>

                    <div class="mt-3">
                        <TextInput
                            v-model="item.test_url"
                            label="Ссылка для тестирования (необязательно)"
                            placeholder="https://example.com/page"
                            :error="form.errors[`items.${index}.test_url`]"
                        />
                    </div>

                    <div class="mt-3">
                        <label class="flex cursor-pointer items-center gap-2 rounded-[14px] border border-dashed border-line-strong bg-white/50 px-4 py-3 text-[11px] text-muted hover:border-primary-300">
                            <PaperClipIcon class="h-4 w-4" />
                            {{ item.screenshot ? item.screenshot.name : 'Прикрепить скриншот (необязательно)' }}
                            <input type="file" accept="image/*" class="hidden" @change="setScreenshot(index, $event)" />
                        </label>
                        <p v-if="form.errors[`items.${index}.screenshot`]" class="mt-1 text-[10px] text-danger-ink">{{ form.errors[`items.${index}.screenshot`] }}</p>
                    </div>
                </div>

                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-[18px] border border-dashed border-line-strong bg-white/40 px-4 py-3 text-[11px] font-semibold text-muted transition hover:border-primary-300 hover:text-ink"
                    @click="addItem"
                >
                    <PlusIcon class="h-4 w-4" />Ещё группа
                </button>
            </section>

            <aside class="space-y-4">
                <section class="surface-card p-5">
                    <p class="text-[12px] font-bold text-ink">Формат отчёта</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">Групп может быть сколько угодно — по одной на каждую проверяемую вещь.</p>
                    <ol class="mt-3 space-y-2 text-[11px] leading-relaxed text-muted">
                        <li><span class="font-semibold text-ink">1.</span> Краткое объяснение.</li>
                        <li><span class="font-semibold text-ink">2.</span> Ссылка страницы для тестирования.</li>
                        <li><span class="font-semibold text-ink">3.</span> Скриншот.</li>
                    </ol>
                    <p class="mt-4 text-[10px] leading-relaxed text-muted">Получатель увидит отчёт в разделе «Отчёты» и получит письмо.</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
