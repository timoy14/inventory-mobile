<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import Money from '@/components/app/Money.vue';
import ProductImage from '@/components/app/ProductImage.vue';
import QuantityStepper from '@/components/app/QuantityStepper.vue';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';

const props = defineProps({
    product: { type: Object, required: true },
    can_buy: { type: Boolean, required: true },
});

const form = useForm({ product_id: props.product.id, quantity: 1 });

const stockLine = computed(() => {
    if (props.product.stock === 0) return 'Out of stock';
    if (props.product.stock <= 5) return `Only ${props.product.stock} left`;
    return `${props.product.stock} in stock`;
});

const add = () => form.post('/cart/items', { preserveScroll: true });
</script>

<template>
    <Head :title="product.name" />
    <AppShell title="Product" back="/shop">
        <div class="space-y-4">
            <div
                v-if="product.images.length"
                class="-mx-4 flex snap-x snap-mandatory overflow-x-auto"
            >
                <img
                    v-for="(image, i) in product.images"
                    :key="i"
                    :src="image.url"
                    :alt="product.name"
                    class="aspect-square w-full shrink-0 snap-center object-cover"
                />
            </div>
            <ProductImage
                v-else
                :alt="product.name"
                class="aspect-square w-full rounded-2xl"
                icon-class="size-10"
            />

            <div class="space-y-1">
                <h1 class="text-xl font-semibold text-violet-950">
                    {{ product.name }}
                </h1>
                <p class="text-2xl font-bold text-violet-600">
                    <Money :amount="product.price" />
                </p>
                <p v-if="product.seller_name" class="text-sm text-violet-600">
                    Sold by {{ product.seller_name }}
                </p>
                <p
                    class="text-sm font-medium"
                    :class="
                        product.stock <= 5 ? 'text-red-600' : 'text-violet-500'
                    "
                >
                    {{ stockLine }}
                </p>
            </div>

            <p
                v-if="product.description"
                class="text-sm whitespace-pre-line text-violet-900"
            >
                {{ product.description }}
            </p>

            <div v-if="can_buy" class="flex items-center gap-3">
                <QuantityStepper
                    v-model="form.quantity"
                    :max="Math.max(product.stock, 1)"
                    :disabled="product.stock === 0 || form.processing"
                />
                <Button
                    class="h-11 flex-1 rounded-xl bg-violet-600 hover:bg-violet-700"
                    :disabled="product.stock === 0 || form.processing"
                    @click="add"
                >
                    Add to cart
                </Button>
            </div>
        </div>
    </AppShell>
</template>
