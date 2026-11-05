<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    retry_url: { type: String, required: true },
});
</script>

<template>
    <Head title="Offline" />
    <main
        class="flex min-h-screen items-center justify-center bg-violet-50 p-6"
    >
        <div
            class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-sm ring-1 ring-violet-100"
        >
            <h1 class="text-lg font-semibold text-violet-900">
                Can't reach the store right now
            </h1>
            <p class="mt-2 text-sm text-violet-700">
                Check your connection and try again in a moment.
            </p>
            <a
                :href="retry_url"
                class="mt-5 inline-block rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700"
            >
                Retry
            </a>
        </div>
    </main>
</template>
