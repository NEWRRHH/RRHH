export default defineNuxtRouteMiddleware(async (to, from) => {
  const { token, fetchUser, setToken } = useAuth()
  const isProtected = to.meta?.auth === true
  if (!isProtected) return
  console.log('auth middleware start', to.path, 'token', token.value)

  // middleware should be no-op during SSR (localStorage is client-only)
  if (process.server) return

  // if state lacks token but localStorage has one, restore it and validate user
  if (!token.value) {
    const saved = process.client ? localStorage.getItem('rrhh_token') : null
    if (saved) {
      setToken(saved)
      const u = await fetchUser()
      if (u) return
      // fall through to redirect if validation fails
    } else {
      return navigateTo('/login')
    }
  }

  // ensure the user is valid for protected routes
  const u = await fetchUser()
  if (!u) return navigateTo('/login')
})