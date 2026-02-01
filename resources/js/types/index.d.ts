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
export interface TenantContext {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    primary_color: string;
    user_role: 'admin' | 'member' | null;
    can_manage_users?: boolean;
    can_manage_organization?: boolean;
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
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
