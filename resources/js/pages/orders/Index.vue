<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ReceiptText } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import LoadMore from '@/components/app/LoadMore.vue';
import Money from '@/components/app/Money.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';
import { formatDate } from '@/lib/format';

defineProps({
    orders: { type: Array, required: true },
    next_page_url: { type: [String, null], required: true },
});
</script>

<template>
    <Head title="Orders" />
    <AppShell title="Orders">
        <EmptyState
            v-if="!orders.length"
            :icon="ReceiptText"
            title="No orders yet"
            hint="Your orders will show up here."
        >
            <Button
                as-child
                class="h-11 rounded-xl bg-violet-600 px-6 hover:bg-violet-700"
            >
                <Link href="/shop">Browse the shop</Link>
            </Button>
        </EmptyState>
        <div v-else class="space-y-3">
            <Link
                v-for="order in orders"
                :key="order.id"
                :href="`/orders/${order.id}`"
                class="flex items-center justify-between gap-3 rounded-2xl bg-white p-4 ring-1 ring-violet-100 active:bg-violet-50"
            >
                <div class="min-w-0">
                    <p class="font-medium text-violet-950">
                        {{ order.number }}
                    </p>
                    <p class="text-xs text-violet-500">
                        {{ formatDate(order.created_at) }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <span class="text-sm font-semibold text-violet-950">
                        <Money :amount="order.total" />
                    </span>
                    <StatusBadge :status="order.status" />
                </div>
            </Link>
            <LoadMore
                :next="next_page_url"
                :only="['orders', 'next_page_url']"
            />
        </div>
    </AppShell>
</template>
