<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ClientLayout from '@/Components/Layout/ClientLayout.vue';
import Card from '@/Components/UI/Card.vue';
import TextInput from '@/Components/Form/TextInput.vue';
import Button from '@/Components/UI/Button.vue';
import ConsentCheckbox from '@/Components/Legal/ConsentCheckbox.vue';
import LocaleSwitcher from '@/Components/UI/LocaleSwitcher.vue';
import SecretFeaturesCard from '@/Components/UI/SecretFeaturesCard.vue';
import { LockClosedIcon, SwatchIcon, UserCircleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    user: Object,
    hasConsent: { type: Boolean, default: false },
});

const allowedTab = new URLSearchParams(window.location.search).get('tab');
const activeTab = ref(['profile', 'security', 'appearance'].includes(allowedTab) ? allowedTab : 'profile');

function go(tab) {
    activeTab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}
const tabs = [
    { value: 'profile', label: 'Профиль', icon: UserCircleIcon, title: 'Личные данные', description: 'Как к вам обращаться и как с вами связаться.' },
    { value: 'security', label: 'Безопасность', icon: LockClosedIcon, title: 'Пароль и доступ', description: 'Смените пароль, если хотите обновить защиту аккаунта.' },
    { value: 'appearance', label: 'Интерфейс', icon: SwatchIcon, title: 'Интерфейс и доступ', description: 'Язык, внешний вид и секретные возможности вашего аккаунта.' },
];
const currentTab = tabs.find((item) => item.value === activeTab.value) || tabs[0];

const profileForm = useForm({
    name: props.user.name,
    phone: props.user.phone || '',
    messenger_contacts: props.user.messenger_contacts || {},
    consent: false,
});

function updateProfile() {
    profileForm.put(route('client.profile.update'));
}

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function updatePassword() {
    passwordForm.put(route('password.update'), {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <ClientLayout title="Профиль">
        <div class="mb-6 max-w-[760px]">
            <p class="eyebrow">Личный кабинет</p>
            <h1 class="page-title mt-1">Профиль и настройки</h1>
            <p class="mt-2 max-w-[620px] text-[12px] leading-relaxed text-muted">
                Личные данные, безопасность и интерфейс — в трёх отдельных разделах, без лишних блоков на одном экране.
            </p>
        </div>

        <div class="grid max-w-[980px] grid-cols-1 gap-5 lg:grid-cols-[210px_1fr]">
            <aside class="lg:sticky lg:top-6 lg:self-start">
                <nav class="surface-card flex gap-1 overflow-x-auto p-2 lg:block">
                    <button
                        v-for="item in tabs"
                        :key="item.value"
                        type="button"
                        :class="[
                            'flex shrink-0 items-center gap-2 rounded-[11px] px-3 py-2.5 text-left text-[11px] font-semibold transition lg:w-full',
                            activeTab === item.value ? 'bg-primary-soft text-primary-800' : 'text-muted hover:bg-ink/[0.04] hover:text-ink',
                        ]"
                        @click="go(item.value)"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ item.label }}
                    </button>
                </nav>
            </aside>

            <div class="min-w-0 space-y-4">
                <div class="surface-card flex items-start gap-3 p-4 sm:p-5">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[12px] bg-primary-soft text-primary-700">
                        <component :is="currentTab.icon" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="eyebrow">Настройки / {{ currentTab.label }}</p>
                        <h2 class="mt-1 font-display text-[20px] font-medium tracking-[-0.03em] text-ink">{{ currentTab.title }}</h2>
                        <p class="mt-1 text-[11px] leading-relaxed text-muted">{{ currentTab.description }}</p>
                    </div>
                </div>

                <Card v-if="activeTab === 'profile'" title="Контактные данные">
                    <form @submit.prevent="updateProfile" class="space-y-4">
                        <TextInput v-model="profileForm.name" label="Имя" :error="profileForm.errors.name" required />
                        <TextInput :model-value="user.email" label="Email" disabled />
                        <TextInput v-model="profileForm.phone" label="Телефон" :error="profileForm.errors.phone" />

                        <ConsentCheckbox v-if="!hasConsent" v-model="profileForm.consent" :error="profileForm.errors.consent" id="profile-consent" />

                        <div class="flex justify-end pt-4">
                            <Button type="submit" :disabled="profileForm.processing || (!hasConsent && !profileForm.consent)">Сохранить</Button>
                        </div>
                    </form>
                </Card>

                <Card v-else-if="activeTab === 'security'" title="Смена пароля">
                    <form @submit.prevent="updatePassword" class="space-y-4">
                        <TextInput v-model="passwordForm.current_password" label="Текущий пароль" type="password" :error="passwordForm.errors.current_password" required />
                        <TextInput v-model="passwordForm.password" label="Новый пароль" type="password" :error="passwordForm.errors.password" required />
                        <TextInput v-model="passwordForm.password_confirmation" label="Подтверждение пароля" type="password" required />

                        <div class="flex justify-end pt-4">
                            <Button type="submit" :disabled="passwordForm.processing">Сменить пароль</Button>
                        </div>
                    </form>
                </Card>

                <div v-else class="grid grid-cols-1 gap-4 xl:grid-cols-[.8fr_1.2fr]">
                    <LocaleSwitcher variant="settings" />
                    <SecretFeaturesCard />
                </div>
            </div>
        </div>
    </ClientLayout>
</template>
