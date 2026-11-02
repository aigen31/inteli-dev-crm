<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { ArrowDownTrayIcon, ArrowLeftIcon, ArrowRightIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    open: { type: Boolean, default: false },
    images: { type: Array, default: () => [] },
    index: { type: Number, default: 0 },
});

const emit = defineEmits(['update:open', 'update:index']);

const current = computed(() => props.images[props.index] || null);
const hasMany = computed(() => props.images.length > 1);

function close() {
    emit('update:open', false);
}

function go(step) {
    if (!props.images.length) return;
    emit('update:index', (props.index + step + props.images.length) % props.images.length);
}

function onKeydown(event) {
    if (!props.open) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowRight') go(1);
    if (event.key === 'ArrowLeft') go(-1);
}

watch(
    () => props.open,
    (value) => {
        document.body.style.overflow = value ? 'hidden' : '';
    },
);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open && current" class="fixed inset-0 z-[110] flex items-center justify-center bg-ink/75 p-4 backdrop-blur-sm sm:p-8" @click.self="close">
            <button type="button" class="absolute right-4 top-4 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20" aria-label="Закрыть" @click="close">
                <XMarkIcon class="h-5 w-5" />
            </button>

            <button v-if="hasMany" type="button" class="absolute left-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:left-6" aria-label="Предыдущее изображение" @click="go(-1)">
                <ArrowLeftIcon class="h-5 w-5" />
            </button>
            <button v-if="hasMany" type="button" class="absolute right-3 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20 sm:right-6" aria-label="Следующее изображение" @click="go(1)">
                <ArrowRightIcon class="h-5 w-5" />
            </button>

            <figure class="flex max-h-full max-w-[1100px] flex-col items-center gap-3" @click.stop>
                <img :src="current.url" :alt="current.alt || 'Изображение'" class="max-h-[78vh] max-w-full rounded-[18px] bg-white object-contain shadow-2xl" />
                <figcaption class="flex max-w-full items-center gap-3 rounded-full bg-ink/55 px-4 py-2 text-[11px] text-white/80 backdrop-blur">
                    <span class="truncate">{{ current.alt || 'Изображение' }}</span>
                    <span v-if="hasMany" class="font-mono text-[10px] text-white/50">{{ index + 1 }} / {{ images.length }}</span>
                    <a :href="current.download" class="inline-flex shrink-0 items-center gap-1 text-white/90 hover:text-white">
                        <ArrowDownTrayIcon class="h-3.5 w-3.5" />
                        Скачать
                    </a>
                </figcaption>
            </figure>
        </div>
    </Teleport>
</template>
