<script setup lang="ts">
import { index as selectTenant } from '@/actions/App/Http/Controllers/TenantSelectorController';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { useSubscription } from '@/composables/useSubscription';
import { useTenant } from '@/composables/useTenant';
import { Link, usePage } from '@inertiajs/vue3';
import {
    Award,
    BarChart3,
    BookOpen,
    Briefcase,
    Building2,
    Calculator,
    Calendar,
    CalendarDays,
    CalendarRange,
    ChevronDown,
    FolderOpen,
    GraduationCap,
    ChevronLeft,
    ChevronsUpDown,
    ClipboardCheck,
    Clock,
    ClipboardList,
    Contact,
    CreditCard,
    HelpCircle,
    History,
    DollarSign,
    FileBarChart,
    FileText,
    Fingerprint,
    Flag,
    Monitor,
    UserRoundCheck,
    GitBranch,
    LayoutGrid,
    MapPin,
    Megaphone,
    Network,
    PieChart,
    Scale,
    ScrollText,
    Settings,
    ShieldCheck,
    SlidersHorizontal,
    Star,
    Target,
    User,
    UserCheck,
    UserPlus,
    Users,
    UsersRound,
    CalendarCheck,
    CalendarClock,
    Lock,
} from 'lucide-vue-next';
import { computed, ref, watch, type Component, onMounted } from 'vue';

interface NavItem {
    title: string;
    href: string;
    icon: Component;
    disabled?: boolean;
}

const { tenantName } = useTenant();
const { hasModule } = useSubscription();
const page = usePage();
const { toggleSidebar, state } = useSidebar();

// Check if user can manage users (Admin only)
const canManageUsers = computed(
    () => page.props.tenant?.can_manage_users ?? false,
);

// Check if user can manage organization (Admin or HR Manager)
const canManageOrganization = computed(
    () => page.props.tenant?.can_manage_organization ?? false,
);

// Check if user can manage employees (Admin or HR Manager - for Employee Dashboard access)
const canManageEmployees = computed(
    () => page.props.tenant?.can_manage_employees ?? false,
);

// Check if user is an Employee-only role (hide admin sections)
const isEmployeeRole = computed(
    () => page.props.tenant?.user_role === 'employee',
);

// HR Management items
const hrManagementItems = computed(() => {
    const items: NavItem[] = [];

    if (canManageEmployees.value) {
        items.push({
            title: 'Employee Dashboard',
            href: '/employees/dashboard',
            icon: BarChart3,
        });
        items.push({
            title: 'HR Analytics',
            href: '/hr/analytics',
            icon: PieChart,
        });
    }

    items.push({ title: 'Employees', href: '/employees', icon: Users });

    // Company Documents is accessible to all users
    items.push({
        title: 'Company Documents',
        href: '/company-documents',
        icon: FileText,
    });

    // Organization structure items (Admin/HR Manager only)
    if (canManageOrganization.value) {
        items.push(
            { title: 'Announcements', href: '/announcements', icon: Megaphone },
            { title: 'Departments', href: '/organization/departments', icon: GitBranch },
            { title: 'Positions', href: '/organization/positions', icon: Briefcase },
            { title: 'Locations', href: '/organization/locations', icon: MapPin },
            { title: 'Org Chart', href: '/organization/org-chart', icon: Network },
            { title: 'Certification Types', href: '/organization/certification-types', icon: Award },
            { title: 'Certifications', href: '/hr/certifications', icon: Award },
            { title: 'Document Requests', href: '/hr/document-requests', icon: ScrollText },
            { title: 'Loan Approvals', href: '/loan-approvals', icon: ClipboardCheck },
            { title: 'Probationary Evaluations', href: '/hr/probationary-evaluations', icon: UserCheck },
        );
    }

    return items;
});

// Time & Attendance items
const timeAttendanceItems = computed(() => {
    const items: NavItem[] = [
        { title: 'Attendance', href: '/attendance', icon: Clock },
        { title: 'Daily Time Record', href: '/time-attendance/dtr', icon: ClipboardList },
        { title: 'OT Requests', href: '/overtime/requests', icon: CalendarClock },
    ];

    // Add OT Approvals for managers/admins
    if (canManageEmployees.value) {
        items.push({ title: 'OT Approvals', href: '/overtime/approvals', icon: ClipboardCheck });
    }

    // Configuration items (Admin/HR Manager only)
    if (canManageOrganization.value) {
        items.push(
            { title: 'Work Schedules', href: '/organization/work-schedules', icon: Clock },
            { title: 'Holidays', href: '/organization/holidays', icon: CalendarDays },
            { title: 'Biometric Devices', href: '/organization/devices', icon: Fingerprint },
            { title: 'Kiosks', href: '/organization/kiosks', icon: Monitor },
        );
    }

    return items;
});

