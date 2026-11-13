// POST /api/auth/stop-impersonation — leave the company and go back to the
// super admin session. The temporary token is revoked in the API.
export default defineEventHandler(async (event) => {
  const companyToken = getToken(event)
  const superToken = getSuperToken(event)

  if (!superToken) {
    setResponseStatus(event, 422)
    return { message: 'No hay una sesion de soporte activa' }
  }

  let companyId: number | null = null
  if (companyToken) {
    try {
      const res = await fetch(`${apiBase()}/api/impersonation/stop`, {
        method: 'POST',
        headers: { accept: 'application/json', authorization: `Bearer ${companyToken}` },
      })
      const data = await res.json().catch(() => ({}))
      companyId = data?.company_id ?? null
    } catch {
      // the token expires by itself (2h); restoring the session matters more
    }
  }

  setTokenCookie(event, superToken)
  clearSuperTokenCookie(event)

  return { company_id: companyId }
})
