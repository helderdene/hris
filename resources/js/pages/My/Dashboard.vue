<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTenant } from '@/composables/useTenant';
import TenantLayout from '@/layouts/TenantLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    Banknote,
    Calendar,
    Clock,
    CreditCard,

    Megaphone,
    Pin,
    ScrollText,
} from 'lucide-vue-next';

interface LeaveBalance {
    id: number;
    leave_type: string | null;
    available: number;
    used: number;
}

interface RecentLeaveApplication {
    id: number;
    reference_number: string;
    leave_type: string | null;
    total_days: number;
    status: string;
    status_label: string;
    status_color: string;
    start_date: string | null;
}

interface Payslip {
    id: number;
    net_pay: number;
    period_name: string | null;
    period_start: string | null;
    period_end: string | null;
    status: string;
}

interface DtrRecord {
    id: number;
    first_in: string | null;
    last_out: string | null;
    status: string;
    total_work_hours: number | null;
}

interface Announcement {
    id: number;
    title: string;
    body: string;
    published_at: string | null;
    is_pinned: boolean;
}

interface EmployeeInfo {
    id: number;
    full_name: string;
}

interface DocumentRequestsSummary {
    pending_count: number;
}

interface LoansSummary {
    active_count: number;
    total_remaining_balance: number;
}

const props = defineProps<{
    employee: EmployeeInfo | null;
    leaveBalances: LeaveBalance[];
    recentLeaveApplications: RecentLeaveApplication[];
    recentPayslips: Payslip[];
    todayDtr: DtrRecord | null;
    announcements: Announcement[];
    documentRequestsSummary: DocumentRequestsSummary;
    loansSummary: LoansSummary;
}>();

const { tenantName } = useTenant();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'My Dashboard', href: '/my/dashboard' },
];

const totalLeaveCredits = props.leaveBalances.reduce(
    (sum, b) => sum + (b.available ?? 0),
    0,
);

const latestPayslip = props.recentPayslips[0] ?? null;

function formatCurrency(value: number | null): string {
    if (value == null) return '---';
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(value);
}

function badgeClasses(color: string): string {
    const map: Record<string, string> = {
        green: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400',
        red: 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400',
        amber: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
    };
    return map[color] ?? map.slate;
}
</script>

