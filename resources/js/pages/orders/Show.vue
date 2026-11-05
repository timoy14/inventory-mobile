<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import OrderSummary from '@/components/app/OrderSummary.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppShell from '@/layouts/AppShell.vue';
import { formatDateTime } from '@/lib/format';

const props = defineProps({
    order: { type: Object, required: true },
    can_cancel: { type: Boolean, required: true },
    can_pay: { type: Boolean, required: true },
});

const confirming = ref(false);
const form = useForm({});
const cancel = () => {
    if (form.processing) return;
    form.post(`/orders/${props.order.id}/cancel`, {
        onFinish: () => (confirming.value = false),
    });
};
</script>

<template>
    <Head :title="order.number" />
    <AppShell :title="order.number" back="/orders">
        <div class="space-y-4">
            <div
                class="space-y-2 rounded-2xl bg-white p-4 text-sm ring-1 ring-violet-100"
            >
                <div class="flex items-center justify-between">
                    <span class="text-violet-600">Status</span>
                    <StatusBadge :status="order.status" />
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-violet-600">Payment</span>
                    <StatusBadge :status="order.payment_status" />
                </div>
                <div v-if="order.paid_at" class="flex justify-between">
                    <span class="text-violet-600">Paid</span>
                    <span class="text-violet-950">{{
                        formatDateTime(order.paid_at)
                    }}</span>
                </div>
            </div>

            <OrderSummary
                :items="order.items"
                :subtotal="order.subtotal"
                :total="order.total"
            />

            <Button
                v-if="can_pay"
                as-child
                class="h-12 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
            >
                <Link :href="`/checkout/${order.id}`">Pay now (test)</Link>
            </Button>
            <Button
                v-if="can_cancel"
                variant="outline"
                class="h-12 w-full rounded-xl border-red-200 text-red-600"
                @click="confirming = true"
            >
                Cancel order
            </Button>
        </div>

        <Dialog v-model:open="confirming">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Cancel this order?</DialogTitle>
                    <DialogDescription>
                        Order {{ order.number }} will be cancelled.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="confirming = false">
                        Keep order
                    </Button>
                    <Button
                        class="bg-red-600 hover:bg-red-700"
                        :disabled="form.processing"
                        @click="cancel"
                    >
                        Cancel order
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppShell>
</template>
