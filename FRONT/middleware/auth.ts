export default defineNuxtRouteMiddleware(async (to, from) => {
  const { token, fetchUser } = useAuth()
  const isProtected = to.meta?.auth === true
  if (!isProtected) return

  if (!token.value) {
    return navigateTo('/login')
  }

  const u = await fetchUser()
  if (!u) return navigateTo('/login')
})