<script setup lang="ts">
import QrcodeVue from 'qrcode.vue';
import { computed, ref } from 'vue';

const props = defineProps<{
    employeeId: number;
    businessCardToken: string | null;
    businessCardEnabled: boolean;
}>();

const emit = defineEmits<{
    toggled: [enabled: boolean, token: string | null];
}>();

const toggling = ref(false);

const cardUrl = computed(() => {
    if (!props.businessCardToken) {
        return '';
    }
    return `${window.location.origin}/card/${props.businessCardToken}`;
});

async function toggleBusinessCard() {
    toggling.value = true;
    try {
        const response = await fetch(
            `/api/employees/${props.employeeId}/business-card/toggle`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie
                            .split('; ')
                            .find((row) => row.startsWith('XSRF-TOKEN='))
                            ?.split('=')[1] ?? '',
                    ),
                },
            },
        );
        if (response.ok) {
            const data = await response.json();
            emit('toggled', data.business_card_enabled, data.business_card_token);
        }
    } finally {
        toggling.value = false;
    }
}

function copyUrl() {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(cardUrl.value);
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = cardUrl.value;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    }
}
</script>

<template>
    <div
        class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-center justify-between">
            <h3
                class="text-sm font-semibold text-slate-900 dark:text-slate-100"
            >
                Digital Business Card
            </h3>
            <button
                @click="toggleBusinessCard"
                :disabled="toggling"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none disabled:opacity-50"
                :class="
                    businessCardEnabled ? 'bg-blue-600' : 'bg-slate-200 dark:bg-slate-600'
                "
                role="switch"
                :aria-checked="businessCardEnabled"
            >
                <span
                    class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    :class="
                        businessCardEnabled
                            ? 'translate-x-5'
                            : 'translate-x-0'
                    "
                />
            </button>
        </div>

        <template v-if="businessCardEnabled && businessCardToken">
            <div class="mt-4 flex justify-center">
                <QrcodeVue :value="cardUrl" :size="160" level="M" />
            </div>
            <div class="mt-3 flex items-center gap-2">
                <input
                    :value="cardUrl"
                    readonly
                    class="flex-1 rounded-md border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400"
                />
                <button
                    @click="copyUrl"
                    class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600"
                    title="Copy URL"
                >
                    Copy
                </button>
            </div>
        </template>

        <p
            v-else
            class="mt-2 text-xs text-slate-500 dark:text-slate-400"
        >
            Enable to generate a QR code business card for this employee.
        </p>
    </div>
</template>
