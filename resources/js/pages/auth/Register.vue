<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import FieldError from '@/components/app/FieldError.vue';
import AuthCard from '@/components/AuthCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

defineProps({
    settings: { type: Object, required: true },
    flash_error: String,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'customer',
    phone: '',
    shop_name: '',
});

const roles = [
    { value: 'customer', label: 'I want to buy' },
    { value: 'seller', label: 'I want to sell' },
];

const submit = () =>
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Create account" />
    <AuthCard
        :store-name="settings.store_name"
        title="Create account"
        :flash-error="flash_error"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <fieldset class="grid grid-cols-2 gap-2">
                <legend class="sr-only">Account type</legend>
                <label
                    v-for="r in roles"
                    :key="r.value"
                    class="flex cursor-pointer items-center justify-center rounded-xl px-3 py-2.5 text-sm font-medium ring-1 ring-violet-200"
                    :class="
                        form.role === r.value
                            ? 'bg-violet-600 text-white ring-violet-600'
                            : 'bg-white text-violet-800'
                    "
                >
                    <input
                        v-model="form.role"
                        type="radio"
                        name="role"
                        :value="r.value"
                        class="sr-only"
                    />
                    {{ r.label }}
                </label>
            </fieldset>
            <FieldError :message="form.errors.role" />

            <div class="space-y-1.5">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="form.name"
                    autocomplete="name"
                    required
                />
                <FieldError :message="form.errors.name" />
            </div>
            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    inputmode="email"
                    required
                />
                <FieldError :message="form.errors.email" />
            </div>
            <div class="space-y-1.5">
                <Label for="phone">Phone (optional)</Label>
                <Input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    autocomplete="tel"
                    inputmode="tel"
                />
                <FieldError :message="form.errors.phone" />
            </div>
            <div v-if="form.role === 'seller'" class="space-y-1.5">
                <Label for="shop_name">Shop name</Label>
                <Input id="shop_name" v-model="form.shop_name" required />
                <FieldError :message="form.errors.shop_name" />
            </div>
            <div class="space-y-1.5">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <FieldError :message="form.errors.password" />
            </div>
            <div class="space-y-1.5">
                <Label for="password_confirmation">Confirm password</Label>
                <Input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
                <FieldError :message="form.errors.password_confirmation" />
            </div>
            <Button
                type="submit"
                class="h-11 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Creating account...' : 'Create account' }}
            </Button>
        </form>
        <template #footer>
            Already have an account?
            <a href="/login" class="font-medium underline">Sign in</a>
        </template>
    </AuthCard>
</template>
