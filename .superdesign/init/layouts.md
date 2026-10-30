# Layouts — CRM

Shell components rendered on every page.

## `resources/js/Components/Layout/AdminLayout.vue`

```vue
<script setup>
import { ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import {
    HomeIcon,
    UsersIcon,
    ClipboardDocumentListIcon,
    CurrencyDollarIcon,
    Cog6ToothIcon,
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, default: null },
});

const page = usePage();
const sidebarOpen = ref(false);

const navigation = [
    { name: 'Дашборд', href: route('admin.dashboard'), icon: HomeIcon, active: route().current('admin.dashboard') },
    { name: 'Клиенты', href: route('admin.clients.index'), icon: UsersIcon, active: route().current('admin.clients.*') },
    { name: 'Задачи', href: route('admin.tasks.index'), icon: ClipboardDocumentListIcon, active: route().current('admin.tasks.*') },
    { name: 'Счета', href: route('admin.invoices.index'), icon: CurrencyDollarIcon, active: route().current('admin.invoices.*') },
    { name: 'Настройки', href: route('admin.settings.edit'), icon: Cog6ToothIcon, active: route().current('admin.settings.*') },
];

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head :title="title" />

        <!-- Mobile sidebar backdrop -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-primary-900 text-white flex flex-col transition-transform duration-200',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <div class="px-6 py-5 border-b border-primary-800 flex items-center justify-between">
                <span class="text-lg font-bold">CRM — Управление</span>
                <button class="lg:hidden text-primary-200 hover:text-white" @click="sidebarOpen = false">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors',
                        item.active
                            ? 'bg-primary-800 text-white'
                            : 'text-primary-200 hover:bg-primary-800 hover:text-white',
                    ]"
                >
                    <component :is="item.icon" class="w-5 h-5" />
                    {{ item.name }}
                </Link>
            </nav>
            <div class="px-3 py-4 border-t border-primary-800">
                <button
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-primary-200 hover:bg-primary-800 hover:text-white w-full transition-colors"
                    @click="logout"
                >
                    <ArrowRightOnRectangleIcon class="w-5 h-5" />
                    Выйти
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Header -->
            <header class="h-16 bg-white border-b border-gray-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden text-gray-400 hover:text-gray-600" @click="sidebarOpen = true">
                        <Bars3Icon class="w-6 h-6" />
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">
                        <slot name="header-title">{{ title }}</slot>
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">{{ page.props.auth?.user?.name }}</span>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="page.props.flash?.success" class="mx-4 sm:mx-6 lg:mx-8 mt-4">
                <div class="rounded-md p-4 bg-green-50 border border-green-200">
                    <p class="text-sm font-medium text-green-700">{{ page.props.flash.success }}</p>
                </div>
            </div>
            <div v-if="page.props.flash?.error" class="mx-4 sm:mx-6 lg:mx-8 mt-4">
                <div class="rounded-md p-4 bg-red-50 border border-red-200">
                    <p class="text-sm font-medium text-red-700">{{ page.props.flash.error }}</p>
                </div>
            </div>

            <!-- Page content -->
            <main class="px-4 sm:px-6 lg:px-8 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
```

## `resources/js/Components/Layout/ClientLayout.vue`

```vue
<script setup>
import { ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import {
    HomeIcon,
    ClipboardDocumentListIcon,
    CurrencyDollarIcon,
    UserCircleIcon,
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, default: null },
});

const page = usePage();
const sidebarOpen = ref(false);

const navigation = [
    { name: 'Дашборд', href: route('client.dashboard'), icon: HomeIcon, active: route().current('client.dashboard') },
    { name: 'Мои задачи', href: route('client.tasks.index'), icon: ClipboardDocumentListIcon, active: route().current('client.tasks.*') },
    { name: 'Мои счета', href: route('client.invoices.index'), icon: CurrencyDollarIcon, active: route().current('client.invoices.*') },
    { name: 'Профиль', href: route('client.profile.edit'), icon: UserCircleIcon, active: route().current('client.profile.*') },
];

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head :title="title" />

        <!-- Mobile sidebar backdrop -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-gray-900/50 lg:hidden" @click="sidebarOpen = false" />

        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-64 bg-primary-900 text-white flex flex-col transition-transform duration-200',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <div class="px-6 py-5 border-b border-primary-800 flex items-center justify-between">
                <span class="text-lg font-bold">CRM — Личный кабинет</span>
                <button class="lg:hidden text-primary-200 hover:text-white" @click="sidebarOpen = false">
                    <XMarkIcon class="w-5 h-5" />
                </button>
            </div>
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    :class="[
                        'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors',
                        item.active
                            ? 'bg-primary-800 text-white'
                            : 'text-primary-200 hover:bg-primary-800 hover:text-white',
                    ]"
                >
                    <component :is="item.icon" class="w-5 h-5" />
                    {{ item.name }}
                </Link>
            </nav>
            <div class="px-3 py-4 border-t border-primary-800">
                <button
                    class="flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-primary-200 hover:bg-primary-800 hover:text-white w-full transition-colors"
                    @click="logout"
                >
                    <ArrowRightOnRectangleIcon class="w-5 h-5" />
                    Выйти
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Header -->
            <header class="h-16 bg-white border-b border-gray-200 sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-4">
                    <button class="lg:hidden text-gray-400 hover:text-gray-600" @click="sidebarOpen = true">
                        <Bars3Icon class="w-6 h-6" />
                    </button>
                    <h1 class="text-lg font-semibold text-gray-800">
                        <slot name="header-title">{{ title }}</slot>
                    </h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-500">{{ page.props.auth?.user?.name }}</span>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="page.props.flash?.success" class="mx-4 sm:mx-6 lg:mx-8 mt-4">
                <div class="rounded-md p-4 bg-green-50 border border-green-200">
                    <p class="text-sm font-medium text-green-700">{{ page.props.flash.success }}</p>
                </div>
            </div>
            <div v-if="page.props.flash?.error" class="mx-4 sm:mx-6 lg:mx-8 mt-4">
                <div class="rounded-md p-4 bg-red-50 border border-red-200">
                    <p class="text-sm font-medium text-red-700">{{ page.props.flash.error }}</p>
                </div>
            </div>

            <!-- Page content -->
            <main class="px-4 sm:px-6 lg:px-8 py-6">
                <slot />
            </main>
        </div>
    </div>
</template>
```

## `resources/js/Components/Layout/GuestLayout.vue`

```vue
<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: null },
});
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12">
        <Head :title="title" />
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-primary-600">CRM</h1>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
```

## `resources/views/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="ru" class="h-full bg-gray-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>CRM</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full font-sans antialiased">
    @inertia
</body>
</html>
```

