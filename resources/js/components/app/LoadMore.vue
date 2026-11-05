<script setup>
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

// Fetches `next` and relies on the page's server prop being an Inertia::merge()
// (or a matching `only` list) so the new rows are appended to the existing ones.
const props = defineProps({
    next: { type: [String, null], required: true },
    only: Array,
});

const loading = ref(false);

const load = () => {
    if (!props.next || loading.value) return;
    router.get(
        props.next,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            ...(props.only ? { only: props.only } : {}),
            onStart: () => (loading.value = true),
            onFinish: () => (loading.value = false),
        },
    );
};
</script>

<template>
    <div v-if="next" class="flex justify-center py-2">
        <Button
            variant="outline"
            class="h-11 rounded-xl border-violet-200 px-6 text-violet-700"
            :disabled="loading"
            @click="load"
        >
            {{ loading ? 'Loading...' : 'Load more' }}
        </Button>
    </div>
</template>