// Visitor Management items
const visitorManagementItems = computed(() => {
    const items: NavItem[] = [
        { title: 'Visitors', href: '/visitors', icon: UserRoundCheck },
        { title: 'Visitor Log', href: '/visitors/log', icon: History },
    ];

    return items;
});

// Leave Management items
const leaveManagementItems = computed(() => {
    const items: NavItem[] = [
        { title: 'My Applications', href: '/leave/applications', icon: Calendar },
        { title: 'Calendar', href: '/leave/calendar', icon: CalendarDays },
    ];

    // Add Leave Approvals for managers/admins
    if (canManageEmployees.value) {
        items.push({ title: 'Approvals', href: '/leave/approvals', icon: ClipboardCheck });
    }

    // Configuration items (Admin/HR Manager only)
    if (canManageOrganization.value) {
        items.push(
            { title: 'Leave Types', href: '/organization/leave-types', icon: Calendar },
            { title: 'Leave Balances', href: '/organization/leave-balances', icon: Scale },
        );
    }

    return items;
});

// Recruitment items
const recruitmentItems = computed(() => {
    const items: NavItem[] = [];

    // Analytics visible to Admin/HR Manager/Supervisor
    if (canManageEmployees.value) {
        items.push({ title: 'Analytics', href: '/recruitment/analytics', icon: PieChart });
    }

    items.push(
        { title: 'Job Requisitions', href: '/recruitment/requisitions', icon: UserPlus },
        { title: 'Job Postings', href: '/recruitment/job-postings', icon: Briefcase },
        { title: 'Candidates', href: '/recruitment/candidates', icon: Contact },
        { title: 'Interviews', href: '/recruitment/interviews', icon: CalendarCheck },
        { title: 'Offers', href: '/recruitment/offers', icon: FileText },
        { title: 'Offer Templates', href: '/recruitment/offer-templates', icon: ScrollText },
        { title: 'Pre-boarding', href: '/preboarding', icon: ClipboardList },
        { title: 'Pre-boarding Templates', href: '/preboarding-templates', icon: ScrollText },
        { title: 'Onboarding', href: '/onboarding', icon: ClipboardCheck },
        { title: 'Onboarding Tasks', href: '/onboarding-tasks', icon: ClipboardList },
        { title: 'Onboarding Templates', href: '/onboarding-templates', icon: ScrollText },
    );

    if (canManageEmployees.value) {
        items.push({ title: 'Approvals', href: '/recruitment/approvals', icon: ClipboardCheck });
    }

    return items;
});

// Training items (Admin/HR only)
const trainingItems = computed(() => {
    const items: NavItem[] = [
        { title: 'Courses', href: '/training/courses', icon: GraduationCap },
        { title: 'Sessions', href: '/training/sessions', icon: CalendarCheck },
        { title: 'Calendar', href: '/training/calendar', icon: CalendarDays },
        { title: 'History', href: '/training/history', icon: Clock },
        { title: 'Categories', href: '/training/categories', icon: FolderOpen },
    ];

    // Add Training Approvals for managers/admins
    if (canManageEmployees.value) {
        items.push({ title: 'Approvals', href: '/training/approvals', icon: ClipboardCheck });
    }

    return items;
});

// Compliance Training items (Admin/HR only)
const complianceItems: NavItem[] = [
    { title: 'Dashboard', href: '/compliance', icon: BarChart3 },
    { title: 'Courses', href: '/compliance/courses', icon: ShieldCheck },
    { title: 'Assignments', href: '/compliance/assignments', icon: ClipboardList },
    { title: 'Rules', href: '/compliance/rules', icon: Target },
    { title: 'Reports', href: '/compliance/reports', icon: FileBarChart },
];

