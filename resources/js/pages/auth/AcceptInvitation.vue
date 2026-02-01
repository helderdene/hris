<script setup lang="ts">
import { accept } from '@/actions/App/Http/Controllers/InvitationController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Form, Head } from '@inertiajs/vue3';

defineProps<{
    user: {
        name: string;
        email: string;
    };
    tenant: {
        name: string;
    };
    token: string;
}>();
</script>

<template>
    <AuthLayout
        title="Accept Invitation"
        :description="`Welcome ${user.name}! Set your password to join ${tenant.name}.`"
    >
        <Head title="Accept Invitation" />

        <Form
            :action="accept.url(token)"
            method="post"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        :model-value="user.email"
                        class="mt-1 block w-full"
                        readonly
                        disabled
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        autofocus
                        placeholder="Enter your password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm Password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        class="mt-1 block w-full"
                        placeholder="Confirm your password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    type="submit"
                    class="mt-4 w-full"
                    :disabled="processing"
                    data-test="accept-invitation-button"
                >
                    <Spinner v-if="processing" />
                    Set Password & Join
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
