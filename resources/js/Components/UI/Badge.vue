<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    type: { type: String, default: 'task' },
});

const taskColors = {
    pending_review: { bg: 'bg-warning/10', text: 'text-warning', dot: 'bg-warning' },
    queued: { bg: 'bg-primary-500/10', text: 'text-primary-700', dot: 'bg-primary-500' },
    in_progress: { bg: 'bg-sky/15', text: 'text-[#3d718f]', dot: 'bg-sky' },
    awaiting_review: { bg: 'bg-accent/15', text: 'text-[#a55e3d]', dot: 'bg-accent' },
    rejected_by_client: { bg: 'bg-danger/10', text: 'text-danger-ink', dot: 'bg-danger' },
    completed: { bg: 'bg-success/10', text: 'text-success', dot: 'bg-success' },
    rejected_by_admin: { bg: 'bg-ink/[0.06]', text: 'text-muted', dot: 'bg-faint' },
};

const invoiceColors = {
    pending: { bg: 'bg-warning/10', text: 'text-warning', dot: 'bg-warning' },
    paid: { bg: 'bg-success/10', text: 'text-success', dot: 'bg-success' },
    cancelled: { bg: 'bg-ink/[0.06]', text: 'text-muted', dot: 'bg-faint' },
    refunded: { bg: 'bg-danger/10', text: 'text-danger-ink', dot: 'bg-danger' },
};

const priorityColors = {
    low: { bg: 'bg-ink/[0.06]', text: 'text-muted', dot: 'bg-faint' },
    medium: { bg: 'bg-warning/10', text: 'text-warning', dot: 'bg-warning' },
    high: { bg: 'bg-danger/10', text: 'text-danger-ink', dot: 'bg-danger' },
};

const paymentColors = {
    yookassa: { bg: 'bg-primary-500/10', text: 'text-primary-700', dot: 'bg-primary-500' },
    transfer: { bg: 'bg-sky/15', text: 'text-[#3d718f]', dot: 'bg-sky' },
};

const colorMap = { task: taskColors, invoice: invoiceColors, priority: priorityColors, payment: paymentColors };
const colors = computed(
    () =>
        colorMap[props.type]?.[props.status] ?? {
            bg: 'bg-ink/[0.06]',
            text: 'text-muted',
            dot: 'bg-faint',
        },
);
</script>

<template>
    <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-semibold', colors.bg, colors.text]">
        <span :class="['h-1.5 w-1.5 rounded-full', colors.dot]"></span>
        <slot />
    </span>
</template>
