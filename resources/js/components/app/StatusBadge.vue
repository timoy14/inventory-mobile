<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const styles = {
    active: 'bg-green-50 text-green-700 ring-green-200',
    paid: 'bg-green-50 text-green-700 ring-green-200',
    completed: 'bg-green-50 text-green-700 ring-green-200',
    pending: 'bg-amber-50 text-amber-700 ring-amber-200',
    inactive: 'bg-gray-100 text-gray-600 ring-gray-200',
    cancelled: 'bg-red-50 text-red-700 ring-red-200',
    suspended: 'bg-red-50 text-red-700 ring-red-200',
};

const classes = computed(
    () => styles[props.status.toLowerCase()] ?? styles.inactive,
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium capitalize ring-1 ring-inset"
        :class="classes"
        >{{ status }}</span
    >
</template>
