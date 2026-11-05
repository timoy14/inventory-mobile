<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { CircleCheck } from '@lucide/vue';
import Money from '@/components/app/Money.vue';
import TestModeBanner from '@/components/app/TestModeBanner.vue';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';

defineProps({
    order: { type: Object, required: true },
    reference: { type: [String, null], required: true },
    test_mode_message: { type: String, required: true },
});
</script>

<template>
    <Head title="Payment complete" />
    <AppShell title="Payment">
        <div class="space-y-5 text-center">
            <CircleCheck
                class="mx-auto mt-4 size-20 text-green-600"
                aria-hidden="true"
            />
            <h2 class="text-xl font-semibold text-violet-950">
                Payment complete (test)
            </h2>
            <div
                class="space-y-1 rounded-2xl bg-white p-4 text-sm ring-1 ring-violet-100"
            >
                <p class="text-violet-600">Order {{ order.number }}</p>
                <p v-if="reference" class="font-mono text-violet-950">
                    {{ reference }}
                </p>
                <p class="text-lg font-semibold text-violet-950">
                    <Money :amount="order.total" />
                </p>
            </div>
            <TestModeBanner :message="test_mode_message" class="text-left" />
            <Button
                as-child
                class="h-12 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
            >
                <Link :href="`/orders/${order.id}`">View order</Link>
            </Button>
        </div>
    </AppShell>
</template>
