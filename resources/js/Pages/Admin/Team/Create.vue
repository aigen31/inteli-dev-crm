<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Components/Layout/AdminLayout.vue';
import Button from '@/Components/UI/Button.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import { ArrowLeftIcon, WrenchScrewdriverIcon } from '@heroicons/vue/24/outline';

const form = useForm({ name: '', email: '', phone: '', password: '' });

function submit() {
    form.post(route('admin.team.store'));
}
</script>

<template>
    <AdminLayout title="Новый исполнитель">
        <template #actions>
            <Link :href="route('admin.team.index')"><Button variant="ghost" size="sm"><ArrowLeftIcon class="h-4 w-4" />К команде</Button></Link>
            <Button size="sm" :disabled="form.processing" @click="submit">Добавить</Button>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.2fr_.8fr]">
            <section class="surface-card p-5 sm:p-6">
                <p class="eyebrow">Аккаунт исполнителя</p>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><TextInput v-model="form.name" label="Имя" :error="form.errors.name" placeholder="Как обращаться к исполнителю" /></div>
                    <TextInput v-model="form.email" label="Email" type="email" :error="form.errors.email" placeholder="executor@example.com" />
                    <TextInput v-model="form.phone" label="Телефон" :error="form.errors.phone" placeholder="+7 …" />
                    <div class="sm:col-span-2"><TextInput v-model="form.password" label="Пароль" type="password" :error="form.errors.password" hint="Минимум 8 символов. Передайте исполнителю безопасным способом." /></div>
                </div>
            </section>

            <aside class="space-y-4">
                <section class="surface-card flex items-start gap-3 p-5">
                    <KikiAvatar :size="48" />
                    <div>
                        <p class="text-[12px] font-bold text-ink">Что сможет исполнитель</p>
                        <ul class="mt-2 space-y-2 text-[11px] leading-relaxed text-muted">
                            <li>· видеть только назначенные задачи;</li>
                            <li>· отмечать чек-лист и комментировать;</li>
                            <li>· переводить задачу в работу и на проверку;</li>
                            <li>· отправлять отчёты клиенту.</li>
                        </ul>
                    </div>
                </section>
                <section class="surface-card p-5">
                    <p class="text-[12px] font-bold text-ink">Доступы</p>
                    <p class="mt-2 text-[11px] leading-relaxed text-muted">Счета, клиенты, настройки и чужие задачи исполнителю недоступны. Назначайте его на задачу в карточке задачи.</p>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
