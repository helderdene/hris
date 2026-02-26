<script setup lang="ts">
import { useTenant } from '@/composables/useTenant';
import { Head } from '@inertiajs/vue3';

interface BusinessCardEmployee {
    full_name: string;
    initials: string;
    profile_photo_url: string | null;
    position: string | null;
    department: string | null;
    email: string | null;
    phone: string | null;
    token: string;
}

const props = defineProps<{
    employee: BusinessCardEmployee;
}>();

const { tenantName, logoUrl, primaryColor } = useTenant();

const vcardUrl = `/card/${props.employee.token}/vcard`;
</script>

<template>
    <Head :title="`${employee.full_name} - ${tenantName}`" />

    <div
        class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 p-4 dark:from-slate-950 dark:to-slate-900"
    >
        <div
            class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-slate-800"
        >
            <!-- Header with gradient -->
            <div
                class="relative h-32"
                :style="{
                    background: `linear-gradient(135deg, ${primaryColor}, ${primaryColor}dd)`,
                }"
            >
                <!-- Company branding -->
                <div class="absolute top-4 left-4 flex items-center gap-2">
                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="tenantName"
                        class="h-8 rounded bg-white/20 object-contain"
                    />
                    <span class="text-sm font-medium text-white/90">{{
                        tenantName
                    }}</span>
                </div>
            </div>

            <!-- Profile photo / avatar -->
            <div class="relative -mt-16 flex justify-center">
                <div
                    class="rounded-full border-4 border-white shadow-lg dark:border-slate-800"
                >
                    <img
                        v-if="employee.profile_photo_url"
                        :src="employee.profile_photo_url"
                        :alt="employee.full_name"
                        class="h-28 w-28 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-28 w-28 items-center justify-center rounded-full bg-slate-200 text-3xl font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                    >
                        {{ employee.initials }}
                    </div>
                </div>
            </div>

            <!-- Employee info -->
            <div class="px-6 pt-4 pb-2 text-center">
                <h1
                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                >
                    {{ employee.full_name }}
                </h1>
                <p
                    v-if="employee.position"
                    class="mt-1 text-sm font-medium text-slate-600 dark:text-slate-400"
                >
                    {{ employee.position }}
                </p>
                <p
                    v-if="employee.department"
                    class="text-sm text-slate-500 dark:text-slate-500"
                >
                    {{ employee.department }}
                </p>
            </div>

            <!-- Contact details -->
            <div class="space-y-3 px-6 pt-4 pb-2">
                <a
                    v-if="employee.email"
                    :href="`mailto:${employee.email}`"
                    class="flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:bg-slate-700/50 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"
                        />
                    </svg>
                    <span class="truncate">{{ employee.email }}</span>
                </a>

                <a
                    v-if="employee.phone"
                    :href="`tel:${employee.phone}`"
                    class="flex items-center gap-3 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700 transition-colors hover:bg-slate-100 dark:bg-slate-700/50 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    <svg
                        class="h-5 w-5 shrink-0 text-slate-400"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"
                        />
                    </svg>
                    <span>{{ employee.phone }}</span>
                </a>
            </div>

            <!-- Save Contact button -->
            <div class="px-6 pt-4 pb-6">
                <a
                    :href="vcardUrl"
                    class="flex w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white transition-opacity hover:opacity-90"
                    :style="{ backgroundColor: primaryColor }"
                >
                    <svg
                        class="h-5 w-5"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
                        />
                    </svg>
                    Save Contact
                </a>
            </div>
        </div>
    </div>
</template>
