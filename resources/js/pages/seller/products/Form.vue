<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { Camera, ImagePlus, Trash2, Undo2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import FieldError from '@/components/app/FieldError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppShell from '@/layouts/AppShell.vue';

const MAX_PHOTOS = 5;

const props = defineProps({
    product: { type: [Object, null], required: true },
    categories: { type: Array, required: true },
    native: { type: Boolean, required: true },
    picked_photos: { type: Array, required: true },
});

const editing = computed(() => props.product !== null);

const form = useForm({
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    category_id: props.product?.category_id
        ? String(props.product.category_id)
        : '',
    sku: props.product?.sku ?? '',
    price: props.product?.price ?? '',
    stock: props.product ? String(props.product.stock) : '',
    status: props.product?.status ?? 'active',
    remove_images: [],
});

const previews = ref([]);

const kept = computed(
    () =>
        (props.product?.images ?? []).filter(
            (i) => !form.remove_images.includes(i.path),
        ).length,
);
const total = computed(
    () => kept.value + previews.value.length + props.picked_photos.length,
);
const remaining = computed(() => Math.max(0, MAX_PHOTOS - total.value));

const toggleRemove = (path) => {
    form.remove_images = form.remove_images.includes(path)
        ? form.remove_images.filter((p) => p !== path)
        : [...form.remove_images, path];
};

const onFiles = (event) => {
    const input = event.target;
    const chosen = Array.from(input.files ?? []).slice(0, remaining.value);
    for (const file of chosen) {
        previews.value.push({ file, url: URL.createObjectURL(file) });
    }
    input.value = '';
};

const removeNew = (index) => {
    URL.revokeObjectURL(previews.value[index].url);
    previews.value.splice(index, 1);
};

onBeforeUnmount(() =>
    previews.value.forEach((p) => URL.revokeObjectURL(p.url)),
);

// Native picker: the result arrives asynchronously as an event on the PHP side,
// so poll the pending list for a short while after opening the gallery or camera.
let poll = null;
const stopPolling = () => {
    if (poll) clearInterval(poll);
    poll = null;
};
const pick = (source) => {
    router.post(
        '/seller/photos/pick',
        { source },
        { preserveScroll: true, preserveState: true, only: ['picked_photos'] },
    );
    stopPolling();
    const before = props.picked_photos.length;
    let ticks = 0;
    // Partial reload of picked_photos only: the server makes no API calls for it.
    poll = setInterval(() => {
        if (++ticks > 40) return stopPolling();
        router.reload({
            only: ['picked_photos'],
            onSuccess: () => {
                if (props.picked_photos.length > before) stopPolling();
            },
        });
    }, 1500);
};
onBeforeUnmount(stopPolling);

const discardPicked = (index) =>
    router.post(
        '/seller/photos/discard',
        { index },
        { preserveScroll: true, preserveState: true, only: ['picked_photos'] },
    );

// API errors for images.0, images.1 ... belong under the photo grid.
const photoErrors = computed(() =>
    Object.entries(form.errors)
        .filter(
            ([key]) =>
                key.startsWith('images') || key.startsWith('remove_images'),
        )
        .map(([, message]) => message),
);

const submit = () => {
    if (form.processing) return;
    // Files live in `previews`; they are attached to the payload only when submitting.
    form.transform((data) => ({
        ...data,
        images: previews.value.map((p) => p.file),
    }));
    if (props.product) {
        form.put(`/seller/products/${props.product.id}`, {
            forceFormData: true,
        });
    } else {
        form.post('/seller/products', { forceFormData: true });
    }
};

const confirmingDelete = ref(false);
const deleting = ref(false);
const destroy = () => {
    if (!props.product || deleting.value) return;
    router.delete(`/seller/products/${props.product.id}`, {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmingDelete.value = false;
        },
    });
};

const field =
    'block w-full rounded-xl border border-violet-200 bg-white px-3 py-2.5 text-base text-violet-950 outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-200';
</script>

