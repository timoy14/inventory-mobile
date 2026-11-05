<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ShoppingCart, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import Money from '@/components/app/Money.vue';
import ProductImage from '@/components/app/ProductImage.vue';
import QuantityStepper from '@/components/app/QuantityStepper.vue';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';

const props = defineProps({
    cart: { type: Object, required: true },
    unavailable_ids: { type: Array, required: true },
});

const quantities = reactive({});
const timers = reactive({});
const hasPending = computed(() => Object.keys(timers).length > 0);
const busy = ref(false);
const checkingOut = ref(false);

watch(
    () => props.cart.items,
    (items) => {
        for (const item of items) quantities[item.product_id] = item.quantity;
    },
    { immediate: true },
);

const cancelTimer = (productId) => {
    clearTimeout(timers[productId]);
    delete timers[productId];
};

const cancelAllTimers = () =>
    Object.keys(timers).forEach((id) => cancelTimer(Number(id)));

// A pending debounce must not fire after the line is gone or the page is left.
onBeforeUnmount(cancelAllTimers);
const stopNavigationListener = router.on('before', (event) => {
    // Our own visits (patch/delete/checkout) clear their timers first; anything else leaves the page.
    if (event.detail.visit.method === 'get') cancelAllTimers();
});
onBeforeUnmount(stopNavigationListener);

// Commit after the user pauses, so rapid taps send one request.
const setQuantity = (line, value) => {
    quantities[line.product_id] = value;
    cancelTimer(line.product_id);
    timers[line.product_id] = setTimeout(() => {
        delete timers[line.product_id];
        if (value === line.quantity) return;
        router.patch(
            `/cart/items/${line.product_id}`,
            { quantity: value },
            {
                preserveScroll: true,
                onStart: () => (busy.value = true),
                onFinish: () => (busy.value = false),
            },
        );
    }, 500);
};

const remove = (line) => {
    cancelTimer(line.product_id);
    router.delete(`/cart/items/${line.product_id}`, {
        preserveScroll: true,
        onStart: () => (busy.value = true),
        onFinish: () => (busy.value = false),
    });
};

const checkout = () =>
    router.post(
        '/checkout',
        {},
        {
            onStart: () => (checkingOut.value = true),
            onFinish: () => (checkingOut.value = false),
        },
    );
</script>

<template>
    <Head title="Cart" />
    <AppShell title="Cart">
        <EmptyState
            v-if="!cart.items.length"
            :icon="ShoppingCart"
            title="Your cart is empty"
            hint="Find something you like."
        >
            <Button
                as-child
                class="h-11 rounded-xl bg-violet-600 px-6 hover:bg-violet-700"
            >
                <Link href="/shop">Browse the shop</Link>
            </Button>
        </EmptyState>

        <div v-else class="space-y-3 pb-28">
            <div
                v-for="line in cart.items"
                :key="line.product_id"
                class="space-y-2 rounded-2xl bg-white p-3 ring-1 ring-violet-100"
            >
                <div class="flex gap-3">
                    <ProductImage
                        :url="line.product.images[0]?.url"
                        :alt="line.product.name"
                        class="size-16 shrink-0 rounded-xl"
                    />
                    <div class="min-w-0 flex-1">
                        <h3
                            class="line-clamp-2 text-sm font-medium text-violet-950"
                        >
                            {{ line.product.name }}
                        </h3>
                        <p class="text-sm text-violet-600">
                            <Money :amount="line.unit_price" />
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Remove item"
                        class="flex size-11 items-center justify-center rounded-full text-violet-500 active:bg-violet-50 disabled:opacity-40"
                        :disabled="busy"
                        @click="remove(line)"
                    >
                        <Trash2 class="size-5" />
                    </button>
                </div>
                <div class="flex items-center justify-between">
                    <QuantityStepper
                        :model-value="
                            quantities[line.product_id] ?? line.quantity
                        "
                        :max="line.product.stock || undefined"
                        :disabled="busy"
                        @update:model-value="setQuantity(line, $event)"
                    />
                    <span class="text-base font-semibold text-violet-950">
                        <Money :amount="line.line_total" />
                    </span>
                </div>
                <p
                    v-if="unavailable_ids.includes(line.product_id)"
                    class="text-sm font-medium text-red-600"
                    role="alert"
                >
                    Unavailable — remove or lower quantity
                </p>
            </div>

            <div
                class="fixed inset-x-0 bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-10 border-t border-violet-100 bg-white/95 px-4 py-3 backdrop-blur"
            >
                <div class="mb-2 flex justify-between text-sm">
                    <span class="text-violet-600">Subtotal</span>
                    <span class="font-semibold text-violet-950">
                        <Money :amount="cart.subtotal" />
                    </span>
                </div>
                <Button
                    class="h-12 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
                    :disabled="checkingOut || busy || hasPending"
                    @click="checkout"
                >
                    Checkout
                </Button>
            </div>
        </div>
    </AppShell>
</template>
