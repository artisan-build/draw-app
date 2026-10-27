import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo;

export function getEcho(config) {
    if (echo) {
        return echo;
    }

    echo = new Echo({
        authEndpoint: '/broadcasting/auth',
        broadcaster: 'reverb',
        enabledTransports: ['ws', 'wss'],
        forceTLS: config.scheme === 'https',
        key: config.key,
        Pusher,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
    });

    return echo;
}
