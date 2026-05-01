// Ambient declarations to silence IDE/TS for Nuxt auto-imports used at runtime
declare const $fetch: any
declare const process: any
declare function useRuntimeConfig(): any
declare function useState<T>(key: string, factory?: () => T): any

// useRuntimeConfig/useState are provided by Nuxt at runtime (we declared them above)
import { useRealtime } from './useRealtime';

export const useAuth = () => {
  const config = useRuntimeConfig()
  // SSR (Node inside Docker): use private config.apiBase = http://back_nginx
  // Client (browser):         use public config.apiBase = http://localhost:8000
  const apiBase: string = process.server
    ? (config.apiBase as string || 'http://back_nginx')
    : (config.public.apiBase as string || 'http://localhost:8000')
  const token = useState<string | null>('auth_token', () => (process.client ? localStorage.getItem('rrhh_token') : null))
  const user = useState<any | null>('auth_user', () => null)
  const unreadNotifications = useState<number>('unread_notifications', () => 0)
  // Last MessageSent event received via WebSocket. Pages (e.g. notificaciones.vue)
  // watch this instead of subscribing to Echo directly, so there is only ONE Echo
  // listener per session and no stale-closure / duplicate-listener problems.
  const lastReceivedMessage = useState<any>('last_received_message', () => null)
  // Read-receipt events for sent messages (used to render WhatsApp-like checks).
  const lastReadReceipt = useState<any>('last_read_receipt', () => null)

  const { connect: connectRealtime, disconnect: disconnectRealtime, instance: realtimeInstance, subscribedChannels } = useRealtime();

  const setToken = (t: string | null) => {
    console.log('useAuth.setToken', t)
    token.value = t
    if (process.client) {
      if (t) localStorage.setItem('rrhh_token', t)
      else localStorage.removeItem('rrhh_token')
    }
  }

  const fetchUser = async () => {
    if (!token.value) return null
    try {
      console.log('fetchUser: requesting /api/user with token', token.value)
      const u = await $fetch(`${apiBase}/api/user`, {
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
      })
      console.log('fetchUser: got user', u)
      user.value = u

      // Connect Echo client as soon as we have a valid user + token.
      // Do NOT subscribe to channels here — pages register their own listeners.
      // Subscribing here caused duplicate listeners on every fetchUser call
      // (auth middleware calls fetchUser on every navigation).
      if (user.value && token.value) {
        try {
          connectRealtime({
            host: config.public.reverbHost,
            port: config.public.reverbPort,
            appId: config.public.reverbAppId,
            key: config.public.reverbAppKey,
            token: token.value,
            authEndpoint: `${config.public.apiBase}/broadcasting/auth`,
          });
          // Subscribe to the global unread badge exactly ONCE per session.
          // We use subscribedChannels (module-level Set) as the guard.
          // Cleared on logout → fresh login will re-subscribe.
          const counterKey = `useAuth:unread:${user.value.id}`
          if (!subscribedChannels.has(counterKey)) {
            subscribedChannels.add(counterKey)
            const echoInst = realtimeInstance()
            if (echoInst) {
              echoInst.private(`user.${user.value.id}`)
                .listen('.MessageSent', (e: any) => {
                  unreadNotifications.value++
                  // Publish the event to all interested pages via shared reactive state.
                  // This avoids pages having to subscribe to Echo directly.
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
      console.error('fetchUser error', e)
      // Only clear our token when the backend explicitly rejects authentication
      // (e.g. 401).  Other errors such as coding mistakes or websocket
      // initialization problems should not automatically log the user out.
      const status = e?.response?.status || e?.status || null
      if (status === 401) {
        setToken(null)
        user.value = null
      }
      return null
    }
  }

  const login = async (credentials: { email: string; password: string }) => {
    const res: any = await $fetch(`${apiBase}/api/login`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: credentials,
    })
    setToken(res.token)
    await fetchUser()  // also connects Echo
    await fetchUnread()
    return res
  }

  const register = async (payload: { name: string; email: string; password: string; password_confirmation: string }) => {
    const res: any = await $fetch(`${apiBase}/api/register`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: payload,
    })
    setToken(res.token)
    await fetchUser()
    return res
  }

  const logout = async () => {
    if (!token.value) return
    await $fetch(`${apiBase}/api/logout`, {
      method: 'POST',
      headers: {
        Authorization: `Bearer ${token.value}`,
        Accept: 'application/json',
      },
    })
    setToken(null)
    user.value = null
    unreadNotifications.value = 0
    lastReceivedMessage.value = null
    lastReadReceipt.value = null
  }

  // hydrate token from localStorage on the client so full reloads keep session
  if (process.client && !token.value) {
    const saved = localStorage.getItem('rrhh_token')
    if (saved) setToken(saved)
  }

  /**
   * Fetch latest unread notifications count for current user.
   */
  const fetchUnread = async () => {
    if (!token.value) return
    try {
      const res: any = await $fetch(`${apiBase}/api/dashboard`, {
        headers: {
          Authorization: `Bearer ${token.value}`,
          Accept: 'application/json',
        },
      })
      unreadNotifications.value = res.unread_notifications || 0
    } catch (e) {
      console.error('failed to refresh unread count', e)
    }
  }

  // expose realtime helpers so pages/components can reuse the same echo instance
  return { apiBase, token, user, login, register, logout, fetchUser, setToken, unreadNotifications, fetchUnread, realtimeInstance, connectRealtime, disconnectRealtime, lastReceivedMessage, lastReadReceipt }
}

