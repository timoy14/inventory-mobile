<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Package, Plus } from '@lucide/vue';
import EmptyState from '@/components/app/EmptyState.vue';
import LoadMore from '@/components/app/LoadMore.vue';
import Money from '@/components/app/Money.vue';
import ProductImage from '@/components/app/ProductImage.vue';
import StatusBadge from '@/components/app/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';

defineProps({
    products: { type: Array, required: true },
    next_page_url: { type: [String, null], required: true },
});
</script>

<template>
    <Head title="My products" />
    <AppShell title="My products">
        <EmptyState
            v-if="!products.length"
            :icon="Package"
            title="No products yet"
            hint="Add your first product to start selling."
        />
        <div v-else class="space-y-3">
            <Link
                v-for="product in products"
                :key="product.id"
                :href="`/seller/products/${product.id}/edit`"
                class="flex items-center gap-3 rounded-2xl bg-white p-3 ring-1 ring-violet-100 active:bg-violet-50"
            >
                <ProductImage
                    :url="product.images[0]?.url"
                    :alt="product.name"
                    class="size-16 shrink-0 rounded-xl bg-violet-100 text-violet-400"
                />
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium text-violet-950">
                        {{ product.name }}
                    </p>
                    <p class="truncate text-xs text-violet-500">
                        SKU {{ product.sku }}
                    </p>
                    <p class="mt-1 text-sm text-violet-950">
                        <Money :amount="product.price" />
                        <span class="text-violet-500">
                            · {{ product.stock }} in stock</span
                        >
                    </p>
                </div>
                <StatusBadge :status="product.status" />
            </Link>
            <LoadMore
                :next="next_page_url"
                :only="['products', 'next_page_url']"
            />
        </div>

        <Button
            as-child
            class="fixed right-4 bottom-[calc(5.5rem+env(safe-area-inset-bottom))] z-20 h-12 rounded-full bg-violet-600 px-5 shadow-lg hover:bg-violet-700"
        >
            <Link href="/seller/products/create">
                <Plus class="size-5" /> Add product
            </Link>
        </Button>
    </AppShell>
</template>
