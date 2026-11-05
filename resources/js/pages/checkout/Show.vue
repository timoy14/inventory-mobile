<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import OrderSummary from '@/components/app/OrderSummary.vue';
import TestModeBanner from '@/components/app/TestModeBanner.vue';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';

const props = defineProps({
    order: { type: Object, required: true },
    test_mode_message: { type: String, required: true },
});

const form = useForm({});
// Disabled while in flight; the server also locks per order.
const pay = () => {
    if (form.processing) return;
    form.post(`/orders/${props.order.id}/pay`);
};
</script>

<template>
    <Head title="Checkout" />
    <AppShell title="Checkout" back="/cart" hide-tabs>
        <div class="space-y-4 pb-28">
            <TestModeBanner :message="test_mode_message" />
            <p class="text-sm text-violet-600">Order {{ order.number }}</p>
            <OrderSummary
                :items="order.items"
                :subtotal="order.subtotal"
                :total="order.total"
            />
        </div>
        <div
            class="fixed inset-x-0 bottom-0 z-10 border-t border-violet-100 bg-white/95 px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur"
        >
            <Button
                class="h-12 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
                :disabled="form.processing"
                @click="pay"
            >
                {{ form.processing ? 'Processing...' : 'Pay (test)' }}
            </Button>
        </div>
    </AppShell>
</template>
