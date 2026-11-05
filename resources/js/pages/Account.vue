<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AppShell from '@/layouts/AppShell.vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const logout = useForm({});

const rows = [
    ['Name', props.user.name],
    ['Email', props.user.email],
    ['Role', props.user.role],
    ['Phone', props.user.phone],
    ...(props.user.shop_name ? [['Shop', props.user.shop_name]] : []),
];
</script>

<template>
    <Head title="Account" />
    <AppShell title="Account">
        <dl
            class="divide-y divide-violet-100 rounded-2xl bg-white ring-1 ring-violet-100"
        >
            <div
                v-for="[label, value] in rows"
                :key="label"
                class="flex justify-between gap-4 px-4 py-3 text-sm"
            >
                <dt class="text-violet-600">{{ label }}</dt>
                <dd class="text-right font-medium text-violet-950">
                    {{ value || '-' }}
                </dd>
            </div>
        </dl>
        <Button
            class="h-11 w-full rounded-xl bg-violet-600 hover:bg-violet-700"
            :disabled="logout.processing"
            @click="logout.post('/logout')"
        >
            {{ logout.processing ? 'Signing out...' : 'Log out' }}
        </Button>
    </AppShell>
</template>
