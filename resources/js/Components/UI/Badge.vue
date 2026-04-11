<script setup>
const props = defineProps({
    status: { type: String, required: true },
    type: { type: String, default: 'task' }, // task, invoice, priority
});

const taskColors = {
    pending_review: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-400' },
    queued: { bg: 'bg-blue-100', text: 'text-blue-800', dot: 'bg-blue-400' },
    in_progress: { bg: 'bg-indigo-100', text: 'text-indigo-800', dot: 'bg-indigo-400' },
    awaiting_review: { bg: 'bg-purple-100', text: 'text-purple-800', dot: 'bg-purple-400' },
    rejected_by_client: { bg: 'bg-red-100', text: 'text-red-800', dot: 'bg-red-400' },
    completed: { bg: 'bg-green-100', text: 'text-green-800', dot: 'bg-green-400' },
    rejected_by_admin: { bg: 'bg-gray-100', text: 'text-gray-800', dot: 'bg-gray-400' },
};

const invoiceColors = {
    pending: { bg: 'bg-amber-100', text: 'text-amber-800', dot: 'bg-amber-400' },
    paid: { bg: 'bg-green-100', text: 'text-green-800', dot: 'bg-green-400' },
    cancelled: { bg: 'bg-gray-100', text: 'text-gray-600', dot: 'bg-gray-400' },
    refunded: { bg: 'bg-red-100', text: 'text-red-800', dot: 'bg-red-400' },
};

const priorityColors = {
    low: { bg: 'bg-gray-100', text: 'text-gray-600', dot: 'bg-gray-400' },
    medium: { bg: 'bg-amber-100', text: 'text-amber-700', dot: 'bg-amber-400' },
    high: { bg: 'bg-red-100', text: 'text-red-700', dot: 'bg-red-500' },
};

const colorMap = { task: taskColors, invoice: invoiceColors, priority: priorityColors };
const colors = colorMap[props.type]?.[props.status] ?? { bg: 'bg-gray-100', text: 'text-gray-600', dot: 'bg-gray-400' };
</script>

<template>
    <span :class="['inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium', colors.bg, colors.text]">
        <span :class="['w-1.5 h-1.5 rounded-full', colors.dot]"></span>
        <slot />
    </span>
</template>
