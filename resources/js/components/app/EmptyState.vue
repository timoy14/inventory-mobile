<script setup>
defineProps({
    icon: { type: [Object, Function], required: true },
    title: { type: String, required: true },
    hint: String,
});
</script>

<template>
    <div
        class="flex flex-col items-center rounded-2xl bg-white px-6 py-10 text-center ring-1 ring-violet-100"
    >
        <span
            class="flex size-14 items-center justify-center rounded-full bg-violet-100 text-violet-600"
        >
            <component :is="icon" class="size-7" />
        </span>
        <h2 class="mt-4 text-base font-semibold text-violet-950">
            {{ title }}
        </h2>
        <p v-if="hint" class="mt-1 text-sm text-violet-600">{{ hint }}</p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
