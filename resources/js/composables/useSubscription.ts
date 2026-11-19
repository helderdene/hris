import type { SubscriptionData, TenantContext } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Composable for accessing subscription data from Inertia shared props.
 *
 * Provides reactive computed properties for subscription information
 * and module access checks.
 *
 * @example
 * ```vue
 * <script setup>
 * const { plan, isActive, hasModule } = useSubscription();
 * </script>
 *
 * <template>
 *   <div v-if="hasModule('recruitment')">Recruitment features here</div>
 * </template>
 * ```
 */
export function useSubscription() {
    const page = usePage();

    const subscription = computed<SubscriptionData | null>(() => {
        const tenant = page.props.tenant as TenantContext | null;
        return tenant?.subscription ?? null;
    });

    const plan = computed<string | null>(() => {
        return subscription.value?.plan ?? null;
    });

    const status = computed<string | null>(() => {
        return subscription.value?.status ?? null;
    });

    const isOnTrial = computed<boolean>(() => {
        return subscription.value?.is_on_trial ?? false;
    });

    const isLifetime = computed<boolean>(() => {
        return subscription.value?.is_lifetime ?? false;
    });

    const trialEndsAt = computed<string | null>(() => {
        return subscription.value?.trial_ends_at ?? null;
    });

    const availableModules = computed<string[]>(() => {
        return subscription.value?.available_modules ?? [];
    });

    const isActive = computed<boolean>(() => {
        return status.value === 'active' || isOnTrial.value || isLifetime.value;
    });

    function hasModule(module: string): boolean {
        return availableModules.value.includes(module);
    }

    return {
        subscription,
        plan,
        status,
        isOnTrial,
        isLifetime,
        trialEndsAt,
        availableModules,
        isActive,
        hasModule,
    };
}
