// POST /api/auth/logout — revokes the API token in Laravel and always clears
// the httpOnly session cookie. During a super admin support session both the
// temporary company token and the super admin's own token are closed.
export default defineEventHandler(async (event) => {
  const token = getToken(event)
  const superToken = getSuperToken(event)

  const post = (path: string, bearer: string) =>
    fetch(`${apiBase()}${path}`, {
      method: 'POST',
      headers: { accept: 'application/json', authorization: `Bearer ${bearer}` },
    }).catch(() => {
      // Even if the API is unreachable the local session must die.
    })

  if (superToken) {
    // impersonation token: revoke it without touching the company user's data
    if (token) await post('/api/impersonation/stop', token)
    await post('/api/logout', superToken)
  } else if (token) {
    await post('/api/logout', token)
  }

  clearTokenCookie(event)
  clearSuperTokenCookie(event)

  return { message: 'Logged out' }
})
