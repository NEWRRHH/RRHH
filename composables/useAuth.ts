// Ambient declarations to silence IDE/TS for Nuxt auto-imports used at runtime
declare const $fetch: any
declare const process: any
declare function useRuntimeConfig(): any
declare function useState<T>(key: string, factory?: () => T): any

// useRuntimeConfig/useState are provided by Nuxt at runtime (we declared them above)

export const useAuth = () => {
  const config = useRuntimeConfig()
  const apiBase = config.public.apiBase || 'http://localhost:8000'
  const token = useState<string | null>('auth_token', () => (process.client ? localStorage.getItem('rrhh_token') : null))
  const user = useState<any | null>('auth_user', () => null)

  const setToken = (t: string | null) => {
    token.value = t
    if (process.client) {
      if (t) localStorage.setItem('rrhh_token', t)
      else localStorage.removeItem('rrhh_token')
    }
  }

  const fetchUser = async () => {
    if (!token.value) return null
    try {
      const u = await $fetch(`${apiBase}/api/user`, {
        headers: { Authorization: `Bearer ${token.value}` },
      })
      user.value = u
      return u
    } catch (e) {
      setToken(null)
      user.value = null
      return null
    }
  }

  const login = async (credentials: { email: string; password: string }) => {
    const res: any = await $fetch(`${apiBase}/api/login`, {
      method: 'POST',
      body: credentials,
    })
    setToken(res.token)
    await fetchUser()
    return res
  }

  const register = async (payload: { name: string; email: string; password: string; password_confirmation: string }) => {
    const res: any = await $fetch(`${apiBase}/api/register`, {
      method: 'POST',
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
      headers: { Authorization: `Bearer ${token.value}` },
    })
    setToken(null)
    user.value = null
  }

  // hydrate token from localStorage on the client so full reloads keep session
  if (process.client && !token.value) {
    const saved = localStorage.getItem('rrhh_token')
    if (saved) setToken(saved)
  }

  return { apiBase, token, user, login, register, logout, fetchUser, setToken }
}
