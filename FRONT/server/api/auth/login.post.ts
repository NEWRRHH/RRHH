// POST /api/auth/login — validates credentials against Laravel, stores the
// returned token in an httpOnly cookie and hands back the user profile.
export default defineEventHandler(async (event) => {
  const body = await readBody(event).catch(() => null)

  try {
    const res = await fetch(`${apiBase()}/api/login`, {
      method: 'POST',
      headers: { accept: 'application/json', 'content-type': 'application/json' },
      body: JSON.stringify(body ?? {}),
    })

    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
      setResponseStatus(event, res.status)
      return data
    }

    if (data?.token) setTokenCookie(event, String(data.token))

    return { user: data?.user ?? null }
  } catch {
    return upstreamUnavailable(event)
  }
})
