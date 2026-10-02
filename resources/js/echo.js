import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Image produksi di-build tanpa env, jadi tanpa VITE_REVERB_* Echo tersambung ke origin halaman,
// tempat Caddy mem-proxy /app ke Reverb (ADR-0070). Key dibaca saat runtime dari meta tag.
const tls = (import.meta.env.VITE_REVERB_SCHEME ?? location.protocol.replace(':', '')) === 'https';
const port = import.meta.env.VITE_REVERB_PORT ?? (location.port || (tls ? 443 : 80));

const key = document.querySelector('meta[name="reverb-key"]')?.content;

// Tanpa key (tamu/portal, atau Reverb belum dikonfigurasi) Echo tidak dibuat; halaman jatuh ke polling.
if (key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: tls,
        enabledTransports: ['ws', 'wss'],
    });
}
