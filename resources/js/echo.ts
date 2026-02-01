import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Echo
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

window.Pusher = Pusher;

/**
 * Initialize Laravel Echo with Reverb WebSocket configuration.
 *
 * This enables real-time broadcasting for features like:
 * - Live attendance log updates
 * - Notification delivery
 * - Dashboard real-time stats
 */
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Required for private channel authentication
    authEndpoint: '/broadcasting/auth',
    authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: (error: Error | null, data?: { auth: string }) => void) => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            fetch('/broadcasting/auth', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken ?? '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channel.name,
                }),
            })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(`Auth failed with status ${response.status}`);
                    }
                    return response.json();
                })
                .then((data) => {
                    callback(null, data);
                })
                .catch((error) => {
                    console.error('[Echo Auth] Error:', error);
                    callback(error);
                });
        },
    }),
});

export default window.Echo;
