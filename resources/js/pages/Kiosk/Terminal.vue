<script setup lang="ts">
import KioskLayout from '@/layouts/KioskLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

defineOptions({ layout: KioskLayout });

const props = defineProps<{
    kiosk: {
        name: string;
        location: string | null;
        token: string;
        cooldown_minutes: number;
    };
    companyName?: string;
    companyLogo?: string;
}>();

type KioskState = 'idle' | 'verifying' | 'confirmed' | 'clocking' | 'success' | 'error';

const state = ref<KioskState>('idle');
const pin = ref('');
const errorMessage = ref('');
const successMessage = ref('');
const currentTime = ref('');
const currentDate = ref('');

const employee = ref<{
    id: number;
    name: string;
    employee_number: string;
    position: string | null;
    department: string | null;
} | null>(null);

const lastPunch = ref<{
    direction: string;
    logged_at: string;
    logged_at_human: string;
} | null>(null);

const suggestedDirection = ref<'in' | 'out'>('in');
const clockResult = ref<{ logged_at_human: string; direction: string } | null>(null);

let timeInterval: ReturnType<typeof setInterval>;
let idleTimeout: ReturnType<typeof setTimeout>;

const maskedPin = computed(() => '*'.repeat(pin.value.length));

function updateTime(): void {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true,
    });
    currentDate.value = now.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function resetToIdle(): void {
    state.value = 'idle';
    pin.value = '';
    employee.value = null;
    lastPunch.value = null;
    suggestedDirection.value = 'in';
    clockResult.value = null;
    errorMessage.value = '';
    successMessage.value = '';
    clearIdleTimeout();
}

function startIdleTimeout(seconds: number = 30): void {
    clearIdleTimeout();
    idleTimeout = setTimeout(resetToIdle, seconds * 1000);
}

function clearIdleTimeout(): void {
    if (idleTimeout) {
        clearTimeout(idleTimeout);
    }
}

function appendDigit(digit: string): void {
    if (pin.value.length < 6) {
        pin.value += digit;
        errorMessage.value = '';
    }
}

function deleteDigit(): void {
    pin.value = pin.value.slice(0, -1);
}

function clearPin(): void {
    pin.value = '';
    errorMessage.value = '';
}

async function submitPin(): Promise<void> {
    if (pin.value.length < 4) {
        errorMessage.value = 'PIN must be at least 4 digits.';
        return;
    }

    state.value = 'verifying';
    errorMessage.value = '';

    try {
        const response = await axios.post(`/kiosk/${props.kiosk.token}/verify-pin`, {
            pin: pin.value,
        });

        employee.value = response.data.employee;
        lastPunch.value = response.data.last_punch;
        suggestedDirection.value = response.data.suggested_direction;
        state.value = 'confirmed';
        pin.value = '';
        startIdleTimeout(30);
    } catch (error: any) {
        state.value = 'idle';
        pin.value = '';
        if (error.response?.status === 429) {
            errorMessage.value = 'Too many attempts. Please wait.';
        } else if (error.response?.status === 422) {
            errorMessage.value = error.response.data.message || 'Invalid PIN.';
        } else {
            errorMessage.value = 'An error occurred. Please try again.';
        }
    }
}

async function clockAction(direction: 'in' | 'out'): Promise<void> {
    if (!employee.value) {
        return;
    }

    state.value = 'clocking';
    clearIdleTimeout();

    try {
        const response = await axios.post(`/kiosk/${props.kiosk.token}/clock`, {
            employee_id: employee.value.id,
            direction,
        });

        clockResult.value = {
            logged_at_human: response.data.logged_at_human,
            direction: response.data.direction,
        };
        successMessage.value = response.data.message;
        state.value = 'success';

        // Auto-reset after 5 seconds
        setTimeout(resetToIdle, 5000);
    } catch (error: any) {
        errorMessage.value = error.response?.data?.message || 'Failed to record clock event.';
        state.value = 'confirmed';
        startIdleTimeout(30);
    }
}

