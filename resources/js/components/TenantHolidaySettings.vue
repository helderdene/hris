<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTenant } from '@/composables/useTenant';
import { onMounted, ref } from 'vue';

interface PayrollSettings {
    pay_frequency: string;
    cutoff_day: number;
    double_holiday_rate: number;
}

const { isAdmin } = useTenant();

const settings = ref<PayrollSettings | null>(null);
const isLoading = ref(true);
const isSaving = ref(false);
const error = ref<string | null>(null);
const successMessage = ref<string | null>(null);
const validationError = ref<string | null>(null);

// Local form state
const doubleHolidayRate = ref<number>(300);

async function fetchSettings() {
    isLoading.value = true;
    error.value = null;

    try {
        const response = await fetch('/api/tenant/payroll-settings', {
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const data = await response.json();
            settings.value = data;
            doubleHolidayRate.value = data.double_holiday_rate ?? 300;
        } else {
            error.value = 'Failed to load settings';
        }
    } catch (err) {
        error.value = 'An error occurred while loading settings';
    } finally {
        isLoading.value = false;
    }
}

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function validateRate(rate: number): string | null {
    if (isNaN(rate) || !Number.isInteger(rate)) {
        return 'The double holiday rate must be a whole number.';
    }
    if (rate < 100) {
        return 'The double holiday rate must be at least 100%.';
    }
    if (rate > 500) {
        return 'The double holiday rate must not exceed 500%.';
    }
    return null;
}

async function saveSettings() {
    // Clear previous messages
    error.value = null;
    successMessage.value = null;
    validationError.value = null;

    // Validate
    const rateValue = Number(doubleHolidayRate.value);
    const validationResult = validateRate(rateValue);
    if (validationResult) {
        validationError.value = validationResult;
        return;
    }

    isSaving.value = true;

    try {
        const response = await fetch('/api/tenant/payroll-settings', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': getCsrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                double_holiday_rate: rateValue,
            }),
        });

        if (response.ok) {
            const data = await response.json();
            settings.value = data;
            doubleHolidayRate.value = data.double_holiday_rate;
            successMessage.value = 'Double holiday rate updated successfully.';

            // Auto-hide success message after 5 seconds
            setTimeout(() => {
                successMessage.value = null;
            }, 5000);
        } else {
            const errorData = await response.json();
            if (errorData.errors?.double_holiday_rate) {
                validationError.value = errorData.errors.double_holiday_rate[0];
            } else {
                error.value = errorData.message || 'Failed to update settings';
            }
        }
    } catch (err) {
        error.value = 'An error occurred while saving settings';
    } finally {
        isSaving.value = false;
    }
}

onMounted(() => {
    fetchSettings();
});
</script>

<template>
    <div class="space-y-6" data-test="tenant-holiday-settings">
        <HeadingSmall
            title="Holiday Pay Settings"
            description="Configure holiday pay rates for double holidays"
        />

        <!-- Loading State -->
        <div
            v-if="isLoading"
            class="flex items-center justify-center py-8"
            data-test="settings-loading"
        >
            <div
                class="flex items-center gap-3 text-slate-500 dark:text-slate-400"
            >
                <svg
                    class="h-5 w-5 animate-spin"
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="10"
                        stroke="currentColor"
                        stroke-width="4"
                    />
                    <path
                        class="opacity-75"
                        fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                </svg>
                <span>Loading settings...</span>
            </div>
        </div>

        <!-- Error State -->
        <Alert
            v-else-if="error"
            variant="destructive"
            data-test="settings-error"
        >
            <AlertDescription>{{ error }}</AlertDescription>
        </Alert>

        <!-- Settings Form -->
        <form v-else @submit.prevent="saveSettings" class="space-y-4">
            <!-- Success Message -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <Alert
                    v-if="successMessage"
                    class="border-green-200 bg-green-50 text-green-800 dark:border-green-800/50 dark:bg-green-900/30 dark:text-green-200"
                    data-test="settings-success"
                >
                    <AlertDescription>{{ successMessage }}</AlertDescription>
                </Alert>
            </Transition>

            <!-- Double Holiday Rate Input -->
            <div class="grid gap-2">
                <Label for="double_holiday_rate"
                    >Double Holiday Pay Rate (%)</Label
                >
                <div class="flex items-center gap-2">
                    <Input
                        id="double_holiday_rate"
                        v-model.number="doubleHolidayRate"
                        type="number"
                        min="100"
                        max="500"
                        step="1"
                        class="w-32"
                        :disabled="!isAdmin || isSaving"
                        data-test="double-holiday-rate-input"
                    />
                    <span class="text-sm text-slate-500 dark:text-slate-400"
                        >%</span
                    >
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    This rate applies to double holidays (e.g., New Year's Day
                    falling on a regular holiday). Default is 300%. Valid range:
                    100% - 500%.
                </p>
                <p
                    v-if="validationError"
                    class="text-sm text-red-600 dark:text-red-400"
                    data-test="validation-error"
                >
                    {{ validationError }}
                </p>
            </div>

            <!-- Current Rate Info -->
            <div
                v-if="settings"
                class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-800/50"
            >
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-medium">Current Rate:</span>
                    {{ settings.double_holiday_rate }}% of daily rate
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Employees will receive {{ settings.double_holiday_rate }}%
                    of their daily rate when working on double holidays.
                </p>
            </div>

            <!-- Save Button -->
            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    :disabled="!isAdmin || isSaving"
                    data-test="save-settings-button"
                >
                    <svg
                        v-if="isSaving"
                        class="mr-2 h-4 w-4 animate-spin"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        />
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                    </svg>
                    {{ isSaving ? 'Saving...' : 'Save Settings' }}
                </Button>

                <p
                    v-if="!isAdmin"
                    class="text-sm text-slate-500 dark:text-slate-400"
                >
                    Only administrators can modify these settings.
                </p>
            </div>
        </form>
    </div>
</template>
