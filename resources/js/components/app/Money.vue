<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

// Display only: the API supplies the 2-decimal string; the app never computes totals.
defineProps({
    amount: { type: String, required: true },
});

const page = usePage();
const symbol = computed(() => page.props.settings?.currency_symbol ?? '$');
</script>

<template>
    <span class="tabular-nums">{{ symbol }}{{ amount }}</span>
</template>
