// POST /api/auth/register — creates the account in Laravel and starts an
// authenticated session (httpOnly cookie) in the same round trip.
export default defineEventHandler(async (event) => {
  const body = await readBody(event).catch(() => null)

  try {
    const res = await fetch(`${apiBase()}/api/register`, {
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
