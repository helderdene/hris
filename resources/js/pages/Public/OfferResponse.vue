<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import OfferPreview from '@/Components/Recruitment/OfferPreview.vue';
import OfferStatusBadge from '@/Components/Recruitment/OfferStatusBadge.vue';
import SignaturePad from '@/Components/Recruitment/SignaturePad.vue';
import { Head, router } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface OfferData {
    id: number;
    content: string;
    status: string;
    status_label: string;
    salary: string;
    salary_currency: string;
    salary_frequency: string;
    benefits: string[] | null;
    start_date: string | null;
    expiry_date: string | null;
    position_title: string;
    department: string | null;
    work_location: string | null;
    employment_type: string | null;
    candidate_name: string;
    candidate_email: string;
    company_name: string;
}

const props = defineProps<{
    offer: OfferData;
}>();

const showAcceptForm = ref(false);
const showDeclineForm = ref(false);
const processing = ref(false);

const signerName = ref(props.offer.candidate_name);
const signerEmail = ref(props.offer.candidate_email);
const signatureData = ref('');
const declineReason = ref('');

const errors = ref<Record<string, string>>({});

const isTerminal = ['accepted', 'declined', 'expired', 'revoked'].includes(props.offer.status);

function acceptOffer(): void {
    processing.value = true;
    errors.value = {};

    router.post(`/api/offers/${props.offer.id}/accept`, {
        signer_name: signerName.value,
        signer_email: signerEmail.value,
        signature_data: signatureData.value,
    }, {
        onError: (errs) => {
            errors.value = errs;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

function declineOffer(): void {
    processing.value = true;
    router.post(`/api/offers/${props.offer.id}/decline`, {
        reason: declineReason.value || null,
    }, {
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`Offer - ${offer.position_title}`" />

    <div class="min-h-screen bg-slate-50 dark:bg-slate-950">
        <div class="mx-auto max-w-3xl px-4 py-8">
            <!-- Header -->
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                    Job Offer
                </h1>
                <p class="mt-2 text-lg text-slate-600 dark:text-slate-400">
                    {{ offer.position_title }}
                </p>
            </div>

            <!-- Terminal State Banner -->
            <div
                v-if="isTerminal"
                class="mb-6 rounded-lg border p-4 text-center"
                :class="{
                    'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-950': offer.status === 'accepted',
                    'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950': offer.status === 'declined',
                    'border-orange-200 bg-orange-50 dark:border-orange-800 dark:bg-orange-950': offer.status === 'expired',
                    'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950': offer.status === 'revoked',
                }"
            >
                <p class="font-medium">
                    This offer has been <span class="lowercase">{{ offer.status_label }}</span>.
                </p>
            </div>

            <!-- Offer Content -->
            <Card class="mb-6">
                <CardContent class="p-6">
                    <OfferPreview :content="offer.content" />
                </CardContent>
            </Card>

            <!-- Compensation Summary -->
            <Card class="mb-6">
                <CardHeader>
                    <CardTitle>Compensation Summary</CardTitle>
                </CardHeader>
                <CardContent>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="font-medium text-muted-foreground">Salary</dt>
                            <dd class="mt-1 text-lg font-semibold">
                                {{ offer.salary_currency }}
                                {{ Number(offer.salary).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
                                <span class="text-sm font-normal text-muted-foreground">({{ offer.salary_frequency }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-muted-foreground">Start Date</dt>
                            <dd class="mt-1">{{ offer.start_date ?? 'TBD' }}</dd>
                        </div>
                        <div v-if="offer.department">
                            <dt class="font-medium text-muted-foreground">Department</dt>
                            <dd class="mt-1">{{ offer.department }}</dd>
                        </div>
                        <div v-if="offer.work_location">
                            <dt class="font-medium text-muted-foreground">Location</dt>
                            <dd class="mt-1">{{ offer.work_location }}</dd>
                        </div>
                    </dl>

                    <div v-if="offer.benefits && offer.benefits.length" class="mt-4">
                        <dt class="text-sm font-medium text-muted-foreground">Benefits</dt>
                        <ul class="mt-1 list-inside list-disc text-sm">
                            <li v-for="b in offer.benefits" :key="b">{{ b }}</li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <!-- Actions (only if not terminal) -->
            <div v-if="!isTerminal" class="space-y-4">
                <!-- Action Buttons -->
                <div v-if="!showAcceptForm && !showDeclineForm" class="flex justify-center gap-4">
                    <Button size="lg" class="gap-2" @click="showAcceptForm = true">
                        <Check class="h-5 w-5" />
                        Accept Offer
                    </Button>
                    <Button size="lg" variant="outline" class="gap-2" @click="showDeclineForm = true">
                        <X class="h-5 w-5" />
                        Decline Offer
                    </Button>
                </div>

                <!-- Accept Form -->
                <Card v-if="showAcceptForm">
                    <CardHeader>
                        <CardTitle>Accept Offer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="acceptOffer" class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <Label for="signer_name">Full Name</Label>
                                    <Input id="signer_name" v-model="signerName" />
                                    <p v-if="errors.signer_name" class="text-sm text-destructive">{{ errors.signer_name }}</p>
                                </div>
                                <div class="space-y-2">
                                    <Label for="signer_email">Email</Label>
                                    <Input id="signer_email" v-model="signerEmail" type="email" />
                                    <p v-if="errors.signer_email" class="text-sm text-destructive">{{ errors.signer_email }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>Signature</Label>
                                <SignaturePad v-model="signatureData" />
                                <p v-if="errors.signature_data" class="text-sm text-destructive">{{ errors.signature_data }}</p>
                            </div>

                            <div class="flex justify-end gap-3">
                                <Button type="button" variant="outline" @click="showAcceptForm = false">
                                    Cancel
                                </Button>
                                <Button type="submit" :disabled="processing || !signatureData">
                                    {{ processing ? 'Submitting...' : 'Confirm Acceptance' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Decline Form -->
                <Card v-if="showDeclineForm">
                    <CardHeader>
                        <CardTitle>Decline Offer</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="declineOffer" class="space-y-4">
                            <div class="space-y-2">
                                <Label for="decline_reason">Reason (optional)</Label>
                                <Textarea
                                    id="decline_reason"
                                    v-model="declineReason"
                                    rows="3"
                                    placeholder="Please share your reason for declining..."
                                />
                            </div>

                            <div class="flex justify-end gap-3">
                                <Button type="button" variant="outline" @click="showDeclineForm = false">
                                    Cancel
                                </Button>
                                <Button type="submit" variant="destructive" :disabled="processing">
                                    {{ processing ? 'Submitting...' : 'Confirm Decline' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <!-- Expiry Notice -->
            <p v-if="offer.expiry_date && !isTerminal" class="mt-6 text-center text-sm text-muted-foreground">
                This offer expires on {{ offer.expiry_date }}. Please respond before the deadline.
            </p>
        </div>
    </div>
</template>
