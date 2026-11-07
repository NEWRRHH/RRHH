/**
 * Global auth guard.
 *
 * Every route is protected by default; public pages opt out with
 * `definePageMeta({ auth: false })` (login, register, index). This replaces
 * the per-page localStorage guards that used to be duplicated in ~10 pages.
 *
 * Two separate apps share this frontend:
 * - /admin/*  -> platform super admin panel (super admin only);
 * - the rest  -> the company app (super admin only while "inside" a company,
 *                in which case the session is the company admin's).
 */
export default defineNuxtRouteMiddleware(async (to) => {
  if (to.meta?.auth === false) return

  const { fetchUser } = useAuth()

  // SSR: forward the incoming httpOnly cookie so protected pages are only
  // rendered for an authenticated session (no flash of private content).
  const fetcher = import.meta.server ? useRequestFetch() : undefined
  const u = await fetchUser(fetcher)

  if (!u) return navigateTo('/login')

  const isSuperAdmin = Boolean((u as any)?.is_super_admin)
  const inAdmin = to.path === '/admin' || to.path.startsWith('/admin/')

  if (inAdmin && !isSuperAdmin) return navigateTo('/dashboard')
  if (!inAdmin && isSuperAdmin) return navigateTo('/admin')
})