// Performance items
const performanceItems = computed(() => {
    const items: NavItem[] = [];

    // Analytics visible to Admin/HR Manager/Supervisor
    if (canManageEmployees.value) {
        items.push({ title: 'Analytics', href: '/performance/analytics', icon: PieChart });
    }

    items.push(
        { title: 'Performance Cycles', href: '/organization/performance-cycles', icon: BarChart3 },
        { title: 'Goals', href: '/performance/goals', icon: Flag },
        { title: 'KPIs', href: '/performance/kpis', icon: Target },
        { title: '360 Evaluations', href: '/performance/evaluations', icon: Star },
        { title: 'Competencies', href: '/organization/competencies', icon: ClipboardList },
        { title: 'Competency Matrix', href: '/organization/competency-matrix', icon: LayoutGrid },
        { title: 'Competency Evaluations', href: '/performance/competency-evaluations', icon: ClipboardCheck },
        { title: 'Development Plans', href: '/performance/development-plans', icon: BookOpen },
    );

    return items;
});

// Payroll items
const payrollItems: NavItem[] = [
    { title: 'Payroll Periods', href: '/organization/payroll-periods', icon: CalendarRange },
    { title: 'Adjustments', href: '/payroll/adjustments', icon: SlidersHorizontal },
    { title: 'Loans', href: '/payroll/loans', icon: DollarSign },
    { title: 'Salary Grades', href: '/organization/salary-grades', icon: DollarSign },
    { title: 'Contributions', href: '/organization/contributions/sss', icon: Calculator },
];

// Compliance Reports items
const reportsItems: NavItem[] = [
    { title: 'SSS Reports', href: '/reports/sss', icon: FileBarChart },
    { title: 'PhilHealth Reports', href: '/reports/philhealth', icon: FileBarChart },
    { title: 'Pag-IBIG Reports', href: '/reports/pagibig', icon: FileBarChart },
    { title: 'BIR Reports', href: '/reports/bir', icon: FileBarChart },
];


// Check if user can approve leaves (Admin, HR Manager, or Supervisor)
const canApproveLeaves = computed(
    () => page.props.tenant?.can_approve_leaves ?? canManageEmployees.value,
);

// Self-Service items
const selfServiceItems = computed(() => {
    const items: NavItem[] = [
        {
            title: 'My Dashboard',
            href: '/my/dashboard',
            icon: LayoutGrid,
        },
        {
            title: 'My Goals',
            href: '/my/goals',
            icon: Flag,
        },
        {
            title: 'My Evaluations',
            href: '/my/evaluations',
            icon: Star,
        },
        {
            title: 'Development Plans',
            href: '/my/development-plans',
            icon: BookOpen,
        },
        {
            title: 'Training Sessions',
            href: '/my/training/sessions',
            icon: GraduationCap,
        },
        {
            title: 'My Enrollments',
            href: '/my/training/enrollments',
            icon: CalendarCheck,
        },
        {
            title: 'My Certifications',
            href: '/my/certifications',
            icon: Award,
        },
        {
            title: 'My Compliance',
            href: '/my/compliance',
            icon: ShieldCheck,
        },
        {
            title: 'Compliance Certificates',
            href: '/my/compliance/certificates',
            icon: Award,
        },
        {
            title: 'My DTR',
            href: '/my/dtr',
            icon: Clock,
        },
        {
            title: 'My Schedule',
            href: '/my/schedule',
            icon: CalendarClock,
        },
        {
            title: 'My Leave',
            href: '/my/leave',
            icon: Calendar,
        },
        {
            title: 'My Overtime',
            href: '/my/overtime-requests',
            icon: CalendarClock,
        },
        {
            title: 'My Visitors',
            href: '/my/visitors',
            icon: UserRoundCheck,
        },
    ];

    if (canApproveLeaves.value) {
        items.push({
            title: 'Leave Approvals',
            href: '/my/leave-approvals',
            icon: ClipboardCheck,
        });
    }

    // Team Goals for managers
    if (canManageEmployees.value) {
        items.push(
            {
                title: 'Team Goals',
                href: '/manager/team-goals',
                icon: Target,
            },
            {
                title: 'Team Compliance',
                href: '/team/compliance',
                icon: ShieldCheck,
            },
            {
                title: 'Probationary Evaluations',
                href: '/manager/probationary-evaluations',
                icon: UserCheck,
            },
        );
    }

    items.push(
        {
            title: 'Announcements',
            href: '/my/announcements',
            icon: Megaphone,
        },
        {
            title: 'Document Requests',
            href: '/my/document-requests',
            icon: ScrollText,
        },
        {
            title: 'My Loans',
            href: '/my/loans',
            icon: CreditCard,
        },
        {
            title: 'Loan Applications',
            href: '/my/loan-applications',
            icon: FileText,
        },
        {
            title: 'BIR 2316',
            href: '/my/bir-2316',
            icon: FileText,
        },
        {
            title: 'My Pre-boarding',
            href: '/my/preboarding',
            icon: ClipboardList,
        },
        {
            title: 'My Onboarding',
            href: '/my/onboarding',
            icon: ClipboardCheck,
        },
        {
            title: 'Probationary Status',
            href: '/my/probationary-status',
            icon: UserCheck,
        },
    );

    return items;
});

