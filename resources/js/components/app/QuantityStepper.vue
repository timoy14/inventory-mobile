<script setup>
import { Minus, Plus } from '@lucide/vue';

const props = defineProps({
    min: Number,
    max: Number,
    disabled: Boolean,
});
const model = defineModel({ type: Number, required: true });

const lower = () => props.min ?? 1;
const set = (n) => {
    const upper = props.max ?? Infinity;
    model.value = Math.min(Math.max(n, lower()), upper);
};
</script>

<template>
    <div
        class="inline-flex items-center rounded-full bg-violet-50 ring-1 ring-violet-100"
    >
        <button
            type="button"
            aria-label="Decrease quantity"
            class="flex size-11 items-center justify-center rounded-full text-violet-700 active:bg-violet-100 disabled:opacity-40"
            :disabled="disabled || model <= lower()"
            @click="set(model - 1)"
        >
            <Minus class="size-4" />
        </button>
        <span
            class="min-w-8 text-center text-sm font-semibold tabular-nums"
            aria-live="polite"
            >{{ model }}</span
        >
        <button
            type="button"
            aria-label="Increase quantity"
            class="flex size-11 items-center justify-center rounded-full text-violet-700 active:bg-violet-100 disabled:opacity-40"
            :disabled="disabled || (max !== undefined && model >= max)"
            @click="set(model + 1)"
        >
            <Plus class="size-4" />
        </button>
    </div>
</template>
