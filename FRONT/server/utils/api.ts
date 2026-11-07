// Server-side helpers for talking to the Laravel API.
//
// The browser never talks to the API directly anymore: every call goes
// through the Nitro routes in server/api/*, which forward the request and
// attach the Bearer token taken from the httpOnly `rrhh_token` cookie.
// That removes the token from localStorage/XSS reach and makes CORS a
// server-side concern.

const COOKIE_NAME = 'rrhh_token'

/** Keep in sync with SANCTUM_EXPIRATION (10080 min = 7 days) in the API. */
export const TOKEN_TTL_SECONDS = 7 * 24 * 60 * 60

/** Base URL of the Laravel API, reachable from the Nuxt server. */
export function apiBase(): string {
  const config = useRuntimeConfig()
  return String(config.apiBase || 'http://localhost:8000')
}

export function getToken(event: any): string | undefined {
  return getCookie(event, COOKIE_NAME)
}

export function setTokenCookie(event: any, token: string): void {
  setCookie(event, COOKIE_NAME, token, {
    httpOnly: true,
    sameSite: 'lax',
    secure: useRuntimeConfig().cookieSecure === true,
    path: '/',
    maxAge: TOKEN_TTL_SECONDS,
  })
}

export function clearTokenCookie(event: any): void {
  deleteCookie(event, COOKIE_NAME, { path: '/' })
}

/**
 * Forward the incoming request (method, body, content-type) to `target`,
 * authenticating with the token stored in the httpOnly cookie.
 *
 * Returns the upstream status, the upstream Response (for headers) and the
 * raw body so handlers can replay binary payloads (file downloads).
 */
export async function proxyFetch(event: any, target: string): Promise<{
  status: number
  upstream: Response
  body: Buffer
}> {
  const method = event.method || 'GET'
  const headers: Record<string, string> = { accept: 'application/json' }

  const contentType = getRequestHeader(event, 'content-type')
  if (contentType) headers['content-type'] = contentType

  const token = getToken(event)
  if (token) headers['authorization'] = `Bearer ${token}`

  let body: Buffer | undefined
  if (method !== 'GET' && method !== 'HEAD') {
    const raw = await readRawBody(event, false).catch(() => null)
    body = raw && raw.length ? raw : undefined
  }

  const upstream = await fetch(target, { method, headers, body })
  const buffer = Buffer.from(await upstream.arrayBuffer())

  // The token is expired or was revoked: drop the cookie so the next
  // navigation is redirected to /login instead of looping on 401s.
  if (upstream.status === 401) clearTokenCookie(event)

  return { status: upstream.status, upstream, body: buffer }
}

/** Replay status + relevant headers of a proxied response. */
export function replayResponse(event: any, status: number, upstream: Response): void {
  setResponseStatus(event, status)

  const contentType = upstream.headers.get('content-type')
  if (contentType) setResponseHeader(event, 'content-type', contentType)

  const disposition = upstream.headers.get('content-disposition')
  if (disposition) setResponseHeader(event, 'content-disposition', disposition)
}

/** Standard error payload used when the API cannot be reached at all. */
export function upstreamUnavailable(event: any): { message: string } {
  setResponseStatus(event, 502)
  return { message: 'Servidor no disponible, intentalo de nuevo' }
}
