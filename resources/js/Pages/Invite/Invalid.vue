<script setup>
import { Head } from '@inertiajs/vue3';
import GuestLayout from '@/Components/Layout/GuestLayout.vue';
import Button from '@/Components/UI/Button.vue';
import { Link } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
</script>

<template>
    <GuestLayout title="Приглашение недействительно">
        <Head title="Приглашение недействительно" />

        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-warning/10 text-warning">
            <ExclamationTriangleIcon class="h-5 w-5" />
        </div>
        <h1 class="mt-4 font-display text-[26px] leading-tight tracking-[-0.03em] text-ink">Ссылка недействительна</h1>
        <p class="mt-2 text-[12px] leading-relaxed text-muted">
            Приглашение уже использовано, истекло или было отозвано. Попросите владельца проекта создать новую ссылку.
        </p>

        <Link :href="route('login')" class="mt-5 block">
            <Button class="w-full">Перейти ко входу</Button>
        </Link>
    </GuestLayout>
</template>
