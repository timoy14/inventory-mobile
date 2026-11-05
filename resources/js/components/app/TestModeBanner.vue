<script setup>
import { FlaskConical } from '@lucide/vue';

defineProps({
    message: { type: String, required: true },
});
</script>

<template>
    <div
        role="note"
        class="flex items-start gap-3 rounded-2xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200"
    >
        <FlaskConical class="mt-0.5 size-5 shrink-0 text-amber-600" />
        <p>{{ message }}</p>
    </div>
</template>