// Check if user can view audit logs (Admin only)
const canViewAuditLogs = computed(
    () => page.props.tenant?.can_view_audit_logs ?? false,
);

// Bottom nav items - conditionally include Users and Audit Logs for admins
const bottomNavItems = computed(() => {
    const items: NavItem[] = [];

    if (canManageUsers.value) {
        items.push({ title: 'Users', href: '/users', icon: UsersRound });
    }

    if (canManageUsers.value) {
        items.push({ title: 'Billing', href: '/billing', icon: CreditCard });
    }

    if (canViewAuditLogs.value) {
        items.push({ title: 'Audit Logs', href: '/settings/audit-logs', icon: History });
    }

    // Help Center is available to all authenticated users
    items.push({ title: 'Help', href: '/help', icon: HelpCircle });

    items.push({ title: 'Settings', href: '/settings/profile', icon: Settings });

    return items;
});

// Check if a nav item is active
function isActive(href: string): boolean {
    const currentPath = page.url;
    if (href === '/dashboard') {
        return currentPath === '/' || currentPath === '/dashboard';
    }
    // Special handling for Employee Dashboard to not conflict with /employees
    if (href === '/employees/dashboard') {
        return (
            currentPath === '/employees/dashboard' ||
            currentPath.startsWith('/employees/dashboard')
        );
    }
    // HR Analytics
    if (href === '/hr/analytics') {
        return currentPath.startsWith('/hr/analytics');
    }
    // Recruitment Analytics
    if (href === '/recruitment/analytics') {
        return currentPath.startsWith('/recruitment/analytics');
    }
    // Performance Analytics
    if (href === '/performance/analytics') {
        return currentPath.startsWith('/performance/analytics');
    }
    // For /employees, exclude /employees/dashboard
    if (href === '/employees') {
        return (
            currentPath.startsWith('/employees') &&
            !currentPath.startsWith('/employees/dashboard')
        );
    }
    // Company documents exact match
    if (href === '/company-documents') {
        return (
            currentPath === '/company-documents' ||
            currentPath.startsWith('/company-documents')
        );
    }
    // Contributions - highlight for all contribution sub-pages
    if (href === '/organization/contributions/sss') {
        return currentPath.startsWith('/organization/contributions');
    }
    // OT Requests
    if (href === '/overtime/requests') {
        return currentPath.startsWith('/overtime/requests');
    }
    // OT Approvals
    if (href === '/overtime/approvals') {
        return currentPath.startsWith('/overtime/approvals');
    }
    // My Overtime
    if (href === '/my/overtime-requests') {
        return currentPath.startsWith('/my/overtime-requests');
    }
    // Leave Applications
    if (href === '/leave/applications') {
        return currentPath.startsWith('/leave/applications');
    }
    // Leave Approvals
    if (href === '/leave/approvals') {
        return currentPath.startsWith('/leave/approvals');
    }
    // Leave Calendar
    if (href === '/leave/calendar') {
        return currentPath.startsWith('/leave/calendar');
    }
    // Self-service routes - exact match
    if (href === '/my/dashboard') {
        return currentPath === '/my/dashboard';
    }
    if (href === '/my/bir-2316') {
        return currentPath.startsWith('/my/bir-2316');
    }
    if (href === '/my/announcements') {
        return currentPath.startsWith('/my/announcements');
    }
    if (href === '/my/document-requests') {
        return currentPath.startsWith('/my/document-requests');
    }
    if (href === '/my/loans') {
        return currentPath.startsWith('/my/loans') && !currentPath.startsWith('/my/loan-applications');
    }
    if (href === '/my/loan-applications') {
        return currentPath.startsWith('/my/loan-applications');
    }
    if (href === '/my/leave') {
        return currentPath.startsWith('/my/leave') && !currentPath.startsWith('/my/leave-approvals');
    }
    if (href === '/my/leave-approvals') {
        return currentPath.startsWith('/my/leave-approvals');
    }
    // HR Document Requests
    if (href === '/hr/document-requests') {
        return currentPath.startsWith('/hr/document-requests');
    }
    if (href === '/loan-approvals') {
        return currentPath.startsWith('/loan-approvals');
    }
    if (href === '/preboarding-templates') {
        return currentPath.startsWith('/preboarding-templates');
    }
    if (href === '/preboarding') {
        return currentPath.startsWith('/preboarding') && !currentPath.startsWith('/preboarding-templates');
    }
    if (href === '/my/preboarding') {
        return currentPath.startsWith('/my/preboarding');
    }
    if (href === '/my/onboarding') {
        return currentPath.startsWith('/my/onboarding');
    }
    if (href === '/onboarding') {
        return currentPath === '/onboarding' || (currentPath.startsWith('/onboarding/') && !currentPath.includes('templates') && !currentPath.includes('tasks'));
    }
    if (href === '/onboarding-tasks') {
        return currentPath.startsWith('/onboarding-tasks');
    }
    if (href === '/onboarding-templates') {
        return currentPath.startsWith('/onboarding-templates');
    }
    // KPIs
    if (href === '/performance/kpis') {
        return currentPath.startsWith('/performance/kpis');
    }
    // Competencies
    if (href === '/organization/competencies') {
        return currentPath === '/organization/competencies' || currentPath.startsWith('/organization/competencies/');
    }
    // Competency Matrix
    if (href === '/organization/competency-matrix') {
        return currentPath.startsWith('/organization/competency-matrix');
    }
    // Competency Evaluations
    if (href === '/performance/competency-evaluations') {
        return currentPath.startsWith('/performance/competency-evaluations');
    }
    // Goals - Admin view
    if (href === '/performance/goals') {
        return currentPath.startsWith('/performance/goals');
    }
    // My Goals - Self-service
    if (href === '/my/goals') {
        return currentPath.startsWith('/my/goals');
    }
    // Team Goals - Manager view
    if (href === '/manager/team-goals') {
        return currentPath.startsWith('/manager/team-goals');
    }
    // 360 Evaluations - Admin view
    if (href === '/performance/evaluations') {
        return currentPath.startsWith('/performance/evaluations');
    }
    // My Evaluations - Self-service
    if (href === '/my/evaluations') {
        return currentPath.startsWith('/my/evaluations');
    }
    // My Development Plans - Self-service
    if (href === '/my/development-plans') {
        return currentPath.startsWith('/my/development-plans');
    }
    // Development Plans - Admin view
    if (href === '/performance/development-plans') {
        return currentPath.startsWith('/performance/development-plans');
    }
    // HR Probationary Evaluations
    if (href === '/hr/probationary-evaluations') {
        return currentPath.startsWith('/hr/probationary-evaluations');
    }
    // Manager Probationary Evaluations
    if (href === '/manager/probationary-evaluations') {
        return currentPath.startsWith('/manager/probationary-evaluations');
    }
    // My Probationary Status
    if (href === '/my/probationary-status') {
        return currentPath.startsWith('/my/probationary-status');
    }
    // Training - Admin
    if (href === '/training/courses') {
        return currentPath.startsWith('/training/courses');
    }
    if (href === '/training/categories') {
        return currentPath.startsWith('/training/categories');
    }
    // Training Sessions - Admin
    if (href === '/training/sessions') {
        return currentPath.startsWith('/training/sessions');
    }
    // Training Calendar - Admin
    if (href === '/training/calendar') {
        return currentPath.startsWith('/training/calendar');
    }
    // My Training Sessions - Self-service
    if (href === '/my/training/sessions') {
        return currentPath.startsWith('/my/training/sessions');
    }
    // My Enrollments - Self-service
    if (href === '/my/training/enrollments') {
        return currentPath.startsWith('/my/training/enrollments');
    }
    // Training Approvals - Admin/Manager
    if (href === '/training/approvals') {
        return currentPath.startsWith('/training/approvals');
    }
    // Certification Types - Admin
    if (href === '/organization/certification-types') {
        return currentPath.startsWith('/organization/certification-types');
    }
    // HR Certifications - Admin
    if (href === '/hr/certifications') {
        return currentPath.startsWith('/hr/certifications');
    }
    // My Certifications - Self-service
    if (href === '/my/certifications') {
        return currentPath.startsWith('/my/certifications');
    }
    // Compliance Dashboard
    if (href === '/compliance') {
        return currentPath === '/compliance';
    }
    // Compliance Courses
    if (href === '/compliance/courses') {
        return currentPath.startsWith('/compliance/courses');
    }
    // Compliance Assignments
    if (href === '/compliance/assignments') {
        return currentPath.startsWith('/compliance/assignments');
    }
    // Compliance Rules
    if (href === '/compliance/rules') {
        return currentPath.startsWith('/compliance/rules');
    }
    // Compliance Reports
    if (href === '/compliance/reports') {
        return currentPath.startsWith('/compliance/reports');
    }
    // My Compliance - Self-service
    if (href === '/my/compliance') {
        return currentPath === '/my/compliance' || (currentPath.startsWith('/my/compliance') && !currentPath.startsWith('/my/compliance/certificates'));
    }
    // My Compliance Certificates - Self-service
    if (href === '/my/compliance/certificates') {
        return currentPath.startsWith('/my/compliance/certificates');
    }
    // Team Compliance - Manager view
    if (href === '/team/compliance') {
        return currentPath.startsWith('/team/compliance');
    }
    // My Visitors
    if (href === '/my/visitors') {
        return currentPath.startsWith('/my/visitors');
    }
    // Kiosks
    if (href === '/organization/kiosks') {
        return currentPath.startsWith('/organization/kiosks');
    }
    // Visitors
    if (href === '/visitors') {
        return currentPath === '/visitors' || (currentPath.startsWith('/visitors') && !currentPath.startsWith('/visitors/log'));
    }
    if (href === '/visitors/log') {
        return currentPath.startsWith('/visitors/log');
    }
    // Audit Logs
    if (href === '/settings/audit-logs') {
        return currentPath.startsWith('/settings/audit-logs');
    }
    // Help Center
    if (href === '/help') {
        return currentPath.startsWith('/help');
    }
    // Billing
    if (href === '/billing') {
        return currentPath.startsWith('/billing');
    }
    // Settings Profile
    if (href === '/settings/profile') {
        return currentPath.startsWith('/settings') && !currentPath.startsWith('/settings/audit-logs') && !currentPath.startsWith('/settings/help-admin');
    }
    return currentPath.startsWith(href);
}

