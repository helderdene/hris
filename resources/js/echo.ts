import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Make Pusher available globally for Echo
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'pusher'>;
    }
}

window.Pusher = Pusher;

/**
 * Initialize Laravel Echo with Pusher WebSocket configuration.
 *
 * This enables real-time broadcasting for features like:
 * - Live attendance log updates
 * - Notification delivery
 * - Dashboard real-time stats
 */
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    forceTLS: true,
    // Required for private channel authentication
    authEndpoint: '/broadcasting/auth',
    authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: (error: Error | null, data: { auth: string; channel_data?: string } | null) => void) => {
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
                    callback(error instanceof Error ? error : new Error(String(error)), null);
                });
        },
    }),
});

export default window.Echo;