<template>
    <Head :title="editing ? 'Edit product' : 'New product'" />
    <AppShell
        :title="editing ? 'Edit product' : 'New product'"
        back="/seller/products"
        hide-tabs
    >
        <form
            class="space-y-4 pb-24"
            enctype="multipart/form-data"
            @submit.prevent="submit"
        >
            <div class="space-y-1.5">
                <Label for="name">Name</Label>
                <Input id="name" v-model="form.name" required />
                <FieldError :message="form.errors.name" />
            </div>

            <div class="space-y-1.5">
                <Label for="description">Description (optional)</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="4"
                    :class="field"
                />
                <FieldError :message="form.errors.description" />
            </div>

            <div class="space-y-1.5">
                <Label for="category">Category (optional)</Label>
                <select id="category" v-model="form.category_id" :class="field">
                    <option value="">No category</option>
                    <option
                        v-for="c in categories"
                        :key="c.id"
                        :value="String(c.id)"
                    >
                        {{ c.name }}
                    </option>
                </select>
                <FieldError :message="form.errors.category_id" />
            </div>

            <div class="space-y-1.5">
                <Label for="sku">SKU</Label>
                <Input id="sku" v-model="form.sku" required />
                <FieldError :message="form.errors.sku" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1.5">
                    <Label for="price">Price</Label>
                    <Input
                        id="price"
                        v-model="form.price"
                        inputmode="decimal"
                        required
                    />
                    <FieldError :message="form.errors.price" />
                </div>
                <div class="space-y-1.5">
                    <Label for="stock">Stock</Label>
                    <Input
                        id="stock"
                        v-model="form.stock"
                        inputmode="numeric"
                        required
                    />
                    <FieldError :message="form.errors.stock" />
                </div>
            </div>

            <fieldset class="space-y-1.5">
                <legend class="text-sm font-medium">Status</legend>
                <div class="grid grid-cols-2 gap-2">
                    <label
                        v-for="s in ['active', 'inactive']"
                        :key="s"
                        class="flex cursor-pointer items-center justify-center rounded-xl px-3 py-2.5 text-sm font-medium capitalize ring-1 ring-violet-200"
                        :class="
                            form.status === s
                                ? 'bg-violet-600 text-white ring-violet-600'
                                : 'bg-white text-violet-800'
                        "
                    >
                        <input
                            v-model="form.status"
                            type="radio"
                            name="status"
                            :value="s"
                            class="sr-only"
                        />
                        {{ s }}
                    </label>
                </div>
                <FieldError :message="form.errors.status" />
            </fieldset>

            <section class="space-y-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium">Photos</h2>
                    <span class="text-xs text-violet-500"
                        >{{ total }} / {{ MAX_PHOTOS }}</span
                    >
                </div>
                <ul class="grid grid-cols-3 gap-2">
                    <li
                        v-for="image in product?.images ?? []"
                        :key="image.path"
                        class="relative aspect-square overflow-hidden rounded-xl ring-1 ring-violet-100"
                    >
                        <img
                            :src="image.url"
                            alt=""
                            class="size-full object-cover"
                            :class="
                                form.remove_images.includes(image.path)
                                    ? 'opacity-30'
                                    : ''
                            "
                        />
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex size-8 items-center justify-center rounded-full bg-white/90 text-violet-700 shadow"
                            :aria-label="
                                form.remove_images.includes(image.path)
                                    ? 'Keep photo'
                                    : 'Remove photo'
                            "
                            @click="toggleRemove(image.path)"
                        >
                            <Undo2
                                v-if="form.remove_images.includes(image.path)"
                                class="size-4"
                            />
                            <Trash2 v-else class="size-4 text-red-600" />
                        </button>
                    </li>
                    <li
                        v-for="(preview, i) in previews"
                        :key="preview.url"
                        class="relative aspect-square overflow-hidden rounded-xl ring-2 ring-violet-300"
                    >
                        <img
                            :src="preview.url"
                            alt=""
                            class="size-full object-cover"
                        />
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex size-8 items-center justify-center rounded-full bg-white/90 shadow"
                            aria-label="Remove new photo"
                            @click="removeNew(i)"
                        >
                            <X class="size-4" />
                        </button>
                    </li>
                    <li
                        v-for="(photo, i) in picked_photos"
                        :key="`picked-${i}-${photo.name}`"
                        class="relative flex aspect-square flex-col items-center justify-center gap-1 rounded-xl bg-violet-100 p-1 text-center text-[10px] text-violet-700 ring-2 ring-violet-300"
                    >
                        <ImagePlus class="size-5" />
                        <span class="line-clamp-2 break-all">{{
                            photo.name
                        }}</span>
                        <span v-if="!photo.usable" class="text-red-600"
                            >Too large or not an image</span
                        >
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex size-7 items-center justify-center rounded-full bg-white/90 shadow"
                            aria-label="Remove picked photo"
                            @click="discardPicked(i)"
                        >
                            <X class="size-4" />
                        </button>
                    </li>
                </ul>

                <div v-if="remaining > 0" class="flex flex-wrap gap-2">
                    <template v-if="native">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 flex-1 rounded-xl border-violet-200 text-violet-700"
                            @click="pick('gallery')"
                        >
                            <ImagePlus class="size-4" /> Gallery
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 flex-1 rounded-xl border-violet-200 text-violet-700"
                            @click="pick('camera')"
                        >
                            <Camera class="size-4" /> Camera
                        </Button>
                    </template>
                    <label
                        v-else
                        class="flex h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-violet-200 text-sm font-medium text-violet-700"
                    >
                        <ImagePlus class="size-4" /> Add photos
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            capture="environment"
                            class="sr-only"
                            @change="onFiles"
                        />
                    </label>
                </div>
                <p v-else class="text-xs text-violet-500">
                    Photo limit reached. Remove one to add another.
                </p>
                <FieldError
                    v-for="message in photoErrors"
                    :key="message"
                    :message="message"
                />
            </section>

            <Button
                v-if="editing"
                type="button"
                variant="outline"
                class="h-12 w-full rounded-xl border-red-200 text-red-600"
                @click="confirmingDelete = true"
            >
                <Trash2 class="size-4" /> Delete product
            </Button>

            <div
                class="fixed inset-x-0 bottom-0 z-30 border-t border-violet-100 bg-white px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
            >
                <Button
                    type="submit"
                    class="mx-auto block h-12 w-full max-w-lg rounded-xl bg-violet-600 hover:bg-violet-700"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving...' : 'Save' }}
                </Button>
            </div>
        </form>

        <Dialog v-model:open="confirmingDelete">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete this product?</DialogTitle>
                    <DialogDescription>
                        {{ product?.name }} will be removed from the store.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="confirmingDelete = false">
                        Keep product
                    </Button>
                    <Button
                        class="bg-red-600 hover:bg-red-700"
                        :disabled="deleting"
                        @click="destroy"
                    >
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppShell>
</template>
