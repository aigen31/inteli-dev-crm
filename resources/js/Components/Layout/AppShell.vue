<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowRightOnRectangleIcon,
    BanknotesIcon,
    Bars3Icon,
    CheckCircleIcon,
    Cog6ToothIcon,
    FolderIcon,
    HomeIcon,
    InboxIcon,
    MagnifyingGlassIcon,
    QuestionMarkCircleIcon,
    RectangleStackIcon,
    SparklesIcon,
    UserIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import KikiAvatar from '@/Components/Kiki/KikiAvatar.vue';
import AssistantPanel from '@/Components/Assistant/AssistantPanel.vue';
import EarlyAccessBanner from '@/Components/UI/EarlyAccessBanner.vue';
import { useAssistant } from '@/Composables/useAssistant';

const props = defineProps({
    role: { type: String, default: 'admin' },
    title: { type: String, default: null },
    eyebrow: { type: String, default: null },
    bare: { type: Boolean, default: false },
});

const page = usePage();
const { open } = useAssistant();
const mobileMenu = ref(false);

const path = computed(() => page.url.split('?')[0]);
const user = computed(() => page.props.auth?.user);
const isAdmin = computed(() => props.role === 'admin');

const navigation = computed(() =>
    isAdmin.value
        ? [
              { label: 'Обзор', href: route('admin.dashboard'), icon: HomeIcon, active: path.value === '/admin/dashboard' },
              {
                  label: 'Задачи',
                  href: route('admin.tasks.index'),
                  icon: CheckCircleIcon,
                  active: path.value.startsWith('/admin/tasks'),
              },
              {
                  label: 'Проекты',
                  href: route('admin.projects.index'),
                  icon: FolderIcon,
                  active: path.value.startsWith('/admin/projects'),
              },
              {
                  label: 'Библиотека',
                  href: route('admin.forms.index'),
                  icon: RectangleStackIcon,
                  active: path.value.startsWith('/admin/forms') || path.value.startsWith('/admin/task-templates'),
              },
              { label: 'Клиенты', href: route('admin.clients.index'), icon: UsersIcon, active: path.value.startsWith('/admin/clients') },
              { label: 'Счета', href: route('admin.invoices.index'), icon: BanknotesIcon, active: path.value.startsWith('/admin/invoices') },
              { label: 'Отчёты', href: route('admin.reports.index'), icon: InboxIcon, active: path.value.startsWith('/admin/reports') },
              { label: 'Кики', href: route('assistant.index'), icon: SparklesIcon, active: path.value.startsWith('/assistant') },
          ]
        : [
              { label: 'Обзор', href: route('client.dashboard'), icon: HomeIcon, active: path.value === '/dashboard' },
              { label: 'Проекты', href: route('client.projects.index'), icon: FolderIcon, active: path.value.startsWith('/projects') },
              { label: 'Задачи', href: route('client.tasks.index'), icon: CheckCircleIcon, active: path.value.startsWith('/tasks') },
              { label: 'Счета', href: route('client.invoices.index'), icon: BanknotesIcon, active: path.value.startsWith('/invoices') },
              { label: 'Отчёты', href: route('client.reports.index'), icon: InboxIcon, active: path.value.startsWith('/reports') },
              { label: 'Кики', href: route('assistant.index'), icon: SparklesIcon, active: path.value.startsWith('/assistant') },
          ],
);

const mobileNavigation = computed(() => navigation.value.filter((item) => !item.href.endsWith('/assistant')));

const secondary = computed(() => [
    ...(isAdmin.value
        ? [{ label: 'Настройки', href: route('admin.settings.edit'), icon: Cog6ToothIcon, active: path.value.startsWith('/admin/settings') }]
        : [{ label: 'Профиль', href: route('client.profile.edit'), icon: UserIcon, active: path.value.startsWith('/profile') }]),
    { label: 'Помощь', href: route('help.index'), icon: QuestionMarkCircleIcon, active: path.value.startsWith('/help') },
]);

