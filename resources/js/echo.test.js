import Pusher from 'pusher-js';
import { describe, expect, it } from 'vitest';

import { getEcho } from './echo.js';

describe('Echo Reverb client', () => {
    it('constructs a Pusher instance before subscribing to a channel', () => {
        const echo = getEcho({
            host: '127.0.0.1',
            key: 'test-key',
            port: 65535,
            scheme: 'http',
        });

        expect(echo.connector.pusher).toBeInstanceOf(Pusher);
        expect(() => echo.channel('integration-shape')).not.toThrow();

        echo.leave('integration-shape');
        echo.disconnect();
    });
});
