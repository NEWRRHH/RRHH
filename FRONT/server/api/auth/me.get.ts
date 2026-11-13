// GET /api/auth/me — resolves the current user from the httpOnly cookie.
// Used by the global auth middleware (works during SSR because the Nuxt
// server can read the cookie and call the API with it).
export default defineEventHandler(async (event) => {
  const token = getToken(event)

  if (!token) {
    setResponseStatus(event, 401)
    return { message: 'Unauthenticated' }
  }

  const fetchMe = (bearer: string) =>
    fetch(`${apiBase()}/api/user`, {
      headers: { accept: 'application/json', authorization: `Bearer ${bearer}` },
    })

  try {
    let res = await fetchMe(token)

    // Support session expired (2h token): fall back to the super admin's own session.
    const superToken = getSuperToken(event)
    if (res.status === 401 && superToken) {
      setTokenCookie(event, superToken)
      clearSuperTokenCookie(event)
      res = await fetchMe(superToken)
    }

    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
      clearTokenCookie(event)
      setResponseStatus(event, res.status)
      return { message: data?.message || 'Unauthenticated' }
    }

    return data
  } catch {
    return upstreamUnavailable(event)
  }
})