<template>
    <Head :title="`My Dashboard - ${tenantName}`" />

    <TenantLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6">
            <!-- Welcome -->
            <div v-if="employee">
                <h1
                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                >
                    Welcome, {{ employee.full_name }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Here's your overview for today.
                </p>
            </div>
            <div v-else>
                <h1
                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                >
                    My Dashboard
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    No employee profile linked to your account.
                </p>
            </div>

            <!-- Row 1: Payslips, DTR, Leave Balance -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Payslips Card -->
                <Link href="/my/payslips" class="block">
                    <Card
                        class="transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <CardHeader class="flex flex-row items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400"
                            >
                                <Banknote class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-base text-slate-900 dark:text-slate-100"
                                    >Payslips</CardTitle
                                >
                                <CardDescription>Latest payslip</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="latestPayslip">
                                <p
                                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ formatCurrency(latestPayslip.net_pay) }}
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        latestPayslip.period_start &&
                                        latestPayslip.period_end
                                            ? `${latestPayslip.period_start} - ${latestPayslip.period_end}`
                                            : latestPayslip.period_name ?? 'N/A'
                                    }}
                                </p>
                            </div>
                            <div v-else>
                                <p
                                    class="text-sm text-slate-400 dark:text-slate-500"
                                >
                                    No payslips available yet.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <!-- DTR Card -->
                <Link href="/my/dtr" class="block">
                    <Card
                        class="transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <CardHeader class="flex flex-row items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400"
                            >
                                <Clock class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-base text-slate-900 dark:text-slate-100"
                                    >Daily Time Record</CardTitle
                                >
                                <CardDescription>Today's attendance</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="todayDtr">
                                <div class="flex items-baseline gap-4">
                                    <div>
                                        <span
                                            class="text-xs text-slate-500 uppercase dark:text-slate-400"
                                            >In</span
                                        >
                                        <p
                                            class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ todayDtr.first_in ?? '---' }}
                                        </p>
                                    </div>
                                    <div>
                                        <span
                                            class="text-xs text-slate-500 uppercase dark:text-slate-400"
                                            >Out</span
                                        >
                                        <p
                                            class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ todayDtr.last_out ?? '---' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div v-else>
                                <p
                                    class="text-sm text-slate-400 dark:text-slate-500"
                                >
                                    No time record for today.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Leave Balance Card (compact) -->
                <Link href="/my/leave" class="block">
                    <Card
                        class="transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <CardHeader class="flex flex-row items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400"
                            >
                                <Calendar class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-base text-slate-900 dark:text-slate-100"
                                    >Leave Balance</CardTitle
                                >
                                <CardDescription>Available credits</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="leaveBalances.length > 0">
                                <p
                                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ totalLeaveCredits }}
                                    <span class="text-sm font-normal text-slate-500"
                                        >days available</span
                                    >
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ leaveBalances.length }} leave type{{ leaveBalances.length !== 1 ? 's' : '' }}
                                </p>
                            </div>
                            <div v-else>
                                <p
                                    class="text-sm text-slate-400 dark:text-slate-500"
                                >
                                    No leave balances found.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>

            <!-- Row 2: Document Requests, Loans, Announcements -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <!-- Document Requests Card -->
                <Link href="/my/document-requests" class="block">
                    <Card
                        class="transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <CardHeader class="flex flex-row items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-400"
                            >
                                <ScrollText class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-base text-slate-900 dark:text-slate-100"
                                    >Document Requests</CardTitle
                                >
                                <CardDescription>Request company documents</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="documentRequestsSummary.pending_count > 0">
                                <p
                                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ documentRequestsSummary.pending_count }}
                                    <span class="text-sm font-normal text-slate-500"
                                        >pending</span
                                    >
                                </p>
                            </div>
                            <div v-else>
                                <p
                                    class="text-sm text-slate-400 dark:text-slate-500"
                                >
                                    No pending requests.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Loans Card -->
                <Link href="/my/loans" class="block">
                    <Card
                        class="transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <CardHeader class="flex flex-row items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-400"
                            >
                                <CreditCard class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-base text-slate-900 dark:text-slate-100"
                                    >Loans</CardTitle
                                >
                                <CardDescription>Active loans overview</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="loansSummary.active_count > 0">
                                <p
                                    class="text-2xl font-bold text-slate-900 dark:text-slate-100"
                                >
                                    {{ loansSummary.active_count }}
                                    <span class="text-sm font-normal text-slate-500"
                                        >active</span
                                    >
                                </p>
                                <p
                                    class="mt-1 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ formatCurrency(loansSummary.total_remaining_balance) }} remaining
                                </p>
                            </div>
                            <div v-else>
                                <p
                                    class="text-sm text-slate-400 dark:text-slate-500"
                                >
                                    No active loans.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>

                <!-- Announcements Card -->
                <Link href="/my/announcements" class="block">
                    <Card
                        class="transition-shadow hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
                    >
                        <CardHeader class="flex flex-row items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400"
                            >
                                <Megaphone class="h-5 w-5" />
                            </div>
                            <div>
                                <CardTitle
                                    class="text-base text-slate-900 dark:text-slate-100"
                                    >Announcements</CardTitle
                                >
                                <CardDescription>Company updates</CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="announcements.length > 0">
                                <div class="flex items-start gap-2">
                                    <Pin
                                        v-if="announcements[0].is_pinned"
                                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-purple-500"
                                    />
                                    <p
                                        class="line-clamp-2 text-sm font-medium text-slate-900 dark:text-slate-100"
                                    >
                                        {{ announcements[0].title }}
                                    </p>
                                </div>
                                <p
                                    v-if="announcements.length > 1"
                                    class="mt-2 text-sm text-slate-500 dark:text-slate-400"
                                >
                                    +{{ announcements.length - 1 }} more
                                    announcement{{
                                        announcements.length - 1 !== 1
                                            ? 's'
                                            : ''
                                    }}
                                </p>
                            </div>
                            <div v-else>
                                <p
                                    class="text-sm text-slate-400 dark:text-slate-500"
                                >
                                    No announcements at this time.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </Link>
            </div>
        </div>
    </TenantLayout>
</template>
