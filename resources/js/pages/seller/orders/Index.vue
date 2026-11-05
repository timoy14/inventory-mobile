<script setup>
import { Head } from '@inertiajs/vue3';
import { ChevronDown, ReceiptText } from '@lucide/vue';
import { ref } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import LoadMore from '@/components/app/LoadMore.vue';
import Money from '@/components/app/Money.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import AppShell from '@/layouts/AppShell.vue';
import { formatDate } from '@/lib/format';

defineProps({
    orders: { type: Array, required: true },
    next_page_url: { type: [String, null], required: true },
});

const open = ref(null);
const toggle = (id) => (open.value = open.value === id ? null : id);
</script>

<template>
    <Head title="Orders" />
    <AppShell title="Orders">
        <EmptyState
            v-if="!orders.length"
            :icon="ReceiptText"
            title="No orders yet"
            hint="Orders containing your products will show up here."
        />
        <div v-else class="space-y-3">
            <div
                v-for="order in orders"
                :key="order.id"
                class="rounded-2xl bg-white ring-1 ring-violet-100"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 p-4 text-left"
                    :aria-expanded="open === order.id"
                    @click="toggle(order.id)"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-violet-950">
                            {{ order.number }}
                        </p>
                        <p class="truncate text-xs text-violet-500">
                            {{ order.customer_name }} ·
                            {{ formatDate(order.created_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex flex-col items-end gap-1">
                            <span class="text-sm font-semibold text-violet-950">
                                <Money :amount="order.seller_total" />
                            </span>
                            <StatusBadge :status="order.status" />
                        </div>
                        <ChevronDown
                            class="size-4 text-violet-400 transition-transform"
                            :class="open === order.id ? 'rotate-180' : ''"
                        />
                    </div>
                </button>
                <ul
                    v-if="open === order.id"
                    class="space-y-2 border-t border-violet-100 px-4 py-3 text-sm"
                >
                    <li
                        v-for="(item, i) in order.items"
                        :key="item.id ?? i"
                        class="flex justify-between gap-3"
                    >
                        <span class="min-w-0 truncate text-violet-950">
                            {{ item.quantity }} × {{ item.product_name }}
                        </span>
                        <span class="text-violet-700">
                            <Money :amount="item.line_total" />
                        </span>
                    </li>
                </ul>
            </div>
            <LoadMore
                :next="next_page_url"
                :only="['orders', 'next_page_url']"
            />
        </div>
    </AppShell>
</template>
