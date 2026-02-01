import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Tenant branding data structure as shared from the backend
 */
export interface TenantBranding {
    id: number;
    name: string;
    slug: string;
    logo_url: string | null;
    primary_color: string;
    user_role: 'admin' | 'member' | null;
}

/**
 * Composable for accessing tenant branding data from Inertia shared props.
 *
 * Provides reactive computed properties for tenant information and branding.
 * Returns null/default values when not on a tenant subdomain.
 *
 * @example
 * ```vue
 * <script setup>
 * const { tenant, isOnTenant, isAdmin, primaryColor, logoUrl, tenantName } = useTenant();
 * </script>
 *
 * <template>
 *   <div v-if="isOnTenant" :style="{ '--primary': primaryColor }">
 *     <img v-if="logoUrl" :src="logoUrl" :alt="tenantName" />
 *     <span v-else>{{ tenantName }}</span>
 *   </div>
 * </template>
 * ```
 */
export function useTenant() {
    const page = usePage();

    /**
     * The raw tenant data from Inertia shared props.
     * May be null when not on a tenant subdomain.
     */
    const tenant = computed<TenantBranding | null>(() => {
        return (page.props.tenant as TenantBranding | null) ?? null;
    });

    /**
     * Whether the current page is on a tenant subdomain.
     */
    const isOnTenant = computed<boolean>(() => {
        return tenant.value !== null;
    });

    /**
     * The tenant ID, or null if not on a tenant subdomain.
     */
    const tenantId = computed<number | null>(() => {
        return tenant.value?.id ?? null;
    });

    /**
     * The tenant name, or empty string if not on a tenant subdomain.
     */
    const tenantName = computed<string>(() => {
        return tenant.value?.name ?? '';
    });

    /**
     * The tenant slug (subdomain), or empty string if not on a tenant subdomain.
     */
    const tenantSlug = computed<string>(() => {
        return tenant.value?.slug ?? '';
    });

    /**
     * The tenant logo URL, or null if no logo is set.
     */
    const logoUrl = computed<string | null>(() => {
        return tenant.value?.logo_url ?? null;
    });

    /**
     * Whether the tenant has a custom logo set.
     */
    const hasLogo = computed<boolean>(() => {
        return !!logoUrl.value;
    });

    /**
     * The tenant's primary color for branding.
     * Defaults to blue (#3b82f6) if not set.
     */
    const primaryColor = computed<string>(() => {
        return tenant.value?.primary_color ?? '#3b82f6';
    });

    /**
     * The current user's role in the tenant.
     * Returns 'admin', 'member', or null if not authenticated or not a member.
     */
    const userRole = computed<'admin' | 'member' | null>(() => {
        return tenant.value?.user_role ?? null;
    });

    /**
     * Whether the current user is an admin of the tenant.
     */
    const isAdmin = computed<boolean>(() => {
        return userRole.value === 'admin';
    });

    /**
     * Whether the current user is a member (non-admin) of the tenant.
     */
    const isMember = computed<boolean>(() => {
        return userRole.value === 'member';
    });

    /**
     * Get CSS custom properties for tenant branding.
     * Useful for applying branding colors dynamically.
     *
     * @example
     * ```vue
     * <template>
     *   <div :style="brandingStyles">...</div>
     * </template>
     * ```
     */
    const brandingStyles = computed(() => {
        return {
            '--tenant-primary': primaryColor.value,
            '--tenant-primary-hover': adjustColorBrightness(
                primaryColor.value,
                -10,
            ),
            '--tenant-primary-light': adjustColorBrightness(
                primaryColor.value,
                40,
            ),
        };
    });

    return {
        // Raw tenant data
        tenant,

        // State checks
        isOnTenant,

        // Tenant info
        tenantId,
        tenantName,
        tenantSlug,

        // Branding
        logoUrl,
        hasLogo,
        primaryColor,
        brandingStyles,

        // User role
        userRole,
        isAdmin,
        isMember,
    };
}

/**
 * Adjusts the brightness of a hex color.
 *
 * @param hex - The hex color string (e.g., '#3b82f6')
 * @param percent - The percentage to adjust brightness (-100 to 100)
 * @returns The adjusted hex color
 */
function adjustColorBrightness(hex: string, percent: number): string {
    // Remove # if present
    hex = hex.replace(/^#/, '');

    // Parse hex to RGB
    const num = parseInt(hex, 16);
    const r = Math.min(255, Math.max(0, (num >> 16) + percent * 2.55));
    const g = Math.min(
        255,
        Math.max(0, ((num >> 8) & 0x00ff) + percent * 2.55),
    );
    const b = Math.min(255, Math.max(0, (num & 0x0000ff) + percent * 2.55));

    // Convert back to hex
    return `#${((1 << 24) + (Math.round(r) << 16) + (Math.round(g) << 8) + Math.round(b)).toString(16).slice(1)}`;
}
