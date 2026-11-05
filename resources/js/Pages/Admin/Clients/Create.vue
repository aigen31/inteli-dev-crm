<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    consent: false,
});

function submit() {
    form.post(route('admin.clients.store'));
}
</script>

<template>
    <AdminLayout title="Новый клиент">
        <template #actions>
            <Link :href="route('admin.clients.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />Назад</Button></Link>
            <Button size="sm" :disabled="form.processing || !form.consent" @click="submit">Создать клиента</Button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.2fr_.8fr]">
            <section class="surface-card p-5 sm:p-6">
                <p class="eyebrow">Аккаунт</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><TextInput v-model="form.name" label="Имя / название" :error="form.errors.name" placeholder="Как обращаться к клиенту" /></div>
                    <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" placeholder="client@example.com" />
                    <TextInput v-model="form.phone" label="Телефон" :error="form.errors.phone" placeholder="+7 …" />
                    <TextInput v-model="form.password" label="Пароль" type="password" :error="form.errors.password" hint="Минимум 8 символов. Передайте клиенту безопасным способом." />
                    <TextInput v-model="form.password_confirmation" label="Подтверждение пароля" type="password" />
                </div>

                <div class="mt-5">
                    <ConsentCheckbox v-model="form.consent" :error="form.errors.consent" id="admin-client-consent" variant="contract" />
                </div>
            </section>

            <aside class="space-y-4">
                <section class="surface-card flex items-start gap-3 p-5">
                    <KikiAvatar :size="48" />
                    <div>
                        <p class="text-[12px] font-bold text-ink">Что получит клиент</p>
                        <ul class="mt-2 space-y-2 text-[11px] leading-relaxed text-muted">
                            <li>· личный кабинет с задачами и счетами;</li>
                            <li>· шаблоны заявок вместо пустого поля;</li>
                            <li>· оплату ЮКассой или по реквизитам;</li>
                            <li>· Кики для вопросов по своим данным.</li>
                        </ul>
                    </div>
                </section>
                <section class="surface-card p-5">
                    <p class="text-[12px] font-bold text-ink">Данные и согласие</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">
                        Данные вводятся для заключения и исполнения договора. Клиент подтверждает
                        <a href="/legal/consent" target="_blank" class="font-semibold text-primary-700 underline">согласие</a>
                        и знакомится с
                        <a href="/legal/privacy" target="_blank" class="font-semibold text-primary-700 underline">политикой</a>
                        при первом входе или первой заявке.
                    </p>
                </section>
                <section class="surface-card p-5">
                    <p class="eyebrow">Поля клиента</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">После создания откройте карточку клиента и заполните «Статус приёма заявок» и другие поля.</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
