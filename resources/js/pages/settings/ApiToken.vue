<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import TenantLayout from '@/layouts/TenantLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
    hasToken: boolean;
    lastUsedAt: string | null;
}

defineProps<Props>();

const page = usePage();
const newToken = computed(() => page.props.flash?.newToken as string | undefined);
const copied = ref(false);
const generating = ref(false);
const revoking = ref(false);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'API Token',
        href: '/settings/api-token',
    },
];

function generateToken() {
    generating.value = true;
    router.post('/settings/api-token', {}, {
        preserveScroll: true,
        onFinish: () => {
            generating.value = false;
        },
    });
}

function revokeToken() {
    revoking.value = true;
    router.delete('/settings/api-token', {
        preserveScroll: true,
        onFinish: () => {
            revoking.value = false;
        },
    });
}

function copyToken() {
    if (newToken.value) {
        navigator.clipboard.writeText(newToken.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    }
}

function formatDate(dateString: string | null): string {
    if (!dateString) {
        return 'Never';
    }
    return new Date(dateString).toLocaleString();
}
</script>

<template>
    <TenantLayout :breadcrumbs="breadcrumbItems">
        <Head title="API Token" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="External API Token"
                    description="Manage the API token used by external systems (e.g., ERP) to access employee data."
                />

                <Alert v-if="newToken" variant="default" class="border-green-500 bg-green-50 dark:bg-green-950/20">
                    <AlertTitle class="text-green-800 dark:text-green-200">Token Generated</AlertTitle>
                    <AlertDescription class="space-y-3">
                        <p class="text-sm text-green-700 dark:text-green-300">
                            Copy this token now. It will not be shown again.
                        </p>
                        <div class="flex items-center gap-2">
                            <Input
                                :model-value="newToken"
                                readonly
                                class="font-mono text-xs"
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                @click="copyToken"
                            >
                                {{ copied ? 'Copied!' : 'Copy' }}
                            </Button>
                        </div>
                    </AlertDescription>
                </Alert>

                <div class="space-y-4">
                    <div v-if="hasToken && !newToken" class="rounded-lg border p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium">Active Token</p>
                                <p class="text-muted-foreground text-xs">
                                    Last used: {{ formatDate(lastUsedAt) }}
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                Active
                            </span>
                        </div>
                    </div>

                    <div v-if="!hasToken && !newToken" class="rounded-lg border border-dashed p-4">
                        <p class="text-muted-foreground text-sm">
                            No API token has been generated yet.
                        </p>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button
                            @click="generateToken"
                            :disabled="generating"
                        >
                            {{ hasToken ? 'Regenerate Token' : 'Generate Token' }}
                        </Button>

                        <Button
                            v-if="hasToken"
                            variant="destructive"
                            @click="revokeToken"
                            :disabled="revoking"
                        >
                            Revoke Token
                        </Button>
                    </div>
                </div>

                <div class="rounded-lg border p-4 space-y-2">
                    <p class="text-sm font-medium">Usage</p>
                    <p class="text-muted-foreground text-xs">
                        Use this token in the <code class="rounded bg-muted px-1 py-0.5">Authorization</code> header when making requests to the external API:
                    </p>
                    <pre class="rounded bg-muted p-3 text-xs overflow-x-auto"><code>GET /external-api/v1/{slug}/employees
Authorization: Bearer {your-token}
Accept: application/json</code></pre>
                </div>
            </div>
        </SettingsLayout>
    </TenantLayout>
</template>
