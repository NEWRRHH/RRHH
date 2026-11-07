// POST /api/auth/logout — revokes the API token in Laravel and always clears
// the httpOnly session cookie.
export default defineEventHandler(async (event) => {
  const token = getToken(event)

  if (token) {
    try {
      await fetch(`${apiBase()}/api/logout`, {
        method: 'POST',
        headers: { accept: 'application/json', authorization: `Bearer ${token}` },
      })
    } catch {
      // Even if the API is unreachable the local session must die.
    }
  }

  clearTokenCookie(event)

  return { message: 'Logged out' }
})
