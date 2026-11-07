/**
 * Global auth guard.
 *
 * Every route is protected by default; public pages opt out with
 * `definePageMeta({ auth: false })` (login, register, index). This replaces
 * the per-page localStorage guards that used to be duplicated in ~10 pages.
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.meta?.auth === false) return

 const { fetchUser } = useAuth()

  // SSR: forward the incoming httpOnly cookie so protected pages are only
  // rendered for an authenticated session (no flash of private content).
  const fetcher = import.meta.server ? useRequestFetch() : undefined
  const u = await fetchUser(fetcher)

  if (!u) return navigateTo('/login')
})
