# UI components — CRM (Vue 3 + Inertia, Tailwind 4)

Shared primitives with full source. Paths relative to repo root.

## `resources/js/Components/UI/Button.vue`

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'button' },
    variant: { type: String, default: 'primary' },
    size: { type: String, default: 'md' },
    disabled: { type: Boolean, default: false },
    href: { type: String, default: null },
});

const baseClasses = 'inline-flex items-center justify-center font-medium rounded-md transition-colors duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

const variantClasses = computed(() => ({
    primary: 'bg-primary-500 hover:bg-primary-600 active:bg-primary-700 text-white',
    secondary: 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300',
    danger: 'bg-red-600 hover:bg-red-700 text-white',
    ghost: 'bg-transparent hover:bg-gray-100 text-gray-700',
    link: 'text-primary-600 hover:text-primary-700 underline-offset-2 hover:underline',
}[props.variant]));

const sizeClasses = computed(() => ({
    sm: 'px-3 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-3 text-base',
}[props.size]));
</script>

<template>
    <component
        :is="href ? 'a' : 'button'"
        :type="href ? undefined : type"
        :href="href"
        :disabled="disabled"
        :class="[baseClasses, variantClasses, sizeClasses]"
    >
        <slot />
    </component>
</template>
```

## `resources/js/Components/UI/Card.vue`

```vue
<script setup>
defineProps({
    title: { type: String, default: null },
});
</script>

<template>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div v-if="title || $slots.header" class="px-6 py-4 border-b border-gray-200">
            <slot name="header">
                <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
            </slot>
        </div>
        <div class="p-6">
            <slot />
        </div>
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200">
            <slot name="footer" />
        </div>
    </div>
</template>
```

## `resources/js/Components/UI/Badge.vue`

```vue
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
```

## `resources/js/Components/UI/Modal.vue`

```vue
<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    maxWidth: { type: String, default: 'lg' },
});

const emit = defineEmits(['close']);

const maxWidthClass = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
};
</script>

<template>
    <Teleport to="body">
        <Transition leave-active-class="duration-200">
            <div v-show="show" class="fixed inset-0 z-40 overflow-y-auto" @click.self="emit('close')">
                <div class="fixed inset-0 bg-gray-900/50 transition-opacity" />
                <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-200 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-show="show"
                            :class="['bg-white rounded-xl shadow-xl w-full max-h-[90vh] overflow-y-auto', maxWidthClass[maxWidth]]"
                            @click.stop
                        >
                            <div v-if="$slots.header" class="px-6 py-4 border-b border-gray-200">
                                <slot name="header" />
                            </div>
                            <div class="px-6 py-4">
                                <slot />
                            </div>
                            <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                                <slot name="footer" />
                            </div>
                        </div>
                    </Transition>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
```

## `resources/js/Components/UI/Pagination.vue`

```vue
<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: { type: Object, required: true },
});
</script>

<template>
    <nav v-if="links.last_page > 1" class="flex items-center justify-between px-6 py-3">
        <p class="text-sm text-gray-500">
            Показано {{ links.from }}–{{ links.to }} из {{ links.total }}
        </p>
        <div class="flex space-x-1">
            <template v-for="link in links.links" :key="link.label">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-md border',
                        link.active
                            ? 'bg-primary-500 text-white border-primary-500'
                            : 'border-gray-300 hover:bg-gray-50 text-gray-700',
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
                <span
                    v-else
                    :class="['px-3 py-1.5 text-sm rounded-md border border-gray-300 opacity-50 cursor-not-allowed text-gray-400']"
                    v-html="link.label"
                />
            </template>
        </div>
    </nav>
</template>
```

## `resources/js/Components/Form/TextInput.vue`

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    type: { type: String, default: 'text' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || `input-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
        </label>
        <input
            :id="inputId"
            :type="type"
            :value="modelValue"
            :class="[
                'w-full rounded-md border shadow-sm px-3 py-2 text-sm text-gray-700 placeholder-gray-400',
                'focus:ring-1 focus:outline-none',
                error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500',
                'disabled:bg-gray-100 disabled:text-gray-500',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        />
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
```

## `resources/js/Components/Form/SelectInput.vue`

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Выберите...' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const inputId = computed(() => props.id || `select-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
        </label>
        <select
            :id="inputId"
            :value="modelValue"
            :class="[
                'w-full rounded-md border shadow-sm px-3 py-2 text-sm text-gray-700',
                'focus:ring-1 focus:outline-none',
                error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500',
            ]"
            @change="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        >
            <option value="" disabled>{{ placeholder }}</option>
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
```

## `resources/js/Components/Form/TextArea.vue`

```vue
<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: null },
    error: { type: String, default: null },
    rows: { type: Number, default: 4 },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
const inputId = computed(() => props.id || `textarea-${Math.random().toString(36).slice(2, 9)}`);
</script>

<template>
    <div>
        <label v-if="label" :for="inputId" class="block text-sm font-medium text-gray-700 mb-1">
            {{ label }}
        </label>
        <textarea
            :id="inputId"
            :rows="rows"
            :value="modelValue"
            :class="[
                'w-full rounded-md border shadow-sm px-3 py-2 text-sm text-gray-700 placeholder-gray-400',
                'focus:ring-1 focus:outline-none resize-y min-h-[100px]',
                error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-primary-500 focus:ring-primary-500',
            ]"
            @input="emit('update:modelValue', $event.target.value)"
            v-bind="$attrs"
        />
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>
```

## `resources/js/Components/Form/InputError.vue`

```vue
<script setup>
defineProps({
    message: { type: String, default: null },
});
</script>

<template>
    <p v-if="message" class="mt-1 text-xs text-red-600">{{ message }}</p>
</template>
```

