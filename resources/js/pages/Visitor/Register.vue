<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Form } from '@inertiajs/vue3';
import { Head, usePage } from '@inertiajs/vue3';
import { CheckCircle, Clock, Loader2, MapPin, Search, UserRoundCheck, XCircle } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';

interface LocationOption {
    id: number;
    name: string;
}

interface EmployeeResult {
    id: number;
    name: string;
}

const props = defineProps<{
    locations: LocationOption[];
    companyName: string | null;
    companyLogo: string | null;
}>();

const page = usePage();
const successMessage = computed(() => page.props.flash?.success as string | undefined);

const mounted = ref(false);
onMounted(() => {
    setTimeout(() => {
        mounted.value = true;
    }, 50);
});

// Host employee search
const hostSearch = ref('');
const hostEmployeeId = ref<number | null>(null);
const hostEmployeeName = ref('');
const searchResults = ref<EmployeeResult[]>([]);
const searching = ref(false);
const showDropdown = ref(false);
const hostVerified = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

async function searchEmployees(query: string): Promise<void> {
    searching.value = true;
    try {
        const url = `/visit/search-employees?q=${encodeURIComponent(query)}`;
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) {
            searchResults.value = [];
            return;
        }
        const data = await response.json();
        searchResults.value = data;
        showDropdown.value = searchResults.value.length > 0;
    } catch {
        searchResults.value = [];
    } finally {
        searching.value = false;
    }
}

watch(hostSearch, (val) => {
    if (hostVerified.value) {
        hostEmployeeId.value = null;
        hostEmployeeName.value = '';
        hostVerified.value = false;
    }

    if (searchTimeout) clearTimeout(searchTimeout);

    if (val.length < 2) {
        searchResults.value = [];
        showDropdown.value = false;
        return;
    }

    searchTimeout = setTimeout(() => {
        searchEmployees(val);
    }, 300);
});

function hideDropdown() {
    setTimeout(() => {
        showDropdown.value = false;
    }, 200);
}

function selectEmployee(employee: EmployeeResult) {
    hostEmployeeId.value = employee.id;
    hostEmployeeName.value = employee.name;
    hostSearch.value = employee.name;
    hostVerified.value = true;
    showDropdown.value = false;
    searchResults.value = [];
}

// Input class matching the design system Input component
const inputClass =
    'placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const textareaClass =
    'placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input min-h-[80px] w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';

const selectClass =
    'placeholder:text-muted-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm';
</script>

