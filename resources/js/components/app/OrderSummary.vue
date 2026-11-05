<script setup>
import Money from '@/components/app/Money.vue';

// Display only: every amount is the API's string.
defineProps({
    items: { type: Array, required: true },
    subtotal: { type: String, required: true },
    total: { type: String, required: true },
});
</script>

<template>
    <div class="space-y-3 rounded-2xl bg-white p-4 ring-1 ring-violet-100">
        <div
            v-for="(item, index) in items"
            :key="index"
            class="flex items-start justify-between gap-3 text-sm"
        >
            <div class="min-w-0">
                <p class="font-medium text-violet-950">
                    {{ item.product_name }}
                </p>
                <p class="text-violet-600">
                    {{ item.quantity }} x <Money :amount="item.unit_price" />
                </p>
            </div>
            <span class="font-medium text-violet-950">
                <Money :amount="item.line_total" />
            </span>
        </div>
        <div class="space-y-1 border-t border-violet-100 pt-3 text-sm">
            <div class="flex justify-between text-violet-600">
                <span>Subtotal</span>
                <Money :amount="subtotal" />
            </div>
            <div
                class="flex justify-between text-base font-semibold text-violet-950"
            >
                <span>Total</span>
                <Money :amount="total" />
            </div>
        </div>
    </div>
</template>
