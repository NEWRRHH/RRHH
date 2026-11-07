// Catch-all proxy: every /api/<path> call from the app is forwarded to the
// Laravel API by the Nuxt server, which attaches the Bearer token read from
// the httpOnly session cookie. More specific routes under server/api/auth/*
// and server/api/broadcasting/* take precedence over this one.

export default defineEventHandler(async (event) => {
  const url = getRequestURL(event)
  const subPath = url.pathname.replace(/^\/api\//, '')
  const target = `${apiBase()}/api/${subPath}${url.search}`

  try {
    const { status, upstream, body } = await proxyFetch(event, target)
    replayResponse(event, status, upstream)
    return body
  } catch {
    return upstreamUnavailable(event)
  }
})
