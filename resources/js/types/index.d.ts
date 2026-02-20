import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
}

/**
 * Tenant branding data shared via Inertia.
 * Available on tenant subdomains, null on main domain.
 */
export interface SubscriptionData {
    plan: string | null;
    status: string | null;
    is_on_trial: boolean;
    trial_ends_at: string | null;
    available_modules: string[];
}

export interface TenantContext {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    primary_color: string;
    user_role: 'admin' | 'member' | 'employee' | null;
    can_manage_users?: boolean;
    can_manage_organization?: boolean;
    can_manage_employees?: boolean;
    can_view_audit_logs?: boolean;
    subscription?: SubscriptionData;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    is_super_admin: boolean;
    sidebarOpen: boolean;
    tenant: TenantContext | null;
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

/**
 * Notification data from the API.
 */
export interface Notification {
    id: string;
    type: string;
    title: string;
    message: string;
    is_read: boolean;
    read_at: string | null;
    created_at: string;
    time_ago: string;
    url?: string | null;
    file_path?: string | null;
    file_name?: string | null;
}

/**
 * Billing data types for plan management and subscriptions.
 */
export interface PlanModuleData {
    module: string;
    label: string;
}

export interface PlanPriceData {
    id: number;
    billing_interval: string;
    price_per_unit: number;
    currency: string;
}

export interface PlanData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    limits: Record<string, number> | null;
    sort_order: number;
    prices: PlanPriceData[];
    modules: PlanModuleData[];
}

export interface SubscriptionDetail {
    id: number;
    paymongo_status: string;
    plan_price_id: number;
    quantity: number;
    current_period_end: string | null;
    ends_at: string | null;
    plan_price: PlanPriceData | null;
}

export interface TenantAddonData {
    id: number;
    type: string;
    type_label: string;
    quantity: number;
    price_per_unit: number;
    is_active: boolean;
    extra_units: number;
    monthly_cost: number;
}

export interface UsageStats {
    employee_count: number;
    max_employees: number | null;
    biometric_device_count: number;
    max_biometric_devices: number | null;
}

export interface AddonTypeOption {
    value: string;
    label: string;
    units_per_quantity: number;
    default_price: number;
}

/**
 * Platform Admin Dashboard types.
 */
export interface AdminDashboardStats {
    total_tenants: number;
    active_subscriptions: number;
    active_trials: number;
    expired_trials: number;
    mrr: number;
    trial_conversion_rate: number;
}

export interface SubscriptionByPlan {
    name: string;
    count: number;
}

export interface RecentRegistration {
    id: number;
    name: string;
    plan_name: string | null;
    status: string;
    created_at: string;
}

export interface AdminTenantListItem {
    id: number;
    name: string;
    slug: string;
    plan_name: string | null;
    employee_count: number;
    status: string;
    created_at: string;
}

export interface AdminTenantDetail {
    id: number;
    name: string;
    slug: string;
    created_at: string;
    employee_count: number;
    status: string;
    plan: {
        id: number;
        name: string;
        slug: string;
        modules: PlanModuleData[];
    } | null;
    trial_ends_at: string | null;
    is_on_trial: boolean;
    trial_expired: boolean;
}

export interface AdminSubscriptionHistory {
    id: number;
    plan_name: string | null;
    status: string;
    billing_interval: string | null;
    price_per_unit: number | null;
    quantity: number;
    current_period_end: string | null;
    ends_at: string | null;
    created_at: string;
}

export interface AdminUser {
    id: number;
    name: string;
    email: string;
}

export interface PlanWithCounts {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
    is_custom: boolean;
    tenant_count: number;
    limits: Record<string, number | boolean> | null;
    prices: PlanPriceData[];
    modules: PlanModuleData[];
}

export interface ModuleOption {
    value: string;
    label: string;
}

/**
 * Visitor Management types.
 */
export interface VisitorData {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email?: string;
    phone?: string;
    company?: string;
    id_type?: string;
    id_number?: string;
    photo_path?: string;
    notes?: string;
    visits_count?: number;
    created_at?: string;
    updated_at?: string;
}

export interface VisitorVisitData {
    id: number;
    visitor: VisitorData;
    work_location?: { id: number; name: string };
    host_employee?: { id: number; name: string };
    purpose: string;
    status: string;
    status_label: string;
    registration_source: string;
    expected_at?: string;
    approved_at?: string;
    host_approved_at?: string;
    host_approved_by?: number;
    is_admin_approved?: boolean;
    is_host_approved?: boolean;
    rejected_at?: string;
    rejection_reason?: string;
    checked_in_at?: string;
    checked_out_at?: string;
    check_in_method?: string;
    check_in_method_label?: string;
    qr_token?: string;
    badge_number?: string;
    notes?: string;
    created_at?: string;
    updated_at?: string;
}
