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
