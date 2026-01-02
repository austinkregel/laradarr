import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;

const tokenMeta = document.querySelector('meta[name="csrf-token"]');

if (tokenMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = tokenMeta.getAttribute('content');
}

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

// Helper function to get CSRF token
const getCsrfToken = () => {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
};

// Configure Laravel Echo (only if Reverb is configured)
if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Pusher = Pusher;

    const echoConfig = {
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        enabledAuth: true,
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
    };

    window.Echo = new Echo(echoConfig);

    // Handle connection errors and state changes (always enabled for debugging)
    window.Echo.connector.pusher.connection.bind('error', (err) => {
        console.error('Echo connection error:', err);
    });

    window.Echo.connector.pusher.connection.bind('state_change', (states) => {
        console.log('Echo connection state changed:', states.previous, '->', states.current);
    });

    window.Echo.connector.pusher.connection.bind('connected', () => {
        console.log('Echo connected successfully');
    });

    window.Echo.connector.pusher.connection.bind('disconnected', () => {
        console.warn('Echo disconnected');
    });

    window.Echo.connector.pusher.connection.bind('failed', () => {
        console.error('Echo connection failed');
    });
}
