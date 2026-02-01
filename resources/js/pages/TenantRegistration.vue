<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store as registerOrganizationRoute } from '@/routes/tenant/register';
import { Form, Head } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

interface Props {
    mainDomain: string;
}

const props = defineProps<Props>();

const slugInput = ref('');
const slugAvailability = ref<'idle' | 'checking' | 'available' | 'taken'>(
    'idle',
);

const subdomainPreview = computed(() => {
    if (!slugInput.value) {
        return `your-company.${props.mainDomain}`;
    }
    return `${slugInput.value}.${props.mainDomain}`;
});

const checkSlugAvailability = useDebounceFn(async (slug: string) => {
    if (!slug || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug)) {
        slugAvailability.value = 'idle';
        return;
    }

    slugAvailability.value = 'checking';

    try {
        const response = await fetch(
            `/api/check-slug/${encodeURIComponent(slug)}`,
        );
        const data = await response.json();
        slugAvailability.value = data.available ? 'available' : 'taken';
    } catch {
        slugAvailability.value = 'idle';
    }
}, 400);

watch(slugInput, (newValue) => {
    checkSlugAvailability(newValue);
});

function generateSlugFromName(name: string): string {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 63);
}

function handleNameChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const name = target.value;

    // Only auto-generate slug if user hasn't manually edited it
    if (
        !slugInput.value ||
        slugInput.value === generateSlugFromName(name.slice(0, -1))
    ) {
        slugInput.value = generateSlugFromName(name);
    }
}
</script>

<template>
    <AuthLayout
        title="Register Organization"
        description="Create a new organization and get your own subdomain"
    >
        <Head title="Register Organization" />

        <Form
            v-bind="registerOrganizationRoute.form()"
            #default="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <!-- Organization Name -->
                <div class="grid gap-2">
                    <Label for="name">Organization Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="organization"
                        name="name"
                        placeholder="Acme Corporation"
                        @input="handleNameChange"
                    />
                    <InputError :message="errors.name" />
                </div>

                <!-- Subdomain (Slug) -->
                <div class="grid gap-2">
                    <Label for="slug">Subdomain</Label>
                    <div class="relative">
                        <Input
                            id="slug"
                            type="text"
                            required
                            :tabindex="2"
                            autocomplete="off"
                            name="slug"
                            placeholder="acme-corp"
                            v-model="slugInput"
                            class="pr-10"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        />
                        <div
                            class="absolute inset-y-0 right-0 flex items-center pr-3"
                        >
                            <Spinner
                                v-if="slugAvailability === 'checking'"
                                class="size-4 text-slate-400"
                            />
                            <svg
                                v-else-if="slugAvailability === 'available'"
                                class="size-5 text-emerald-500"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M4.5 12.75l6 6 9-13.5"
                                />
                            </svg>
                            <svg
                                v-else-if="slugAvailability === 'taken'"
                                class="size-5 text-red-500"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </div>
                    </div>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Your subdomain:
                        <span
                            class="font-medium text-blue-600 dark:text-blue-400"
                            >{{ subdomainPreview }}</span
                        >
                    </p>
                    <p
                        v-if="slugAvailability === 'taken'"
                        class="text-sm text-red-600 dark:text-red-500"
                    >
                        This subdomain is already taken. Please choose another
                        one.
                    </p>
                    <InputError :message="errors.slug" />
                </div>

                <!-- Company Name -->
                <div class="grid gap-2">
                    <Label for="company_name">Company Name (Legal)</Label>
                    <Input
                        id="company_name"
                        type="text"
                        required
                        :tabindex="3"
                        autocomplete="organization"
                        name="business_info[company_name]"
                        placeholder="Acme Corporation Inc."
                    />
                    <InputError
                        :message="errors['business_info.company_name']"
                    />
                </div>

                <!-- Address (Optional) -->
                <div class="grid gap-2">
                    <Label for="address">
                        Company Address
                        <span class="text-slate-400 dark:text-slate-500"
                            >(optional)</span
                        >
                    </Label>
                    <Input
                        id="address"
                        type="text"
                        :tabindex="4"
                        autocomplete="street-address"
                        name="business_info[address]"
                        placeholder="123 Main Street, Manila, Philippines"
                    />
                    <InputError :message="errors['business_info.address']" />
                </div>

                <!-- TIN (Optional) -->
                <div class="grid gap-2">
                    <Label for="tin">
                        TIN (Tax Identification Number)
                        <span class="text-slate-400 dark:text-slate-500"
                            >(optional)</span
                        >
                    </Label>
                    <Input
                        id="tin"
                        type="text"
                        :tabindex="5"
                        autocomplete="off"
                        name="business_info[tin]"
                        placeholder="XXX-XXX-XXX-XXX"
                    />
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Philippine TIN format: 123-456-789-000
                    </p>
                    <InputError :message="errors['business_info.tin']" />
                </div>

                <Button
                    type="submit"
                    class="mt-2 w-full"
                    tabindex="6"
                    :disabled="
                        processing ||
                        slugAvailability === 'taken' ||
                        slugAvailability === 'checking'
                    "
                    data-test="register-organization-button"
                >
                    <Spinner v-if="processing" />
                    Create Organization
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an organization?
                <TextLink
                    href="/select-tenant"
                    class="underline underline-offset-4"
                    :tabindex="7"
                    >Select organization</TextLink
                >
            </div>
        </Form>
    </AuthLayout>
</template>
