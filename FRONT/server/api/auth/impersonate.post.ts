// POST /api/auth/impersonate { company_id } — super admin "entrar como empresa".
// Asks the API for a temporary token of the company's admin, parks the super
// admin's own token in a second httpOnly cookie and switches the session.
export default defineEventHandler(async (event) => {
  const body = await readBody(event).catch(() => null)
  const companyId = Number(body?.company_id || 0)
  const superToken = getToken(event)

  if (!superToken || !companyId) {
    setResponseStatus(event, 422)
    return { message: 'Empresa no valida' }
  }
  if (getSuperToken(event)) {
    setResponseStatus(event, 409)
    return { message: 'Ya estas dentro de una empresa: sal primero' }
  }

  try {
    const res = await fetch(`${apiBase()}/api/admin/companies/${companyId}/impersonate`, {
      method: 'POST',
      headers: { accept: 'application/json', authorization: `Bearer ${superToken}` },
    })
    const data = await res.json().catch(() => ({}))

    if (!res.ok || !data?.token) {
      setResponseStatus(event, res.status || 500)
      return data
    }

    setSuperTokenCookie(event, superToken)
    setCookie(event, 'rrhh_token', String(data.token), {
      httpOnly: true,
      sameSite: 'lax',
      secure: useRuntimeConfig().cookieSecure === true,
      path: '/',
      maxAge: IMPERSONATION_TTL_SECONDS,
    })

    return { company_id: data.company_id, user: data.user }
  } catch {
    return upstreamUnavailable(event)
  }
})
