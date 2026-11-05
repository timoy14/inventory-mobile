<script setup>
defineProps({
    storeName: { type: String, required: true },
    title: { type: String, required: true },
    flashError: String,
});
</script>

<template>
    <main
        class="flex min-h-screen items-center justify-center bg-gradient-to-b from-violet-100 via-violet-50 to-white px-4 pt-[max(1.5rem,env(safe-area-inset-top))] pb-[max(1.5rem,env(safe-area-inset-bottom))]"
    >
        <div class="w-full max-w-sm">
            <p class="mb-4 text-center text-2xl font-semibold text-violet-700">
                {{ storeName }}
            </p>
            <div
                class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-violet-100"
            >
                <h1 class="mb-4 text-lg font-semibold text-violet-900">
                    {{ title }}
                </h1>
                <p
                    v-if="flashError"
                    class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-100"
                    role="alert"
                >
                    {{ flashError }}
                </p>
                <slot />
            </div>
            <div class="mt-4 text-center text-sm text-violet-700">
                <slot name="footer" />
            </div>
        </div>
    </main>
</template>
