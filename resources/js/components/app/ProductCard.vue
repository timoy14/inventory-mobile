<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Money from '@/components/app/Money.vue';
import ProductImage from '@/components/app/ProductImage.vue';

const props = defineProps({
    product: { type: Object, required: true },
    href: { type: String, required: true },
});

const stock = computed(() => {
    const n = props.product.stock;
    if (n > 5) return { cls: 'text-violet-500', label: `${n} in stock` };
    if (n > 0) return { cls: 'text-amber-600', label: `Only ${n} left` };
    return { cls: 'text-red-600', label: 'Out of stock' };
});
</script>

<template>
    <Link
        :href="href"
        class="block overflow-hidden rounded-2xl bg-white ring-1 ring-violet-100 active:bg-violet-50"
    >
        <ProductImage
            :url="product.images?.[0]?.url"
            :alt="product.name"
            class="aspect-square w-full"
            icon-class="size-8"
        />
        <div class="space-y-0.5 p-3">
            <h3 class="line-clamp-2 text-sm font-medium text-violet-950">
                {{ product.name }}
            </h3>
            <p class="text-base font-semibold text-violet-600">
                <Money :amount="product.price" />
            </p>
            <p class="text-xs" :class="stock.cls">{{ stock.label }}</p>
        </div>
    </Link>
</template>
