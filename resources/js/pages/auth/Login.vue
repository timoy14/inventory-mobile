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

const form = useForm({ email: '', password: '' });

const submit = () =>
    form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Sign in" />
    <AuthCard
        :store-name="settings.store_name"
        title="Sign in"
        :flash-error="flash_error"
    >
        <form class="space-y-4" @submit.prevent="submit">
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
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                />
                <FieldError :message="form.errors.password" />
            </div>
            <Button
                type="submit"
                class="h-11 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
                :disabled="form.processing"
            >
                {{ form.processing ? 'Signing in...' : 'Sign in' }}
            </Button>
        </form>
        <template #footer>
            New here?
            <a href="/register" class="font-medium underline"
                >Create an account</a
            >
        </template>
    </AuthCard>
</template>
