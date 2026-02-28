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

  const { connect: connectRealtime, disconnect: disconnectRealtime, instance: realtimeInstance } = useRealtime();

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

      // if we now have both user and token, ensure websocket connection
      if (user.value && token.value) {
        try {
          const echo = connectRealtime({
            host: config.public.reverbHost,
            port: config.public.reverbPort,
            appId: config.public.reverbAppId,
            key: config.public.reverbAppKey,
            token: token.value,
            authEndpoint: `${config.public.apiBase}/broadcasting/auth`,
          });
          // also listen for incoming messages and update unread counter
          echo.private(`user.${user.value.id}`)
            .listen('MessageSent', (e: any) => {
              unreadNotifications.value++;
            });
        } catch (err) {
          console.error('fetchUser: realtime connect failed', err)
          // do not throw; we still consider fetchUser successful
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
    await fetchUser()
    await fetchUnread()
    // establish websocket connection after obtaining user and token
    if (user.value && token.value) {
      try {
        connectRealtime({
          host: config.public.reverbHost,
          port: config.public.reverbPort,
          appId: config.public.reverbAppId,
          key: config.public.reverbAppKey,
          token: token.value,
          authEndpoint: `${config.public.apiBase}/broadcasting/auth`,
        }).private(`user.${user.value.id}`)
          .listen('MessageSent', (e: any) => {
            // increment unread counter when a message arrives for this user
            unreadNotifications.value++;
          });
      } catch (err) {
        console.error('login realtime connect failed', err)
      }
    }
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
  return { apiBase, token, user, login, register, logout, fetchUser, setToken, unreadNotifications, fetchUnread, realtimeInstance, connectRealtime, disconnectRealtime }
}