<template>
    <Head :title="`Visit Registration${companyName ? ` - ${companyName}` : ''}`" />

    <div
        class="visitor-register relative flex min-h-svh flex-col items-center justify-center bg-slate-50 p-6 md:p-10 dark:bg-slate-900"
    >
        <!-- Ambient Background -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden">
            <div
                class="absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-blue-500 opacity-[0.03] blur-3xl transition-all duration-1000 dark:opacity-[0.05]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />
            <div
                class="absolute -right-32 -bottom-32 h-[400px] w-[400px] rounded-full bg-emerald-500 opacity-[0.02] blur-3xl transition-all delay-200 duration-1000 dark:opacity-[0.04]"
                :class="mounted ? 'scale-100' : 'scale-50'"
            />

            <svg
                class="absolute inset-0 h-full w-full opacity-[0.015] dark:opacity-[0.03]"
                xmlns="http://www.w3.org/2000/svg"
            >
                <defs>
                    <pattern
                        id="visitor-grid"
                        width="40"
                        height="40"
                        patternUnits="userSpaceOnUse"
                    >
                        <path
                            d="M 40 0 L 0 0 0 40"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="0.5"
                        />
                    </pattern>
                </defs>
                <rect
                    width="100%"
                    height="100%"
                    fill="url(#visitor-grid)"
                    class="text-slate-900 dark:text-slate-100"
                />
            </svg>
        </div>

        <!-- Content -->
        <div
            class="relative z-10 w-full max-w-lg transition-all duration-500"
            :class="mounted ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
        >
            <div class="flex flex-col gap-8">
                <!-- Logo & Header -->
                <div class="flex flex-col items-center gap-6">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-600 shadow-lg shadow-blue-500/20"
                        >
                            <UserRoundCheck class="h-6 w-6 text-white" />
                        </div>
                        <span
                            class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100"
                        >
                            {{ companyName ?? 'KasamaHR' }}
                        </span>
                    </div>

                    <div class="space-y-2 text-center">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
                            Visitor Registration
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Fill in your details to register your visit
                        </p>
                    </div>
                </div>

                <!-- Success State -->
                <div
                    v-if="successMessage"
                    class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-900/5 dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/20"
                >
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-xl bg-green-600 shadow-lg shadow-green-500/20">
                        <CheckCircle class="h-7 w-7 text-white" />
                    </div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        Registration Submitted
                    </h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ successMessage }}
                    </p>
                </div>

                <!-- Registration Form Card -->
                <div
                    v-else
                    class="rounded-2xl border border-slate-200 bg-white p-8 shadow-xl shadow-slate-900/5 dark:border-slate-700 dark:bg-slate-800 dark:shadow-black/20"
                >
                    <Form
                        action=""
                        method="post"
                        #default="{ errors, processing }"
                    >
                        <div class="space-y-5">
                            <!-- Personal Information Section -->
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    <UserRoundCheck class="h-3.5 w-3.5" />
                                    Personal Information
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="first_name">First Name</Label>
                                        <input
                                            id="first_name"
                                            name="first_name"
                                            required
                                            placeholder="Juan"
                                            :class="inputClass"
                                        />
                                        <p v-if="errors.first_name" class="text-sm text-red-600 dark:text-red-400">{{ errors.first_name }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="last_name">Last Name</Label>
                                        <input
                                            id="last_name"
                                            name="last_name"
                                            required
                                            placeholder="Dela Cruz"
                                            :class="inputClass"
                                        />
                                        <p v-if="errors.last_name" class="text-sm text-red-600 dark:text-red-400">{{ errors.last_name }}</p>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="email">Email</Label>
                                    <input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                        placeholder="juan@example.com"
                                        :class="inputClass"
                                    />
                                    <p v-if="errors.email" class="text-sm text-red-600 dark:text-red-400">{{ errors.email }}</p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <Label for="phone">Phone</Label>
                                        <input
                                            id="phone"
                                            name="phone"
                                            placeholder="09171234567"
                                            :class="inputClass"
                                        />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="company">Company</Label>
                                        <input
                                            id="company"
                                            name="company"
                                            placeholder="Company name"
                                            :class="inputClass"
                                        />
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="border-t border-slate-100 dark:border-slate-700/50" />

                            <!-- Visit Details Section -->
                            <div class="space-y-4">
                                <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                    <MapPin class="h-3.5 w-3.5" />
                                    Visit Details
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="work_location_id">Location</Label>
                                    <select
                                        id="work_location_id"
                                        name="work_location_id"
                                        required
                                        :class="selectClass"
                                    >
                                        <option value="">Select location...</option>
                                        <option v-for="loc in locations" :key="loc.id" :value="loc.id">
                                            {{ loc.name }}
                                        </option>
                                    </select>
                                    <p v-if="errors.work_location_id" class="text-sm text-red-600 dark:text-red-400">{{ errors.work_location_id }}</p>
                                </div>

                                <!-- Host Employee Search -->
                                <div class="space-y-1.5">
                                    <Label for="host_search">Person You're Visiting</Label>
                                    <div class="relative">
                                        <input type="hidden" name="host_employee_id" :value="hostEmployeeId ?? ''" />
                                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                            <Search v-if="!searching && !hostVerified" class="h-4 w-4 text-slate-400" />
                                            <Loader2 v-else-if="searching" class="h-4 w-4 animate-spin text-slate-400" />
                                            <CheckCircle v-else class="h-4 w-4 text-green-500" />
                                        </div>
                                        <input
                                            id="host_search"
                                            v-model="hostSearch"
                                            type="text"
                                            required
                                            placeholder="Search by employee name..."
                                            autocomplete="off"
                                            class="pl-9 pr-3"
                                            :class="[
                                                inputClass,
                                                hostVerified
                                                    ? 'border-green-400 dark:border-green-600'
                                                    : '',
                                            ]"
                                            @focus="showDropdown = searchResults.length > 0"
                                            @blur="hideDropdown"
                                        />

                                        <!-- Dropdown results -->
                                        <div
                                            v-if="showDropdown"
                                            class="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-600 dark:bg-slate-800"
                                        >
                                            <button
                                                v-for="emp in searchResults"
                                                :key="emp.id"
                                                type="button"
                                                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-slate-50 dark:hover:bg-slate-700"
                                                @mousedown.prevent="selectEmployee(emp)"
                                            >
                                                <UserRoundCheck class="h-4 w-4 shrink-0 text-slate-400" />
                                                <span class="text-slate-900 dark:text-slate-100">{{ emp.name }}</span>
                                            </button>
                                        </div>
                                    </div>
                                    <Transition
                                        enter-active-class="transition duration-200 ease-out"
                                        enter-from-class="opacity-0 -translate-y-1"
                                        enter-to-class="opacity-100 translate-y-0"
                                    >
                                        <p v-if="hostVerified" class="flex items-center gap-1 text-sm text-green-600 dark:text-green-400">
                                            <CheckCircle class="h-3.5 w-3.5" />
                                            {{ hostEmployeeName }} found
                                        </p>
                                    </Transition>
                                    <p v-if="hostSearch.length >= 2 && !searching && searchResults.length === 0 && !hostVerified" class="flex items-center gap-1 text-sm text-amber-600 dark:text-amber-400">
                                        <XCircle class="h-3.5 w-3.5" />
                                        No employee found with that name
                                    </p>
                                    <p v-if="errors.host_employee_id" class="text-sm text-red-600 dark:text-red-400">{{ errors.host_employee_id }}</p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="purpose">Purpose of Visit</Label>
                                    <textarea
                                        id="purpose"
                                        name="purpose"
                                        required
                                        rows="3"
                                        placeholder="Describe the reason for your visit..."
                                        :class="textareaClass"
                                    ></textarea>
                                    <p v-if="errors.purpose" class="text-sm text-red-600 dark:text-red-400">{{ errors.purpose }}</p>
                                </div>

                                <div class="space-y-1.5">
                                    <Label for="expected_at">Expected Date/Time</Label>
                                    <div class="relative">
                                        <input
                                            id="expected_at"
                                            name="expected_at"
                                            type="datetime-local"
                                            required
                                            :class="inputClass"
                                        />
                                    </div>
                                    <p v-if="errors.expected_at" class="text-sm text-red-600 dark:text-red-400">{{ errors.expected_at }}</p>
                                </div>
                            </div>

                            <Button type="submit" class="w-full" :disabled="processing || !hostVerified">
                                <Loader2 v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                                {{ processing ? 'Submitting...' : 'Submit Registration' }}
                            </Button>
                        </div>
                    </Form>
                </div>

                <!-- Footer -->
                <p class="text-center text-xs text-slate-400 dark:text-slate-500">
                    Your information will be shared with the host for visit coordination.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.visitor-register {
    font-family:
        'DM Sans',
        system-ui,
        -apple-system,
        sans-serif;
}
</style>
