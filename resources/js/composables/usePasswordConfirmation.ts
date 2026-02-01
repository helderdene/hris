import { confirmation } from '@/routes/password';
import { readonly, ref } from 'vue';

/**
 * Password confirmation timeout in milliseconds (3 hours).
 * Matches the Laravel auth.password_timeout configuration.
 */
const PASSWORD_CONFIRMATION_TIMEOUT_MS = 10800 * 1000;

/**
 * State tracking for the password confirmation composable.
 * Stored outside the composable to maintain state across component instances.
 */
const isModalOpen = ref(false);
const lastConfirmedAt = ref<number | null>(null);
const pendingResolve = ref<((value: boolean) => void) | null>(null);
const pendingReject = ref<((reason?: unknown) => void) | null>(null);

/**
 * Composable for managing password confirmation flow.
 *
 * Provides a reusable way to require password re-confirmation for sensitive
 * actions throughout the application. The confirmation status is valid for
 * 3 hours (configurable via Laravel's auth.password_timeout).
 *
 * @example
 * ```vue
 * <script setup lang="ts">
 * import { usePasswordConfirmation } from '@/composables/usePasswordConfirmation';
 * import PasswordConfirmationModal from '@/components/PasswordConfirmationModal.vue';
 *
 * const { isOpen, confirmPassword, onConfirmed, onCancelled } = usePasswordConfirmation();
 *
 * const handleSensitiveAction = async () => {
 *     const confirmed = await confirmPassword();
 *     if (confirmed) {
 *         // Proceed with sensitive action
 *     }
 * };
 * </script>
 *
 * <template>
 *     <button @click="handleSensitiveAction">Do Sensitive Thing</button>
 *     <PasswordConfirmationModal
 *         v-model:open="isOpen"
 *         @confirmed="onConfirmed"
 *         @cancelled="onCancelled"
 *     />
 * </template>
 * ```
 */
export const usePasswordConfirmation = () => {
    /**
     * Checks if the password confirmation is still valid based on the
     * last confirmation timestamp and the configured timeout period.
     */
    const isConfirmationValid = (): boolean => {
        if (lastConfirmedAt.value === null) {
            return false;
        }

        const elapsed = Date.now() - lastConfirmedAt.value;
        return elapsed < PASSWORD_CONFIRMATION_TIMEOUT_MS;
    };

    /**
     * Fetches the current password confirmation status from the server.
     * This allows checking if the user has recently confirmed their password
     * without requiring a new confirmation.
     */
    const checkConfirmationStatus = async (): Promise<boolean> => {
        try {
            const response = await fetch(confirmation.url(), {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                if (data.confirmed) {
                    lastConfirmedAt.value = Date.now();
                    return true;
                }
            }
            return false;
        } catch {
            return false;
        }
    };

    /**
     * Requests password confirmation from the user.
     *
     * If the confirmation is still valid (within the 3-hour window),
     * returns immediately with true. Otherwise, opens the confirmation
     * modal and returns a Promise that resolves when the user confirms
     * their password or rejects if they cancel.
     *
     * @returns Promise<boolean> - true if confirmed, false if cancelled
     */
    const confirmPassword = async (): Promise<boolean> => {
        // First check if we have a cached valid confirmation
        if (isConfirmationValid()) {
            return true;
        }

        // Check with the server if the password was confirmed in another tab/window
        const serverConfirmed = await checkConfirmationStatus();
        if (serverConfirmed) {
            return true;
        }

        // Need to show the modal for confirmation
        return new Promise<boolean>((resolve, reject) => {
            pendingResolve.value = resolve;
            pendingReject.value = reject;
            isModalOpen.value = true;
        });
    };

    /**
     * Handler for when password confirmation succeeds.
     * Should be called from the PasswordConfirmationModal's @confirmed event.
     */
    const onConfirmed = () => {
        lastConfirmedAt.value = Date.now();
        if (pendingResolve.value) {
            pendingResolve.value(true);
            pendingResolve.value = null;
            pendingReject.value = null;
        }
        isModalOpen.value = false;
    };

    /**
     * Handler for when password confirmation is cancelled.
     * Should be called from the PasswordConfirmationModal's @cancelled event.
     */
    const onCancelled = () => {
        if (pendingResolve.value) {
            pendingResolve.value(false);
            pendingResolve.value = null;
            pendingReject.value = null;
        }
        isModalOpen.value = false;
    };

    /**
     * Resets the confirmation state.
     * Can be used to force re-confirmation on the next sensitive action.
     */
    const resetConfirmation = () => {
        lastConfirmedAt.value = null;
    };

    return {
        /**
         * Whether the password confirmation modal is currently open.
         * Use v-model:open on the PasswordConfirmationModal component.
         */
        isOpen: isModalOpen,

        /**
         * Readonly reference to whether the confirmation modal is open.
         */
        isModalOpen: readonly(isModalOpen),

        /**
         * Whether the current confirmation is still valid.
         */
        isConfirmationValid,

        /**
         * Request password confirmation.
         */
        confirmPassword,

        /**
         * Handler for successful confirmation.
         */
        onConfirmed,

        /**
         * Handler for cancelled confirmation.
         */
        onCancelled,

        /**
         * Reset the confirmation state.
         */
        resetConfirmation,

        /**
         * Check confirmation status with the server.
         */
        checkConfirmationStatus,
    };
};
