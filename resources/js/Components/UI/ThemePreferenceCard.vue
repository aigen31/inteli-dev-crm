<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { CheckIcon, MoonIcon, SparklesIcon, SunIcon } from '@heroicons/vue/24/outline';

const page = usePage();
const features = computed(() => page.props.features || {});
const current = computed(() => page.props.theme || 'default');

const options = computed(() => {
    const choices = [
        {
            value: 'default',
            label: 'Светлая',
            description: 'Тёплая бумага, мягкий фиолетовый акцент.',
            icon: SunIcon,
            preview: 'light',
        },
        {
            value: 'dark',
            label: 'Тёмная',
            description: 'Глубокий графит, приглушённые панели, мягкий свет.',
            icon: MoonIcon,
            preview: 'dark',
        },
    ];

    if (features.value.xp_theme === true) {
        choices.push({
            value: 'xp',
            label: 'Windows XP',
            description: 'Luna Blue, Tahoma и классические объёмные контролы.',
            icon: SparklesIcon,
            preview: 'xp',
        });
    }

    return choices;
});

function choose(theme) {
    if (theme === current.value) return;

    router.patch(route('settings.theme'), { theme }, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(),
    });
}
</script>

<template>
    <section class="surface-card overflow-hidden">
        <div class="flex flex-wrap items-start gap-3 border-b border-line/70 bg-white/35 px-5 py-4">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[11px] bg-primary-soft text-primary-700">
                <SunIcon class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[13px] font-bold text-ink">Тема оформления</p>
                <p class="mt-0.5 text-[10px] leading-relaxed text-muted">Выберите комфортный режим для глаз. Тема применяется ко всем разделам CRM и сохраняется в вашем профиле.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-2.5 p-4 sm:grid-cols-2 xl:grid-cols-3">
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                :class="[
                    'theme-choice group rounded-[15px] border p-2.5 text-left transition',
                    current === option.value
                        ? 'border-primary-300 bg-primary-soft/60 shadow-[inset_0_0_0_1px_rgba(109,90,230,.08)]'
                        : 'border-line bg-white/35 hover:border-line-strong hover:bg-white/55',
                ]"
                :aria-pressed="current === option.value"
                @click="choose(option.value)"
            >
                <div
                    :class="[
                        'theme-preview relative mb-2.5 h-[62px] overflow-hidden rounded-[9px] border',
                        option.preview === 'dark' ? 'border-[#392f4a] bg-[#14111b]' : '',
                        option.preview === 'light' ? 'border-[#e2dbea] bg-[#f6f1e8]' : '',
                        option.preview === 'xp' ? 'border-[#0831d9] bg-[#ece9d8]' : '',
                    ]"
                >
                    <template v-if="option.preview === 'dark'">
                        <div class="absolute inset-x-0 top-0 h-[13px] bg-[#0e0b14]"></div>
                        <div class="absolute bottom-0 left-0 top-[13px] w-[22px] bg-[#1d1728]"></div>
                        <div class="absolute left-[30px] right-2 top-[20px] h-[7px] rounded-[2px] bg-[#9b86f5]/70"></div>
                        <div class="absolute left-[30px] right-2 top-[31px] h-[18px] rounded-[2px] border border-[#322943] bg-[#211b2c]"></div>
                        <div class="absolute left-[30px] top-[20px] h-[7px] w-[34px] rounded-[2px] bg-[#f6f2fb]/85"></div>
                    </template>
                    <template v-else-if="option.preview === 'xp'">
                        <div class="h-[13px] bg-gradient-to-b from-[#0997ff] to-[#003dd7]"></div>
                        <div class="absolute bottom-0 left-0 top-[13px] w-[22px] bg-[#245edb]"></div>
                        <div class="absolute left-[30px] right-2 top-[20px] h-[7px] rounded-[1px] bg-[#0a246a]/70"></div>
                        <div class="absolute left-[30px] right-2 top-[31px] h-[18px] rounded-[1px] border border-[#7f9db9] bg-white"></div>
                    </template>
                    <template v-else>
                        <div class="h-[13px] bg-[#ece5f6]"></div>
                        <div class="absolute bottom-0 left-0 top-[13px] w-[22px] bg-[#f1eaf7]"></div>
                        <div class="absolute left-[30px] right-2 top-[20px] h-[7px] rounded-[2px] bg-[#7d68d8]/60"></div>
                        <div class="absolute left-[30px] right-2 top-[31px] h-[18px] rounded-[2px] border border-[#dfd6e9] bg-white"></div>
                    </template>
                </div>

                <div class="flex items-center gap-1.5">
                    <component :is="option.icon" class="h-3.5 w-3.5 shrink-0 text-primary-600" />
                    <span class="text-[11px] font-bold text-ink">{{ option.label }}</span>
                    <CheckIcon v-if="current === option.value" class="ml-auto h-3.5 w-3.5 text-primary-600" />
                </div>
                <p class="mt-1 text-[9px] leading-relaxed text-muted">{{ option.description }}</p>
            </button>
        </div>
    </section>
</template>