const isCollapsed = computed(() => state.value === 'collapsed');

// Load saved state from localStorage
function loadSavedState(): Record<string, boolean> {
    try {
        const saved = localStorage.getItem('sidebar-expanded-sections');
        if (saved) {
            return JSON.parse(saved);
        }
    } catch {
        // Ignore parse errors
    }
    return {};
}

const savedState = loadSavedState();

// Individual refs for each collapsible section
const hrManagementOpen = ref(savedState.hrManagement ?? true);
const timeAttendanceOpen = ref(savedState.timeAttendance ?? true);
const visitorManagementOpen = ref(savedState.visitorManagement ?? true);
const leaveManagementOpen = ref(savedState.leaveManagement ?? true);
const recruitmentOpen = ref(savedState.recruitment ?? true);
const trainingOpen = ref(savedState.training ?? true);
const complianceOpen = ref(savedState.compliance ?? true);
const performanceOpen = ref(savedState.performance ?? true);
const payrollOpen = ref(savedState.payroll ?? true);
const reportsOpen = ref(savedState.reports ?? true);
const selfServiceOpen = ref(savedState.selfService ?? true);

// Save to localStorage whenever any section changes
function saveState() {
    localStorage.setItem('sidebar-expanded-sections', JSON.stringify({
        hrManagement: hrManagementOpen.value,
        timeAttendance: timeAttendanceOpen.value,
        visitorManagement: visitorManagementOpen.value,
        leaveManagement: leaveManagementOpen.value,
        recruitment: recruitmentOpen.value,
        training: trainingOpen.value,
        compliance: complianceOpen.value,
        performance: performanceOpen.value,
        payroll: payrollOpen.value,
        reports: reportsOpen.value,
        selfService: selfServiceOpen.value,
    }));
}

