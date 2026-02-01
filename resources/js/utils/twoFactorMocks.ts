/**
 * Mock exports for disabled 2FA features.
 * These are hardcoded since 2FA is disabled in Fortify config
 * and wayfinder doesn't generate routes for disabled features.
 */

export const enable = {
    url: () => '/user/two-factor-authentication',
    form: () => ({ action: '/user/two-factor-authentication', method: 'post' }),
};

export const disable = {
    url: () => '/user/two-factor-authentication',
    form: () => ({
        action: '/user/two-factor-authentication',
        method: 'delete',
    }),
};

export const confirm = {
    url: () => '/user/confirmed-two-factor-authentication',
    form: () => ({
        action: '/user/confirmed-two-factor-authentication',
        method: 'post',
    }),
};

export const regenerateRecoveryCodes = {
    url: () => '/user/two-factor-recovery-codes',
    form: () => ({ action: '/user/two-factor-recovery-codes', method: 'post' }),
};

export const qrCode = { url: () => '/user/two-factor-qr-code' };
export const secretKey = { url: () => '/user/two-factor-secret-key' };
export const recoveryCodes = { url: () => '/user/two-factor-recovery-codes' };

// Login/store for 2FA challenge page
export const store = {
    url: () => '/two-factor-challenge',
    form: () => ({ action: '/two-factor-challenge', method: 'post' }),
};
