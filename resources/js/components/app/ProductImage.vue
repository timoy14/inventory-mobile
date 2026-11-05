<script setup>
import { ImageOff } from '@lucide/vue';
import { useAttrs } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

defineProps({
    url: String,
    alt: { type: String, required: true },
    iconClass: String,
});

// Sizing and shape come from the caller's class; the placeholder keeps its tint unless overridden.
const attrs = useAttrs();
</script>

<template>
    <img
        v-if="url"
        :src="url"
        :alt="alt"
        loading="lazy"
        :class="cn('object-cover', attrs.class)"
    />
    <div
        v-else
        :class="
            cn(
                'flex items-center justify-center bg-violet-50 text-violet-300',
                attrs.class,
            )
        "
    >
        <ImageOff :class="iconClass ?? 'size-6'" />
    </div>
</template>
