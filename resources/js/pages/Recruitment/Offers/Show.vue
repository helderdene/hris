<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import OfferPreview from '@/components/Recruitment/OfferPreview.vue';
import OfferStatusBadge from '@/components/Recruitment/OfferStatusBadge.vue';
import OfferStatusTimeline from '@/components/Recruitment/OfferStatusTimeline.vue';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Download, Send, XCircle } from 'lucide-vue-next';
import { ref } from 'vue';

interface Signature {
    id: number;
    signer_type: string;
    signer_name: string;
    signer_email: string;
    signed_at: string | null;
}

interface OfferData {
    id: number;
    job_application: {
        id: number;
        candidate: { id: number; full_name: string; email: string };
        job_posting: { id: number; title: string };
    };
    offer_template: { id: number; name: string } | null;
    content: string;
    status: string;
    status_label: string;
    status_color: string;
    allowed_transitions: { value: string; label: string; color: string }[];
    salary: string;
    salary_currency: string;
    salary_frequency: string;
    benefits: string[] | null;
    terms: string | null;
    start_date: string | null;
    expiry_date: string | null;
    position_title: string;
    department: string | null;
    work_location: string | null;
    employment_type: string | null;
    pdf_path: string | null;
    decline_reason: string | null;
    revoke_reason: string | null;
    signatures: Signature[];
    sent_at: string | null;
    viewed_at: string | null;
    accepted_at: string | null;
    declined_at: string | null;
    expired_at: string | null;
    revoked_at: string | null;
    created_at: string | null;
}

const props = defineProps<{
    offer: OfferData;
    statuses: { value: string; label: string; color: string }[];
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Offers', href: '/recruitment/offers' },
    { title: props.offer.position_title, href: '#' },
];

const processing = ref(false);

function sendOffer(): void {
    if (!confirm('Send this offer to the candidate?')) return;
    processing.value = true;
    router.post(`/api/offers/${props.offer.id}/send`, {}, {
        onFinish: () => { processing.value = false; },
    });
}

function revokeOffer(): void {
    const reason = prompt('Reason for revoking (optional):');
    if (reason === null) return;
    processing.value = true;
    router.post(`/api/offers/${props.offer.id}/revoke`, { reason }, {
        onFinish: () => { processing.value = false; },
    });
}

function downloadPdf(): void {
    window.open(`/api/offers/${props.offer.id}/pdf`, '_blank');
}
</script>

<template>
    <Head :title="`Offer - ${offer.position_title} - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto max-w-6xl">
            <!-- Header -->
            <div class="mb-6 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ offer.position_title }}
                        </h1>
                        <OfferStatusBadge :status="offer.status" :label="offer.status_label" :color="offer.status_color" />
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ offer.job_application.candidate.full_name }} —
                        {{ offer.job_application.job_posting.title }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        v-if="offer.allowed_transitions.some((t) => t.value === 'sent')"
                        class="gap-2"
                        :disabled="processing"
                        @click="sendOffer"
                    >
                        <Send class="h-4 w-4" />
                        Send Offer
                    </Button>
                    <Button
                        v-if="offer.allowed_transitions.some((t) => t.value === 'revoked')"
                        variant="destructive"
                        class="gap-2"
                        :disabled="processing"
                        @click="revokeOffer"
                    >
                        <XCircle class="h-4 w-4" />
                        Revoke
                    </Button>
                    <Button v-if="offer.pdf_path || offer.status === 'accepted'" variant="outline" class="gap-2" @click="downloadPdf">
                        <Download class="h-4 w-4" />
                        Download PDF
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Main Content -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Offer Content -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Offer Letter</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <OfferPreview :content="offer.content" />
                        </CardContent>
                    </Card>

                    <!-- Compensation -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Compensation Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <dt class="font-medium text-muted-foreground">Salary</dt>
                                    <dd class="mt-1">
                                        {{ offer.salary_currency }}
                                        {{ Number(offer.salary).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                        ({{ offer.salary_frequency }})
                                    </dd>
                                </div>
                                <div>
                                    <dt class="font-medium text-muted-foreground">Start Date</dt>
                                    <dd class="mt-1">{{ offer.start_date ?? '-' }}</dd>
                                </div>
                                <div v-if="offer.department">
                                    <dt class="font-medium text-muted-foreground">Department</dt>
                                    <dd class="mt-1">{{ offer.department }}</dd>
                                </div>
                                <div v-if="offer.work_location">
                                    <dt class="font-medium text-muted-foreground">Work Location</dt>
                                    <dd class="mt-1">{{ offer.work_location }}</dd>
                                </div>
                                <div v-if="offer.employment_type">
                                    <dt class="font-medium text-muted-foreground">Employment Type</dt>
                                    <dd class="mt-1 capitalize">{{ offer.employment_type.replace('_', ' ') }}</dd>
                                </div>
                                <div v-if="offer.expiry_date">
                                    <dt class="font-medium text-muted-foreground">Offer Expires</dt>
                                    <dd class="mt-1">{{ offer.expiry_date }}</dd>
                                </div>
                            </dl>

                            <div v-if="offer.benefits && offer.benefits.length" class="mt-4">
                                <dt class="text-sm font-medium text-muted-foreground">Benefits</dt>
                                <ul class="mt-1 list-inside list-disc text-sm">
                                    <li v-for="benefit in offer.benefits" :key="benefit">{{ benefit }}</li>
                                </ul>
                            </div>

                            <div v-if="offer.terms" class="mt-4">
                                <dt class="text-sm font-medium text-muted-foreground">Additional Terms</dt>
                                <dd class="mt-1 text-sm">{{ offer.terms }}</dd>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Signatures -->
                    <Card v-if="offer.signatures.length">
                        <CardHeader>
                            <CardTitle>Signatures</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-3">
                                <div
                                    v-for="sig in offer.signatures"
                                    :key="sig.id"
                                    class="flex items-center justify-between rounded-md border p-3"
                                >
                                    <div>
                                        <p class="text-sm font-medium">{{ sig.signer_name }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ sig.signer_type.replace('_', ' ') }} — {{ sig.signer_email }}
                                        </p>
                                    </div>
                                    <span class="text-xs text-muted-foreground">
                                        Signed {{ sig.signed_at }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Decline/Revoke Reasons -->
                    <Card v-if="offer.decline_reason || offer.revoke_reason" class="border-destructive/50">
                        <CardContent class="p-4">
                            <p v-if="offer.decline_reason" class="text-sm">
                                <strong>Decline Reason:</strong> {{ offer.decline_reason }}
                            </p>
                            <p v-if="offer.revoke_reason" class="text-sm">
                                <strong>Revoke Reason:</strong> {{ offer.revoke_reason }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <OfferStatusTimeline :offer="offer" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Candidate</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm">
                            <p class="font-medium">{{ offer.job_application.candidate.full_name }}</p>
                            <p class="text-muted-foreground">{{ offer.job_application.candidate.email }}</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </TenantLayout>
</template>
