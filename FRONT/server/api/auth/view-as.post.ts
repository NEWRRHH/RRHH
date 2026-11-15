// POST /api/auth/view-as { user_id } — RRHH / admin "Ver como" (read-only).
// Asks the API for a short-lived read-only token of the employee, parks the
// viewer's own token in a second httpOnly cookie and switches the session.
export default defineEventHandler(async (event) => {
  const body = await readBody(event).catch(() => null)
  const userId = Number(body?.user_id || 0)
  const viewerToken = getToken(event)

  if (!viewerToken || !userId) {
    setResponseStatus(event, 422)
    return { message: 'Empleado no valido' }
  }
  if (getViewerToken(event) || getSuperToken(event)) {
    setResponseStatus(event, 409)
    return { message: 'Ya estas en otra sesion: sal primero' }
  }

  try {
    const res = await fetch(`${apiBase()}/api/employees/${userId}/view-as`, {
      method: 'POST',
      headers: { accept: 'application/json', authorization: `Bearer ${viewerToken}` },
    })
    const data = await res.json().catch(() => ({}))

    if (!res.ok || !data?.token) {
      setResponseStatus(event, res.status || 500)
      return data
    }

    setViewerTokenCookie(event, viewerToken)
    setCookie(event, 'rrhh_token', String(data.token), {
      httpOnly: true,
      sameSite: 'lax',
      secure: useRuntimeConfig().cookieSecure === true,
      path: '/',
      maxAge: VIEW_AS_TTL_SECONDS,
    })

    return { user: data.user, expires_at: data.expires_at }
  } catch {
    return upstreamUnavailable(event)
  }
})
