import { useRealtime } from './useRealtime';

type Fetcher = (url: string, opts?: Record<string, any>) => Promise<any>

/**
 * Auth state for the whole app.
 *
 * The API token never reaches JavaScript: it lives in an httpOnly cookie
 * written by the Nitro routes in server/api/auth/* and is attached to every
 * outgoing call by the proxy in server/api/[...path].ts. All calls are made
 * against relative /api/* URLs (same origin), so there is no CORS surface
 * and no token in localStorage for an XSS payload to steal.
 */
export const useAuth = () => {
  const config = useRuntimeConfig()

  // Browser-facing base URL, only used for static assets (/storage/avatars...).
  // Behind the gateway / Cloudflare tunnel (default port) /storage is served
  // on the same origin, so a relative base ('') is used instead.
  const apiBase: string = useRequestURL().port
    ? String(config.public.apiBase || 'http://localhost:8000')
    : ''

  const authenticated = useState<boolean>('auth_authenticated', () => false)
  const user = useState<any | null>('auth_user', () => null)
  const unreadNotifications = useState<number>('unread_notifications', () => 0)
  // Last MessageSent event received via WebSocket. Pages (e.g. notificaciones.vue)
  // watch this instead of subscribing to Echo directly, so there is only ONE Echo
  // listener per session and no stale-closure / duplicate-listener problems.
  const lastReceivedMessage = useState<any>('last_received_message', () => null)
  // Read-receipt events for sent messages (used to render WhatsApp-like checks).
  const lastReadReceipt = useState<any>('last_read_receipt', () => null)

  const { connect: connectRealtime, disconnect: disconnectRealtime, connected: realtimeConnected, instance: realtimeInstance, subscribedChannels } = useRealtime();

  const resetSession = () => {
    authenticated.value = false
    user.value = null
    unreadNotifications.value = 0
    lastReceivedMessage.value = null
    lastReadReceipt.value = null
    disconnectRealtime()
  }

  const fetchUser = async (fetcher?: Fetcher) => {
    const doFetch: Fetcher = fetcher || ($fetch as unknown as Fetcher)

    try {
      const u = await doFetch('/api/auth/me')
      user.value = u
      authenticated.value = true

      // Connect Echo as soon as we have a valid user.
      // Do NOT subscribe to channels here — pages register their own listeners.
      if (user.value) {
        try {
          connectRealtime({
            host: config.public.reverbHost,
            port: config.public.reverbPort,
            appId: config.public.reverbAppId,
            key: config.public.reverbAppKey,
            // Same-origin endpoint: the proxy injects the Bearer token from
            // the httpOnly cookie, the browser never sees it.
            authEndpoint: '/api/broadcasting/auth',
          });
          // Subscribe to the global unread badge exactly ONCE per session.
          const counterKey = `useAuth:unread:${user.value.id}`
          if (!subscribedChannels.has(counterKey)) {
            subscribedChannels.add(counterKey)
            const echoInst = realtimeInstance()
            if (echoInst) {
              echoInst.private(`user.${user.value.id}`)
                .listen('.MessageSent', (e: any) => {
                  unreadNotifications.value++
                  lastReceivedMessage.value = e
                })
                .listen('.MessageRead', (e: any) => {
                  lastReadReceipt.value = e
                })
            }
          }
        } catch (err) {
          console.error('fetchUser: realtime connect failed', err)
        }
      }

      return u
    } catch (e: any) {
      const status = e?.status ?? e?.statusCode ?? e?.response?.status ?? null
      // Backend explicitly rejected the session (expired/revoked token) —
      // expected on public pages, so it is not logged as an error.
      if (status === 401) {
        resetSession()
        return null
      }
      // Other failures (network, websocket setup) must not wipe the state.
      console.error('fetchUser error', e)
      return null
    }
  }

  const login = async (credentials: { email: string; password: string }) => {
    await $fetch('/api/auth/login', {
      method: 'POST',
      body: credentials,
    })
    const u = await fetchUser()  // also connects Echo
    if (u) await fetchUnread()
    return u
  }

  const register = async (payload: { company_name: string; name: string; email: string; password: string; password_confirmation: string }) => {
    await $fetch('/api/auth/register', {
      method: 'POST',
      body: payload,
    })
    return await fetchUser()
  }

  const logout = async () => {
    try {
      await $fetch('/api/auth/logout', { method: 'POST' })
    } catch (e) {
      // The local cookie is cleared by the route even if the API is down.
      console.error('logout failed', e)
    }
    resetSession()
  }

  /**
   * Fetch latest unread notifications count for current user.
   */
  const fetchUnread = async () => {
    if (!authenticated.value) return
    try {
      const res: any = await $fetch('/api/dashboard')
      unreadNotifications.value = res.unread_notifications || 0
    } catch (e) {
      console.error('failed to refresh unread count', e)
    }
  }

  // expose realtime helpers so pages/components can reuse the same echo instance
  return { apiBase, authenticated, user, login, register, logout, fetchUser, unreadNotifications, fetchUnread, realtimeInstance, connectRealtime, disconnectRealtime, realtimeConnected, subscribedChannels, lastReceivedMessage, lastReadReceipt }
}
