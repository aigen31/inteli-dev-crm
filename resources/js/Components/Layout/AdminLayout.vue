<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, default: null },
});

const page = usePage();
const menuOpen = ref(false);

const isActive = (path) => page.url.split('?')[0].startsWith(path);

const navigation = computed(() => [
    { name: 'Дашборд', href: route('admin.dashboard'), active: page.url.split('?')[0] === '/admin/dashboard' },
    { name: 'Клиенты', href: route('admin.clients.index'), active: isActive('/admin/clients') },
    { name: 'Задачи', href: route('admin.tasks.index'), active: isActive('/admin/tasks') },
    { name: 'Счета', href: route('admin.invoices.index'), active: isActive('/admin/invoices') },
    { name: 'Настройки', href: route('admin.settings.edit'), active: isActive('/admin/settings') },
]);

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen text-ink">
        <Head :title="title" />

        <!-- Floating glass navigation -->
        <div class="fixed inset-x-0 top-4 z-50 px-4">
            <div
                class="mx-auto flex h-16 max-w-[1200px] items-center gap-3 rounded-[20px] border border-line bg-[rgba(24,21,58,0.72)] px-4 shadow-[0_16px_40px_rgba(0,0,0,0.4)] backdrop-blur-xl sm:px-6"
            >
                <Link :href="route('admin.dashboard')" class="flex items-center gap-2 whitespace-nowrap">
                    <span class="text-[1.05rem] font-bold tracking-tight text-ink">inteli dev</span>
                    <span
                        class="rounded-full border border-line px-2 py-[3px] font-mono text-[9px] uppercase tracking-[0.18em] text-muted"
                    >
                        CRM
                    </span>
                </Link>

                <nav class="ml-2 hidden items-center gap-1 lg:flex">
                    <Link
                        v-for="item in navigation"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            'rounded-full px-3.5 py-2 text-sm transition-colors',
                            item.active
                                ? 'bg-primary-soft font-medium text-white'
                                : 'text-ink-soft hover:bg-white/5 hover:text-white',
                        ]"
                    >
                        {{ item.name }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-3">
                    <span class="hidden text-sm text-ink-soft sm:block">{{ page.props.auth?.user?.name }}</span>
                    <span
                        class="hidden rounded-full border border-primary-500/30 bg-primary-soft px-2.5 py-1 font-mono text-[10px] uppercase tracking-[0.14em] text-primary-300 md:block"
                    >
                        Администратор
                    </span>
                    <button
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-line text-ink-soft transition-colors hover:border-line-strong hover:text-white"
                        title="Выйти"
                        @click="logout"
                    >
                        <ArrowRightOnRectangleIcon class="h-4 w-4" />
                    </button>
                    <button
                        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-line text-ink-soft lg:hidden"
                        @click="menuOpen = !menuOpen"
                    >
                        <XMarkIcon v-if="menuOpen" class="h-4 w-4" />
                        <Bars3Icon v-else class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Mobile menu -->
            <div
                v-if="menuOpen"
                class="mx-auto mt-2 max-w-[1200px] rounded-2xl border border-line bg-surface p-2 shadow-[0_16px_40px_rgba(0,0,0,0.4)] lg:hidden"
            >
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'block rounded-xl px-4 py-2.5 text-sm transition-colors',
                        item.active ? 'bg-primary-soft text-white' : 'text-ink-soft hover:bg-white/5 hover:text-white',
                    ]"
                    @click="menuOpen = false"
                >
                    {{ item.name }}
                </Link>
            </div>
        </div>

        <!-- Content -->
        <main class="mx-auto w-full max-w-[1200px] px-4 pb-16 pt-28 sm:px-6 lg:pt-32">
            <div class="mb-8 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-muted">Управление</p>
                    <h1 class="mt-2 text-[28px] font-bold leading-tight text-ink sm:text-[32px]">
                        <slot name="header-title">{{ title }}</slot>
                    </h1>
                </div>
                <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3">
                    <slot name="actions" />
                </div>
            </div>

            <div v-if="page.props.flash?.success" class="mb-4">
                <div class="rounded-xl border border-success/30 bg-success/10 px-4 py-3">
                    <p class="text-sm font-medium text-success">{{ page.props.flash.success }}</p>
                </div>
            </div>
            <div v-if="page.props.flash?.error" class="mb-4">
                <div class="rounded-xl border border-danger/30 bg-danger/10 px-4 py-3">
                    <p class="text-sm font-medium text-danger-ink">{{ page.props.flash.error }}</p>
                </div>
            </div>

            <slot />
        </main>
    </div>
</template>
