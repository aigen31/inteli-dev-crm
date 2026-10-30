<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    type: { type: String, default: 'task' }, // task, invoice, priority, payment
});

const taskColors = {
    pending_review: { bg: 'bg-warning/10', text: 'text-warning', dot: 'bg-warning' },
    queued: { bg: 'bg-primary-500/12', text: 'text-primary-300', dot: 'bg-primary-400' },
    in_progress: { bg: 'bg-[#8fd0ff]/10', text: 'text-[#8fd0ff]', dot: 'bg-[#8fd0ff]' },
    awaiting_review: { bg: 'bg-[#d7b7ff]/10', text: 'text-[#d7b7ff]', dot: 'bg-[#d7b7ff]' },
    rejected_by_client: { bg: 'bg-danger/10', text: 'text-danger-ink', dot: 'bg-danger' },
    completed: { bg: 'bg-success/10', text: 'text-success', dot: 'bg-success' },
    rejected_by_admin: { bg: 'bg-white/5', text: 'text-muted', dot: 'bg-white/40' },
};

const invoiceColors = {
    pending: { bg: 'bg-warning/10', text: 'text-warning', dot: 'bg-warning' },
    paid: { bg: 'bg-success/10', text: 'text-success', dot: 'bg-success' },
    cancelled: { bg: 'bg-white/5', text: 'text-muted', dot: 'bg-white/40' },
    refunded: { bg: 'bg-danger/10', text: 'text-danger-ink', dot: 'bg-danger' },
};

const priorityColors = {
    low: { bg: 'bg-white/5', text: 'text-muted', dot: 'bg-white/40' },
    medium: { bg: 'bg-warning/10', text: 'text-warning', dot: 'bg-warning' },
    high: { bg: 'bg-danger/10', text: 'text-danger-ink', dot: 'bg-danger' },
};

const paymentColors = {
    yookassa: { bg: 'bg-primary-500/12', text: 'text-primary-300', dot: 'bg-primary-400' },
    transfer: { bg: 'bg-[#8fd0ff]/10', text: 'text-[#8fd0ff]', dot: 'bg-[#8fd0ff]' },
};

const colorMap = { task: taskColors, invoice: invoiceColors, priority: priorityColors, payment: paymentColors };
const colors = computed(() => colorMap[props.type]?.[props.status] ?? {
    bg: 'bg-white/5',
    text: 'text-muted',
    dot: 'bg-white/40',
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center gap-1.5 rounded-full border border-white/5 px-2.5 py-0.5 text-xs font-medium',
            colors.bg,
            colors.text,
        ]"
    >
        <span :class="['h-1.5 w-1.5 rounded-full', colors.dot]"></span>
        <slot />
    </span>
</template>