watch([hrManagementOpen, timeAttendanceOpen, visitorManagementOpen, leaveManagementOpen, recruitmentOpen, trainingOpen, complianceOpen, performanceOpen, payrollOpen, reportsOpen, selfServiceOpen], saveState);
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="sidebar"
        class="border-r border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900"
    >
        <!-- Logo Header -->
        <SidebarHeader
            class="border-b border-slate-100 px-4 py-4 dark:border-slate-800"
        >
            <Link href="/dashboard" class="flex items-center gap-3">
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-500"
                >
                    <span class="text-sm font-bold text-white">K</span>
                </div>
                <span
                    v-if="!isCollapsed"
                    class="text-lg font-semibold text-slate-900 dark:text-slate-100"
                >
                    KasamaHR
                </span>
            </Link>
        </SidebarHeader>

        <!-- Main Navigation -->
        <SidebarContent class="px-2 py-4">
            <!-- Organization Switcher -->
            <div class="mb-4 px-1">
                <a
                    :href="selectTenant.url()"
                    class="flex w-full items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-left transition-colors hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400"
                    >
                        <Building2 class="h-4 w-4" />
                    </div>
                    <div v-if="!isCollapsed" class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-medium text-slate-900 dark:text-slate-100"
                        >
                            {{ tenantName }}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Switch organization
                        </p>
                    </div>
                    <ChevronsUpDown
                        v-if="!isCollapsed"
                        class="h-4 w-4 shrink-0 text-slate-400"
                    />
                </a>
            </div>

            <!-- Dashboard (hidden for Employee role — they use My Dashboard) -->
            <SidebarMenu v-if="!isEmployeeRole">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        as-child
                        :class="[
                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                            isActive('/dashboard')
                                ? 'bg-blue-500 text-white hover:bg-blue-600'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                        ]"
                    >
                        <Link href="/dashboard" class="flex items-center gap-3">
                            <LayoutGrid class="h-5 w-5 shrink-0" />
                            <span v-if="!isCollapsed">Dashboard</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- HR Management Section (hidden for Employee role) -->
            <Collapsible
                v-if="!isEmployeeRole"
                v-model:open="hrManagementOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('hr_management') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            HR Management
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('hr_management')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    hrManagementOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('hr_management') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in hrManagementItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('hr_management')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('hr_management')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('hr_management')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Time & Attendance Section (hidden for Employee role) -->
            <Collapsible
                v-if="!isEmployeeRole"
                v-model:open="timeAttendanceOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('time_attendance') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Time & Attendance
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('time_attendance')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    timeAttendanceOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('time_attendance') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in timeAttendanceItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('time_attendance')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('time_attendance')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('time_attendance')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Visitor Management Section (hidden for Employee role, requires visitor_management module) -->
            <Collapsible
                v-if="!isEmployeeRole"
                v-model:open="visitorManagementOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('visitor_management') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Visitor Management
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('visitor_management')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    visitorManagementOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('visitor_management') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in visitorManagementItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('visitor_management')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('visitor_management')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('visitor_management')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Leave Management Section (hidden for Employee role) -->
            <Collapsible
                v-if="!isEmployeeRole"
                v-model:open="leaveManagementOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('leave_management') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Leave Management
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('leave_management')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    leaveManagementOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('leave_management') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in leaveManagementItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('leave_management')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('leave_management')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('leave_management')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Recruitment Section (hidden for Employee role, requires recruitment module) -->
            <Collapsible
                v-if="!isEmployeeRole"
                v-model:open="recruitmentOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('recruitment') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Recruitment
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('recruitment')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    recruitmentOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('recruitment') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in recruitmentItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('recruitment')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('recruitment')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('recruitment')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Training Section (Admin/HR only, requires training_development module) -->
            <Collapsible
                v-if="canManageOrganization"
                v-model:open="trainingOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('training_development') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Training
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('training_development')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    trainingOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('training_development') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in trainingItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('training_development')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('training_development')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('training_development')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Compliance Training Section (Admin/HR only, requires compliance_training module) -->
            <Collapsible
                v-if="canManageOrganization"
                v-model:open="complianceOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('compliance_training') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Compliance
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('compliance_training')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    complianceOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('compliance_training') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in complianceItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('compliance_training')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('compliance_training')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('compliance_training')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Performance Section (Admin/HR only, requires performance_management module) -->
            <Collapsible
                v-if="canManageOrganization"
                v-model:open="performanceOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('performance_management') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Performance
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('performance_management')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    performanceOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('performance_management') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in performanceItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('performance_management')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('performance_management')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('performance_management')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Payroll Section -->
            <Collapsible
                v-if="canManageOrganization"
                v-model:open="payrollOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('payroll') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Payroll
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('payroll')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    payrollOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('payroll') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in payrollItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('payroll')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('payroll')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('payroll')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Reports Section -->
            <Collapsible
                v-if="canManageOrganization"
                v-model:open="reportsOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('hr_compliance') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Reports
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('hr_compliance')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    reportsOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('hr_compliance') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in reportsItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('hr_compliance')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('hr_compliance')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('hr_compliance')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>

            <!-- Self-Service Section -->
            <Collapsible
                v-model:open="selfServiceOpen"
                class="mt-4"
            >
                <SidebarGroup>
                    <CollapsibleTrigger
                        v-if="!isCollapsed"
                        class="flex w-full cursor-pointer items-center justify-between px-3 py-1"
                    >
                        <span :class="['text-xs font-semibold tracking-wider uppercase', hasModule('employee_self_service') ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500']">
                            Self-Service
                        </span>
                        <div class="flex items-center gap-1">
                            <Lock v-if="!hasModule('employee_self_service')" class="h-3 w-3 text-slate-400 dark:text-slate-500" />
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 text-slate-400 transition-transform',
                                    selfServiceOpen ? '' : '-rotate-90',
                                ]"
                            />
                        </div>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarGroupContent>
                            <Link
                                v-if="!hasModule('employee_self_service') && !isCollapsed"
                                href="/billing"
                                class="mx-3 mb-2 flex items-center gap-2 rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:hover:bg-amber-900/30"
                            >
                                <Lock class="h-3 w-3 shrink-0" />
                                <span>Upgrade your plan to unlock</span>
                            </Link>
                            <SidebarMenu>
                                <SidebarMenuItem
                                    v-for="item in selfServiceItems"
                                    :key="item.href"
                                >
                                    <SidebarMenuButton
                                        :as-child="hasModule('employee_self_service')"
                                        :class="[
                                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                            !hasModule('employee_self_service')
                                                ? 'pointer-events-none opacity-40'
                                                : isActive(item.href)
                                                    ? 'bg-blue-500 text-white hover:bg-blue-600'
                                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                                        ]"
                                    >
                                        <Link
                                            v-if="hasModule('employee_self_service')"
                                            :href="item.href"
                                            class="flex items-center gap-3"
                                        >
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </Link>
                                        <span v-else class="flex items-center gap-3">
                                            <component
                                                :is="item.icon"
                                                class="h-5 w-5 shrink-0"
                                            />
                                            <span v-if="!isCollapsed">{{
                                                item.title
                                            }}</span>
                                        </span>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </CollapsibleContent>
                </SidebarGroup>
            </Collapsible>
        </SidebarContent>

        <!-- Footer: Settings + Collapse -->
        <SidebarFooter class="mt-auto px-2 pb-4">
            <SidebarSeparator class="mb-4" />

            <!-- Settings -->
            <SidebarMenu>
                <SidebarMenuItem
                    v-for="item in bottomNavItems"
                    :key="item.href"
                >
                    <SidebarMenuButton
                        as-child
                        :class="[
                            'w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                            isActive(item.href)
                                ? 'bg-blue-500 text-white hover:bg-blue-600'
                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100',
                        ]"
                    >
                        <Link :href="item.href" class="flex items-center gap-3">
                            <component
                                :is="item.icon"
                                class="h-5 w-5 shrink-0"
                            />
                            <span v-if="!isCollapsed">{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <!-- Collapse Button -->
            <SidebarMenu class="mt-2">
                <SidebarMenuItem>
                    <SidebarMenuButton
                        class="w-full justify-start rounded-lg px-3 py-2.5 text-sm font-medium text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                        @click="toggleSidebar"
                    >
                        <ChevronLeft
                            :class="[
                                'h-4 w-4 transition-transform',
                                isCollapsed ? 'rotate-180' : '',
                            ]"
                        />
                        <span v-if="!isCollapsed">Collapse</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>
    </Sidebar>
</template>
