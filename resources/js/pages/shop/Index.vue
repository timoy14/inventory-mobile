<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Search, Store } from '@lucide/vue';
import { ref, watch } from 'vue';
import EmptyState from '@/components/app/EmptyState.vue';
import LoadMore from '@/components/app/LoadMore.vue';
import ProductCard from '@/components/app/ProductCard.vue';
import { Input } from '@/components/ui/input';
import AppShell from '@/layouts/AppShell.vue';

const props = defineProps({
    products: { type: Array, required: true },
    next_page_url: { type: [String, null], required: true },
    categories: Array,
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
let timer;

const visit = (params) => {
    const query = {};
    if (params.search) query.search = params.search;
    if (params.category_id) query.category_id = params.category_id;
    router.get('/shop', query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        if (value.trim() === (props.filters.search ?? '')) return;
        visit({ search: value.trim(), category_id: props.filters.category_id });
    }, 350);
});

const pick = (id) => visit({ search: search.value.trim(), category_id: id });
const chip = (active) =>
    active
        ? 'bg-violet-600 text-white'
        : 'bg-white text-violet-700 ring-1 ring-violet-100';
</script>

<template>
    <Head title="Shop" />
    <AppShell title="Shop">
        <div class="space-y-4">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-violet-400"
                />
                <Input
                    v-model="search"
                    type="search"
                    placeholder="Search products"
                    aria-label="Search products"
                    class="h-11 rounded-xl bg-white pl-9 ring-1 ring-violet-100"
                />
            </div>

            <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1">
                <button
                    type="button"
                    class="h-9 shrink-0 rounded-full px-4 text-sm font-medium"
                    :class="chip(!filters.category_id)"
                    @click="pick(undefined)"
                >
                    All
                </button>
                <button
                    v-for="category in categories ?? []"
                    :key="category.id"
                    type="button"
                    class="h-9 shrink-0 rounded-full px-4 text-sm font-medium"
                    :class="chip(filters.category_id === category.id)"
                    @click="pick(category.id)"
                >
                    {{ category.name }}
                </button>
            </div>

            <div v-if="products.length" class="grid grid-cols-2 gap-3">
                <ProductCard
                    v-for="product in products"
                    :key="product.id"
                    :product="product"
                    :href="`/shop/${product.id}`"
                />
            </div>
            <EmptyState
                v-else
                :icon="Store"
                title="No products found"
                hint="Try a different search or category."
            />

            <LoadMore
                :next="next_page_url"
                :only="['products', 'next_page_url']"
            />
        </div>
    </AppShell>
</template>
