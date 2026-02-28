import { ref } from 'vue';
import Echo from 'laravel-echo';
// Reverb speaks the Pusher protocol, so we can simply use the official
// `pusher-js` client.  The library isn't published under `reverb-js`, which
// is why we were seeing `ReferenceError: Pusher is not defined` earlier.
//
// Install both packages in the front workspace:
//   npm install laravel-echo pusher-js
//
// Once `pusher-js` is available we just surface it globally so Echo can find
// it as expected.
import Pusher from 'pusher-js';

// ensure global Pusher reference for Echo
if (process.client && typeof window !== 'undefined') {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  (window as any).Pusher = Pusher;
}

// we use the `reverb` broadcaster; the client-side library is already
// compatible with the regular Pusher client (no extra package required).  socket.io is not required.
// previously the comment mentioned `reverb-js` which doesn't exist on npm.

let echo: Echo | null = null;
// Track which user channels already have listeners to prevent duplicates.
const subscribedChannels = new Set<string>();

export function useRealtime() {
  const connected = ref(false);

  function connect(config: {
    host: string;
    port: string | number;
    appId: string;
    key: string;
    token?: string;
    authEndpoint?: string;
  }) {
    // WebSockets only exist in the browser — skip silently during SSR.
    if (typeof process !== 'undefined' && process.server) return echo as any;
    if (echo) {
      return echo;
    }

    const opts = {
      broadcaster: 'reverb',
      key: config.key,
      appId: config.appId,
      wsHost: config.host,
      wsPort: Number(config.port),
      wssPort: Number(config.port),
      forceTLS: false,
      disableStats: true,
      // Force WebSocket transport only — avoids falling back to HTTP long-polling
      // which would never reach Reverb and appear as no connection in logs.
      enabledTransports: ['ws', 'wss'],
      // The auth endpoint must be reachable from the browser.
      // Default: http://localhost:8000/broadcasting/auth
      authEndpoint: config.authEndpoint || 'http://localhost:8000/broadcasting/auth',
      auth: {
        headers: {
          Authorization: config.token ? `Bearer ${config.token}` : '',
          Accept: 'application/json',
        },
      },
    };

    console.log('useRealtime.connect options', opts);

    echo = new Echo(opts);

    connected.value = true;
    return echo;
  }

  function disconnect() {
    if (echo) {
      echo.disconnect();
      echo = null;
      subscribedChannels.clear();
      connected.value = false;
    }
  }

  // also allow callers to access the raw Echo instance if needed
  function instance() {
    return echo;
  }

  return { connect, disconnect, connected, instance, subscribedChannels };
}