onMounted(() => {
    updateTime();
    timeInterval = setInterval(updateTime, 1000);
});

onUnmounted(() => {
    clearInterval(timeInterval);
    clearIdleTimeout();
});
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center p-4 selection:bg-blue-500/20">
        <!-- Header -->
        <div class="mb-8 text-center">
            <img v-if="companyLogo" :src="companyLogo" :alt="companyName" class="mx-auto mb-3 h-12 object-contain" />
            <p v-if="companyName" class="mb-2 text-sm font-medium tracking-widest text-slate-400 uppercase">{{ companyName }}</p>
            <h1 class="text-4xl font-bold text-white">{{ kiosk.name }}</h1>
            <p v-if="kiosk.location" class="mt-1 text-lg text-slate-400">{{ kiosk.location }}</p>
            <p class="mt-4 text-5xl font-light tabular-nums text-blue-400">{{ currentTime }}</p>
            <p class="mt-1 text-lg text-slate-500">{{ currentDate }}</p>
        </div>

        <!-- Idle / PIN Entry State -->
        <div v-if="state === 'idle' || state === 'verifying'" class="w-full max-w-sm">
            <div class="rounded-2xl border border-slate-700 bg-slate-800 p-8 shadow-2xl">
                <p class="mb-6 text-center text-lg font-medium text-slate-300">
                    Enter your PIN
                </p>

                <!-- PIN Display -->
                <div class="mb-6 flex items-center justify-center rounded-xl border border-slate-600 bg-slate-900 px-4 py-4">
                    <span class="text-3xl tracking-[0.5em] text-white">
                        {{ maskedPin || '&nbsp;' }}
                    </span>
                </div>

                <!-- Error -->
                <p v-if="errorMessage" class="mb-4 text-center text-sm text-red-400">
                    {{ errorMessage }}
                </p>

                <!-- Numpad -->
                <div class="grid grid-cols-3 gap-3">
                    <button
                        v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                        :key="digit"
                        :disabled="state === 'verifying'"
                        class="flex h-16 items-center justify-center rounded-xl bg-slate-700 text-2xl font-semibold text-white transition-colors hover:bg-slate-600 active:bg-slate-500 disabled:opacity-50"
                        @click="appendDigit(digit)"
                    >
                        {{ digit }}
                    </button>
                    <button
                        :disabled="state === 'verifying'"
                        class="flex h-16 items-center justify-center rounded-xl bg-slate-700 text-sm font-medium text-slate-400 transition-colors hover:bg-slate-600 active:bg-slate-500 disabled:opacity-50"
                        @click="clearPin"
                    >
                        Clear
                    </button>
                    <button
                        :disabled="state === 'verifying'"
                        class="flex h-16 items-center justify-center rounded-xl bg-slate-700 text-2xl font-semibold text-white transition-colors hover:bg-slate-600 active:bg-slate-500 disabled:opacity-50"
                        @click="appendDigit('0')"
                    >
                        0
                    </button>
                    <button
                        :disabled="state === 'verifying'"
                        class="flex h-16 items-center justify-center rounded-xl bg-slate-700 text-xl text-slate-400 transition-colors hover:bg-slate-600 active:bg-slate-500 disabled:opacity-50"
                        @click="deleteDigit"
                    >
                        &#9003;
                    </button>
                </div>

                <!-- Submit -->
                <button
                    :disabled="pin.length < 4 || state === 'verifying'"
                    class="mt-4 flex h-14 w-full items-center justify-center rounded-xl bg-blue-600 text-lg font-semibold text-white transition-colors hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-40"
                    @click="submitPin"
                >
                    <template v-if="state === 'verifying'">
                        <svg class="mr-2 h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        Verifying...
                    </template>
                    <template v-else>Enter</template>
                </button>
            </div>
        </div>

        <!-- Confirmed State — Show employee + Clock In/Out -->
        <div v-if="state === 'confirmed' || state === 'clocking'" class="w-full max-w-md">
            <div class="rounded-2xl border border-slate-700 bg-slate-800 p-8 shadow-2xl">
                <!-- Employee Info -->
                <div class="mb-6 text-center">
                    <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-blue-600 text-3xl font-bold text-white">
                        {{ employee?.name?.charAt(0) ?? '?' }}
                    </div>
                    <h2 class="text-2xl font-bold text-white">{{ employee?.name }}</h2>
                    <p class="text-slate-400">{{ employee?.employee_number }}</p>
                    <p v-if="employee?.position || employee?.department" class="mt-1 text-sm text-slate-500">
                        {{ [employee?.position, employee?.department].filter(Boolean).join(' - ') }}
                    </p>
                </div>

                <!-- Last Punch Info -->
                <div v-if="lastPunch" class="mb-6 rounded-lg border border-slate-600 bg-slate-900 px-4 py-3 text-center">
                    <p class="text-sm text-slate-400">Last punch</p>
                    <p class="text-lg font-medium text-white">
                        Clock {{ lastPunch.direction === 'in' ? 'In' : 'Out' }}
                        <span class="text-slate-400">{{ lastPunch.logged_at_human }}</span>
                    </p>
                </div>

                <!-- Error -->
                <p v-if="errorMessage" class="mb-4 text-center text-sm text-red-400">
                    {{ errorMessage }}
                </p>

                <!-- Clock Buttons -->
                <div class="grid grid-cols-2 gap-4">
                    <button
                        :disabled="state === 'clocking'"
                        :class="[
                            'flex h-20 flex-col items-center justify-center rounded-xl text-lg font-semibold transition-colors disabled:opacity-50',
                            suggestedDirection === 'in'
                                ? 'bg-green-600 text-white ring-2 ring-green-400 hover:bg-green-500'
                                : 'bg-slate-700 text-slate-300 hover:bg-slate-600',
                        ]"
                        @click="clockAction('in')"
                    >
                        <span class="text-2xl">&#8599;</span>
                        Clock In
                    </button>
                    <button
                        :disabled="state === 'clocking'"
                        :class="[
                            'flex h-20 flex-col items-center justify-center rounded-xl text-lg font-semibold transition-colors disabled:opacity-50',
                            suggestedDirection === 'out'
                                ? 'bg-red-600 text-white ring-2 ring-red-400 hover:bg-red-500'
                                : 'bg-slate-700 text-slate-300 hover:bg-slate-600',
                        ]"
                        @click="clockAction('out')"
                    >
                        <span class="text-2xl">&#8601;</span>
                        Clock Out
                    </button>
                </div>

                <!-- Cancel -->
                <button
                    :disabled="state === 'clocking'"
                    class="mt-4 w-full rounded-lg py-3 text-sm text-slate-400 transition-colors hover:text-white disabled:opacity-50"
                    @click="resetToIdle"
                >
                    Cancel
                </button>
            </div>
        </div>

        <!-- Success State -->
        <div v-if="state === 'success'" class="w-full max-w-md">
            <div class="rounded-2xl border border-slate-700 bg-slate-800 p-8 shadow-2xl">
                <div class="text-center">
                    <div
                        :class="[
                            'mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full text-4xl',
                            clockResult?.direction === 'in' ? 'bg-green-600' : 'bg-red-600',
                        ]"
                    >
                        &#10003;
                    </div>
                    <h2 class="text-2xl font-bold text-white">{{ successMessage }}</h2>
                    <p class="mt-2 text-lg text-slate-400">{{ clockResult?.logged_at_human }}</p>
                    <p class="mt-1 text-xl font-semibold text-white">{{ employee?.name }}</p>
                    <p class="mt-8 text-sm text-slate-500">Returning to home screen...</p>
                </div>
            </div>
        </div>
    </div>
</template>
