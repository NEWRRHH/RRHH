// POST /api/auth/stop-view-as — leave "Ver como" and go back to the viewer's
// own session. The temporary token is revoked (and audited) in the API.
export default defineEventHandler(async (event) => {
  const viewAsToken = getToken(event)
  const viewerToken = getViewerToken(event)

  if (!viewerToken) {
    setResponseStatus(event, 422)
    return { message: 'No hay una sesion "Ver como" activa' }
  }

  let userId: number | null = null
  if (viewAsToken) {
    try {
      const res = await fetch(`${apiBase()}/api/view-as/stop`, {
        method: 'POST',
        headers: { accept: 'application/json', authorization: `Bearer ${viewAsToken}` },
      })
      const data = await res.json().catch(() => ({}))
      userId = data?.user_id ?? null
    } catch {
      // the token expires by itself (30 min); restoring the session matters more
    }
  }

  setTokenCookie(event, viewerToken)
  clearViewerTokenCookie(event)

  return { user_id: userId }
})
