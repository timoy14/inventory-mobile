<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, required: true },
    message: { type: String, required: true },
});

const title = computed(() =>
    props.status === 404
        ? 'Not found'
        : props.status === 403 || props.status === 401
          ? 'No access'
          : 'Something went wrong',
);
</script>

<template>
    <Head :title="title" />
    <main
        class="flex min-h-screen items-center justify-center bg-violet-50 p-6"
    >
        <div
            class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-sm ring-1 ring-violet-100"
        >
            <p class="text-3xl font-semibold text-violet-600">{{ status }}</p>
            <p class="mt-2 text-sm text-violet-700">{{ message }}</p>
            <a
                href="/"
                class="mt-5 inline-block rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-violet-700"
                >Go home</a
            >
        </div>
    </main>
</template>