const sectionLabel = computed(() => {
    if (path.value.startsWith('/admin/settings')) return 'Настройки';
    if (path.value.startsWith('/profile')) return 'Профиль';
    if (path.value.startsWith('/help')) return 'Помощь';
    return navigation.value.find((item) => item.active)?.label ?? (isAdmin.value ? 'Обзор' : 'Кабинет');
});

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen text-ink">
        <Head :title="title" />

        <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
            <div class="absolute -right-40 -top-52 h-[520px] w-[520px] rounded-full bg-primary-500/[0.08] blur-3xl"></div>
            <div class="absolute -bottom-52 -left-32 h-[460px] w-[460px] rounded-full bg-accent/[0.09] blur-3xl"></div>
        </div>

        <!-- Desktop sidebar -->
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-[236px] flex-col border-r border-line/80 bg-surface/60 px-4 py-6 backdrop-blur-xl lg:flex">
            <Link :href="isAdmin ? route('admin.dashboard') : route('client.dashboard')" class="flex items-center gap-1.5 px-3">
                <span class="shrink-0 whitespace-nowrap font-display text-[17px] font-medium tracking-[-0.04em] text-ink">inteli dev</span>
                <span class="rounded-full border border-line bg-white/50 px-2 py-[2px] font-mono text-[8px] uppercase tracking-[0.18em] text-muted">crm</span>
                <span class="rounded-full border border-warning/25 bg-warning/10 px-2 py-[2px] font-mono text-[8px] uppercase tracking-[0.14em] text-warning">beta</span>
            </Link>

            <p class="eyebrow mt-10 px-3">Рабочее пространство</p>
            <nav class="mt-3 space-y-1">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    :class="[
                        'group flex items-center gap-3 rounded-2xl px-3 py-2.5 text-[14px] transition-all duration-150',
                        item.active
                            ? 'bg-primary-soft font-semibold text-primary-800'
                            : 'font-medium text-ink-soft hover:bg-ink/[0.04] hover:text-ink',
                    ]"
                >
                    <component :is="item.icon" :class="['h-[19px] w-[19px]', item.active ? 'text-primary-600' : 'text-muted group-hover:text-ink']" />
                    <span>{{ item.label }}</span>
                    <span v-if="item.active" class="ml-auto h-1.5 w-1.5 rounded-full bg-primary-500"></span>
                </Link>
            </nav>

            <button
                type="button"
                class="mt-6 flex w-full items-center gap-3 rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 to-surface px-3 py-3 text-left transition hover:border-primary-300"
                @click="open"
            >
                <KikiAvatar variant="face" fit="contain" :size="38" />
                <span class="min-w-0">
                    <span class="block text-[13px] font-semibold text-ink">Спросить Кики</span>
                    <span class="block truncate text-[11px] text-muted">задачи, счета, клиенты</span>
                </span>
                <SparklesIcon class="ml-auto h-4 w-4 text-primary-500" />
            </button>

            <div class="mt-auto space-y-1">
                <Link
                    v-for="item in secondary"
                    :key="item.label"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 rounded-2xl px-3 py-2.5 text-[13px] transition',
                        item.active ? 'bg-ink/[0.05] font-semibold text-ink' : 'font-medium text-muted hover:bg-ink/[0.04] hover:text-ink',
                    ]"
                >
                    <component :is="item.icon" class="h-[18px] w-[18px]" />
                    {{ item.label }}
                </Link>

                <div class="mt-3 flex items-center gap-3 rounded-2xl border border-line bg-white/50 px-3 py-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-500 text-[11px] font-bold text-white">
                        {{ user?.initials }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[12px] font-semibold text-ink">{{ user?.name }}</span>
                        <span class="block text-[10px] text-muted">{{ isAdmin ? 'Владелец' : 'Клиент' }}</span>
                    </span>
                    <button type="button" class="text-muted transition hover:text-danger" title="Выйти" @click="logout">
                        <ArrowRightOnRectangleIcon class="h-[18px] w-[18px]" />
                    </button>
                </div>
            </div>
        </aside>

        <!-- Mobile top bar -->
        <header class="sticky top-0 z-40 flex items-center gap-3 border-b border-line/70 bg-canvas/85 px-4 py-3 backdrop-blur-xl lg:hidden">
            <button type="button" class="-ml-1 rounded-xl p-2 text-ink-soft" @click="mobileMenu = !mobileMenu">
                <XMarkIcon v-if="mobileMenu" class="h-5 w-5" />
                <Bars3Icon v-else class="h-5 w-5" />
            </button>
            <Link :href="isAdmin ? route('admin.dashboard') : route('client.dashboard')" class="flex items-center gap-1.5">
                <span class="shrink-0 whitespace-nowrap font-display text-[15px] font-medium tracking-[-0.04em]">inteli dev</span>
                <span class="font-mono text-[8px] uppercase tracking-[0.16em] text-muted">crm</span>
                <span class="rounded-full border border-warning/25 bg-warning/10 px-1.5 py-[1px] font-mono text-[7px] uppercase tracking-[0.12em] text-warning">beta</span>
            </Link>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="rounded-xl p-2 text-muted" @click="open">
                    <MagnifyingGlassIcon class="h-5 w-5" />
                </button>
                <button type="button" class="rounded-full p-0.5" @click="open">
                    <KikiAvatar variant="face" fit="contain" :size="30" />
                </button>
            </div>
        </header>

        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div v-if="mobileMenu" class="fixed inset-x-3 top-[58px] z-50 rounded-3xl border border-line bg-surface p-2 shadow-2xl lg:hidden">
                <Link
                    v-for="item in [...navigation, ...secondary]"
                    :key="item.label"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 rounded-2xl px-4 py-3 text-sm',
                        item.active ? 'bg-primary-soft font-semibold text-primary-800' : 'text-ink-soft',
                    ]"
                    @click="mobileMenu = false"
                >
                    <component :is="item.icon" class="h-5 w-5" />
                    {{ item.label }}
                </Link>
                <button class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-sm text-danger" @click="logout">
                    <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    Выйти
                </button>
            </div>
        </transition>

        <div class="relative z-10 lg:pl-[236px]">
            <!-- Desktop top strip -->
            <div v-if="!bare" class="mx-auto hidden max-w-[1280px] items-center gap-4 px-8 pt-6 lg:flex">
                <div class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.16em] text-muted">
                    <span>inteli dev</span>
                    <span class="text-faint">/</span>
                    <span class="text-ink-soft">{{ sectionLabel }}</span>
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full border border-line bg-white/50 py-1.5 pl-3 pr-2 text-[12px] text-muted transition hover:border-line-strong hover:text-ink"
                        @click="open"
                    >
                        <MagnifyingGlassIcon class="h-3.5 w-3.5" />
                        Найти или спросить
                        <span class="rounded-md border border-line bg-surface px-1.5 py-0.5 font-mono text-[9px]">⌘K</span>
                    </button>
                    <Link
                        :href="isAdmin ? route('admin.settings.edit') : route('client.profile.edit')"
                        class="rounded-full border border-line bg-white/50 p-2 text-muted transition hover:border-line-strong hover:text-ink"
                    >
                        <Cog6ToothIcon class="h-4 w-4" />
                    </Link>
                </div>
            </div>

            <main
                :class="[
                    'mx-auto w-full',
                    bare
                        ? 'max-w-none px-2 pb-2 pt-2 sm:px-3 lg:px-3 lg:pb-3 lg:pt-2'
                        : 'max-w-[1280px] px-4 pb-28 pt-6 sm:px-6 lg:px-8 lg:pb-16 lg:pt-7',
                ]"
            >
                <EarlyAccessBanner v-if="!bare" />

                <div v-if="!bare" class="mb-7 flex flex-wrap items-end justify-between gap-5">
                    <div class="min-w-0">
                        <p class="eyebrow">{{ eyebrow || (isAdmin ? 'Рабочий стол' : 'Личный кабинет') }}</p>
                        <h1 class="page-title mt-2">
                            <slot name="header-title">{{ title }}</slot>
                        </h1>
                    </div>
                    <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2.5">
                        <slot name="actions" />
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="mb-5">
                    <div class="flex items-start gap-3 rounded-2xl border border-success/25 bg-success/[0.08] px-4 py-3 text-sm text-success">
                        <CheckCircleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ page.props.flash.success }}</span>
                    </div>
                </div>
                <div v-if="page.props.flash?.error" class="mb-5">
                    <div class="rounded-2xl border border-danger/20 bg-danger/[0.07] px-4 py-3 text-sm text-danger-ink">
                        {{ page.props.flash.error }}
                    </div>
                </div>

                <slot />
            </main>
        </div>

        <!-- Mobile bottom nav -->
        <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line/80 bg-surface/90 px-2 pb-[max(8px,env(safe-area-inset-bottom))] pt-1.5 backdrop-blur-xl lg:hidden">
            <div class="mx-auto flex max-w-md items-center justify-around">
                <Link
                    v-for="item in mobileNavigation"
                    :key="item.label"
                    :href="item.href"
                    :class="['flex min-w-[64px] flex-col items-center gap-1 rounded-2xl px-2 py-1.5', item.active ? 'text-primary-700' : 'text-muted']"
                >
                    <component :is="item.icon" class="h-[21px] w-[21px]" />
                    <span class="text-[10px] font-medium">{{ item.label }}</span>
                </Link>
                <button type="button" class="flex min-w-[64px] flex-col items-center gap-1 rounded-2xl px-2 py-1.5 text-muted" @click="open">
                    <KikiAvatar variant="face" fit="contain" :size="23" />
                    <span class="text-[10px] font-medium">Кики</span>
                </button>
            </div>
        </nav>

        <AssistantPanel />
    </div>
</template>
