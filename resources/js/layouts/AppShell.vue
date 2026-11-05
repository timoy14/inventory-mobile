<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Package,
    Receipt,
    Store,
    ShoppingCart,
    User,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Toaster } from '@/components/ui/sonner';

const props = defineProps({
    title: { type: String, required: true },
    back: String,
    hideTabs: Boolean,
});

const page = usePage();

const role = computed(() => page.props.auth?.user?.role);
const cartCount = computed(() => Number(page.props.cart_count ?? 0));

const tabs = computed(() =>
    role.value === 'seller'
        ? [
              { label: 'My products', href: '/seller/products', icon: Package },
              { label: 'Orders', href: '/seller/orders', icon: Receipt },
              { label: 'Shop', href: '/shop', icon: Store },
              { label: 'Account', href: '/account', icon: User },
          ]
        : [
              { label: 'Shop', href: '/shop', icon: Store },
              {
                  label: 'Cart',
                  href: '/cart',
                  icon: ShoppingCart,
                  badge: cartCount.value,
              },
              { label: 'Orders', href: '/orders', icon: Receipt },
              { label: 'Account', href: '/account', icon: User },
          ],
);

// Longest matching prefix wins, so /seller/orders never lights up /orders.
const activeHref = computed(() => {
    const path = page.url.split('?')[0];
    const matches = tabs.value.filter(
        (t) => path === t.href || path.startsWith(`${t.href}/`),
    );
    return matches.sort((a, b) => b.href.length - a.href.length)[0]?.href;
});

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    },
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen bg-violet-50 font-sans text-violet-950">
        <header
            class="sticky top-0 z-30 border-b border-violet-100 bg-white/80 pt-[env(safe-area-inset-top)] backdrop-blur"
        >
            <div class="relative flex h-14 items-center justify-center px-4">
                <Link
                    v-if="props.back"
                    :href="props.back"
                    aria-label="Back"
                    class="absolute left-2 flex size-11 items-center justify-center rounded-full text-violet-700 active:bg-violet-100"
                >
                    <ArrowLeft class="size-5" />
                </Link>
                <h1 class="truncate px-12 text-base font-semibold">
                    {{ props.title }}
                </h1>
            </div>
        </header>

        <main
            class="mx-auto w-full max-w-lg space-y-4 px-4 pt-4"
            :class="
                props.hideTabs
                    ? 'pb-[max(1.5rem,env(safe-area-inset-bottom))]'
                    : 'pb-[calc(6rem+env(safe-area-inset-bottom))]'
            "
        >
            <slot />
        </main>

        <nav
            v-if="!props.hideTabs"
            aria-label="Main"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-violet-100 bg-white pb-[env(safe-area-inset-bottom)]"
        >
            <ul class="mx-auto flex max-w-lg">
                <li v-for="tab in tabs" :key="tab.href" class="flex-1">
                    <Link
                        :href="tab.href"
                        class="flex min-h-14 flex-col items-center justify-center gap-0.5 py-1.5 text-[11px] font-medium"
                        :class="
                            activeHref === tab.href
                                ? 'text-violet-600'
                                : 'text-violet-400'
                        "
                        :aria-current="
                            activeHref === tab.href ? 'page' : undefined
                        "
                    >
                        <span
                            class="relative flex h-7 w-12 items-center justify-center rounded-full transition-colors"
                            :class="
                                activeHref === tab.href ? 'bg-violet-100' : ''
                            "
                        >
                            <component :is="tab.icon" class="size-5" />
                            <span
                                v-if="tab.badge && tab.badge > 0"
                                class="absolute -top-1 right-0.5 flex min-w-4 items-center justify-center rounded-full bg-violet-600 px-1 text-[10px] leading-4 font-semibold text-white"
                                data-testid="cart-badge"
                                >{{ tab.badge }}</span
                            >
                        </span>
                        {{ tab.label }}
                    </Link>
                </li>
            </ul>
        </nav>

        <Toaster position="top-center" />
    </div>
</template>
